<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\AccountType;
use App\Enums\TransactionSource;
use App\Enums\TransactionSplitRule;
use App\Models\Account;
use App\Models\AiProviderSetting;
use App\Models\BankAccountMapping;
use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\StatementImport;
use App\Models\StatementImportEntry;
use App\Models\Transaction;
use App\Modules\Ledger\Data\CreateAccountData;
use App\Modules\Ledger\Data\CreatePendingTransactionData;
use App\Modules\Ledger\Services\ByokStatementParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class ProcessStatementImportAction
{
    public function __construct(
        private ByokStatementParser $parser,
        private CreatePendingTransactionAction $createPendingTransactionAction,
        private CreateAccountAction $createAccountAction,
    ) {}

    public function execute(StatementImport $import): void
    {
        $setting = AiProviderSetting::query()->where('user_id', $import->user_id)->first();

        if (!$setting instanceof AiProviderSetting) {
            throw new RuntimeException('Configure an AI provider before processing statements.');
        }

        $contents = Storage::disk('local')->get($import->file_path);
        $parsed = $this->parser->parse($setting, $contents, $import->mime_type);

        $import->update([
            'status' => 'processing',
            'parsed_count' => count($parsed['transactions']),
            'pending_count' => 0,
            'duplicate_count' => 0,
            'failed_count' => 0,
            'error_message' => null,
        ]);

        $ledger = Ledger::query()->findOrFail($import->ledger_id);
        $membership = LedgerUser::query()
            ->where('ledger_id', $import->ledger_id)
            ->where('user_id', $import->user_id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        /** @var array<string, BankAccountMapping> $mappings */
        $mappings = [];

        foreach ($parsed['bank_accounts'] as $parsedAccount) {
            try {
                $mapping = $this->resolveBankAccountMapping(
                    $ledger,
                    $import,
                    $parsedAccount,
                    $membership->ai_import_auto_create_accounts,
                );
                $externalId = $this->requiredString($parsedAccount, 'external_id');
                $mappings[$externalId] = $mapping;
            } catch (Throwable) {
                continue;
            }
        }

        $pendingCount = 0;
        $duplicateCount = 0;
        $failedCount = 0;

        foreach ($parsed['transactions'] as $parsedTransaction) {
            try {
                $result = $this->processTransaction(
                    $import,
                    $membership,
                    $parsedTransaction,
                    $mappings,
                );

                if ($result === 'pending') {
                    $pendingCount++;
                } elseif ($result === 'duplicate') {
                    $duplicateCount++;
                } else {
                    $failedCount++;
                }
            } catch (Throwable) {
                $failedCount++;
            }
        }

        $import->update([
            'status' => 'completed',
            'pending_count' => $pendingCount,
            'duplicate_count' => $duplicateCount,
            'failed_count' => $failedCount,
            'processed_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed> $parsedAccount
     */
    private function resolveBankAccountMapping(
        Ledger $ledger,
        StatementImport $import,
        array $parsedAccount,
        bool $autoCreateAccounts,
    ): BankAccountMapping {
        $externalId = $this->requiredString($parsedAccount, 'external_id');
        $name = $this->requiredString($parsedAccount, 'name');
        $lastFour = $this->optionalString($parsedAccount, 'last_four');
        $ownership = $this->optionalString($parsedAccount, 'ownership') === 'joint'
            ? 'joint'
            : 'personal';
        $fingerprint = hash('sha256', $this->normalize($externalId . '|' . $name . '|' . $lastFour));

        return DB::transaction(function () use (
            $ledger,
            $import,
            $name,
            $lastFour,
            $ownership,
            $fingerprint,
            $autoCreateAccounts,
        ): BankAccountMapping {
            $mapping = BankAccountMapping::query()->firstOrCreate(
                [
                    'ledger_id' => $ledger->id,
                    'user_id' => $import->user_id,
                    'external_account_fingerprint' => $fingerprint,
                ],
                [
                    'external_account_name' => $name,
                    'masked_identifier' => $lastFour === null ? null : '•••• ' . $lastFour,
                    'ownership_type' => $ownership,
                ],
            );

            $candidate = $mapping->account_id === null
                ? $this->findMatchingAccount($ledger, $import->user_id, $name, $lastFour, $ownership)
                : null;

            $updates = [
                'external_account_name' => $name,
                'masked_identifier' => $lastFour === null ? null : '•••• ' . $lastFour,
                'ownership_type' => $ownership,
                'suggested_account_id' => $mapping->account_id === null ? $candidate?->id : null,
            ];

            if ($mapping->account_id === null && $autoCreateAccounts) {
                $account = $candidate ?? $this->createMappedAccount(
                    $ledger,
                    $import->user_id,
                    $name,
                    $lastFour,
                    $ownership,
                );
                $updates['account_id'] = $account->id;
                $updates['suggested_account_id'] = null;
            }

            $mapping->update($updates);

            return $mapping->fresh(['account', 'suggestedAccount']) ?? $mapping;
        });
    }

    private function findMatchingAccount(
        Ledger $ledger,
        int $userId,
        string $name,
        ?string $lastFour,
        string $ownership,
    ): ?Account {
        $accounts = Account::query()
            ->where('ledger_id', $ledger->id)
            ->when(
                $ownership === 'joint',
                fn ($query) => $query
                    ->where('type', AccountType::PoolAsset)
                    ->whereNull('owner_id'),
                fn ($query) => $query
                    ->where('type', AccountType::UserFunding)
                    ->where('owner_id', $userId),
            )
            ->get();

        $normalizedName = $this->normalize($name);
        $bestAccount = null;
        $bestScore = 0.0;

        foreach ($accounts as $account) {
            $normalizedAccountName = $this->normalize($account->name);

            if ($normalizedName === $normalizedAccountName) {
                return $account;
            }

            similar_text($normalizedName, $normalizedAccountName, $score);

            if ($lastFour !== null && str_contains($normalizedAccountName, $lastFour)) {
                $score += 25;
            }

            if ($score > $bestScore) {
                $bestAccount = $account;
                $bestScore = $score;
            }
        }

        return $bestScore >= 55 ? $bestAccount : null;
    }

    private function createMappedAccount(
        Ledger $ledger,
        int $userId,
        string $name,
        ?string $lastFour,
        string $ownership,
    ): Account {
        return $this->createAccountAction->execute(
            $ledger,
            new CreateAccountData(
                name: $lastFour === null ? $name : "{$name} •••• {$lastFour}",
                type: $ownership === 'joint' ? AccountType::PoolAsset : AccountType::UserFunding,
                ownerId: $ownership === 'joint' ? null : $userId,
            ),
        );
    }

    /**
     * @param array<string, mixed> $parsedTransaction
     * @param array<string, BankAccountMapping> $mappings
     */
    private function processTransaction(
        StatementImport $import,
        LedgerUser $membership,
        array $parsedTransaction,
        array $mappings,
    ): string {
        if ($this->optionalString($parsedTransaction, 'transaction_type') !== 'expense') {
            return 'failed';
        }

        $bankAccountExternalId = $this->requiredString($parsedTransaction, 'bank_account_external_id');
        $mapping = $mappings[$bankAccountExternalId] ?? null;

        if (!$mapping instanceof BankAccountMapping) {
            return 'failed';
        }

        $date = $this->requiredString($parsedTransaction, 'date');

        if (!$this->validDate($date)) {
            return 'failed';
        }

        $description = $this->requiredString($parsedTransaction, 'description');
        $rawDescription = $this->optionalString($parsedTransaction, 'raw_description') ?? $description;
        $amount = filter_var($parsedTransaction['amount_cents'] ?? null, FILTER_VALIDATE_INT);

        if (!is_int($amount) || $amount <= 0) {
            return 'failed';
        }

        $externalTransactionId = $this->optionalString($parsedTransaction, 'external_id');
        $transactionFingerprint = hash(
            'sha256',
            $mapping->external_account_fingerprint
                . '|'
                . ($externalTransactionId ?? "{$date}|{$amount}|{$this->normalize($rawDescription)}"),
        );

        return DB::transaction(function () use (
            $import,
            $membership,
            $parsedTransaction,
            $mapping,
            $date,
            $description,
            $rawDescription,
            $amount,
            $externalTransactionId,
            $transactionFingerprint,
        ): string {
            $entry = StatementImportEntry::query()->firstOrCreate(
                [
                    'ledger_id' => $import->ledger_id,
                    'transaction_fingerprint' => $transactionFingerprint,
                ],
                [
                    'statement_import_id' => $import->id,
                    'status' => 'processing',
                    'raw_data' => $parsedTransaction,
                ],
            );

            if (!$entry->wasRecentlyCreated) {
                return 'duplicate';
            }

            $payerAccountId = $mapping->account_id;
            $destinationAccountId = $membership->default_expense_account_id;

            if (
                $payerAccountId !== null
                && $destinationAccountId !== null
                && $this->existingTransaction(
                    $import->ledger_id,
                    $payerAccountId,
                    $destinationAccountId,
                    $date,
                    $amount,
                    $description,
                )
            ) {
                $entry->update(['status' => 'duplicate']);

                return 'duplicate';
            }

            $confidence = is_numeric($parsedTransaction['confidence'] ?? null)
                ? min(1, max(0, (float) $parsedTransaction['confidence']))
                : null;
            $rationale = $this->optionalString($parsedTransaction, 'rationale');

            $pendingTransaction = $this->createPendingTransactionAction->execute(
                new CreatePendingTransactionData(
                    ledgerId: $import->ledger_id,
                    userId: $import->user_id,
                    payerAccountId: $payerAccountId,
                    destinationAccountId: $destinationAccountId,
                    rawData: [
                        ...$parsedTransaction,
                        'description' => $rawDescription,
                        'statement_import_id' => $import->public_id,
                        'statement_import_entry_id' => $entry->id,
                        'bank_account_mapping_id' => $mapping->id,
                        'bank_account_name' => $mapping->external_account_name,
                        'ownership' => $mapping->ownership_type,
                        'suggested_account_id' => $mapping->suggested_account_id,
                        'external_transaction_id' => $externalTransactionId,
                    ],
                    description: $description,
                    amount: $amount,
                    splitRule: TransactionSplitRule::Proportional->value,
                    participants: [],
                    date: $date,
                    source: TransactionSource::AiImport->value,
                    confidence: $confidence,
                    rationale: $rationale,
                ),
            );

            $entry->update([
                'status' => 'pending',
                'pending_transaction_id' => $pendingTransaction->id,
            ]);

            return 'pending';
        });
    }

    private function existingTransaction(
        int $ledgerId,
        int $payerAccountId,
        int $destinationAccountId,
        string $date,
        int $amount,
        string $description,
    ): bool {
        return Transaction::query()
            ->where('ledger_id', $ledgerId)
            ->where('payer_account_id', $payerAccountId)
            ->where('destination_account_id', $destinationAccountId)
            ->whereDate('date', $date)
            ->where('amount', $amount)
            ->where('description', $description)
            ->exists();
    }

    /**
     * @param array<string, mixed> $value
     */
    private function requiredString(array $value, string $key): string
    {
        $result = $this->optionalString($value, $key);

        if ($result === null) {
            throw new RuntimeException("The parsed statement is missing {$key}.");
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function optionalString(array $value, string $key): ?string
    {
        $result = $value[$key] ?? null;

        if (!is_string($result) || trim($result) === '') {
            return null;
        }

        return trim($result);
    }

    private function normalize(?string $value): string
    {
        return Str::of($value ?? '')
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function validDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
