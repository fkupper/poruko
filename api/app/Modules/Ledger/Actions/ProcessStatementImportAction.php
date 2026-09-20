<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\AccountType;
use App\Enums\PendingTransactionStatus;
use App\Enums\StatementImportStage;
use App\Enums\TransactionSource;
use App\Enums\TransactionSplitRule;
use App\Models\Account;
use App\Models\AiProviderSetting;
use App\Models\BankAccountMapping;
use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\PendingTransaction;
use App\Models\StatementImport;
use App\Models\StatementImportEntry;
use App\Modules\Ledger\Data\CreateAccountData;
use App\Modules\Ledger\Data\CreatePendingTransactionData;
use App\Modules\Ledger\Services\ByokStatementParser;
use App\Modules\Ledger\Services\StatementImportIdentity;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        $this->markStage($import, StatementImportStage::Parsing);

        $contents = Storage::disk('local')->get($import->file_path);
        $parsed = $this->parser->parse($setting, $contents, $import->mime_type);

        $accountCount = count($parsed['bank_accounts']);
        $transactionCount = count($parsed['transactions']);

        $import->update([
            'status' => 'processing',
            'stage' => StatementImportStage::MappingAccounts->value,
            'parsed_count' => $transactionCount,
            'pending_count' => 0,
            'duplicate_count' => 0,
            'failed_count' => 0,
            'progress_current' => 0,
            'progress_total' => $accountCount + $transactionCount,
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
            } finally {
                $this->advanceProgress($import);
            }
        }

        $this->markStage($import, StatementImportStage::ProcessingTransactions);

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
            } finally {
                $this->advanceProgress($import);
            }
        }

        $import->update([
            'status' => 'completed',
            'stage' => StatementImportStage::Completed->value,
            'pending_count' => $pendingCount,
            'duplicate_count' => $duplicateCount,
            'failed_count' => $failedCount,
            'progress_current' => $import->progress_total,
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
        $identity = StatementImportIdentity::fromParsedAccount($parsedAccount);
        $ownership = $this->optionalString($parsedAccount, 'ownership') === 'joint'
            ? 'joint'
            : 'personal';

        return DB::transaction(function () use (
            $ledger,
            $import,
            $identity,
            $ownership,
            $autoCreateAccounts,
        ): BankAccountMapping {
            $mapping = $this->findExistingMapping($ledger->id, $import->user_id, $identity);

            if (!$mapping instanceof BankAccountMapping) {
                $mapping = BankAccountMapping::query()->create([
                    'ledger_id' => $ledger->id,
                    'user_id' => $import->user_id,
                    'external_account_fingerprint' => $identity->fingerprint,
                    'external_account_name' => $identity->displayName,
                    'masked_identifier' => $identity->lastFour === null ? null : '•••• ' . $identity->lastFour,
                    'ownership_type' => $ownership,
                ]);
            }

            $alreadyLinked = $mapping->account_id !== null;
            $candidate = $alreadyLinked
                ? null
                : $this->findMatchingAccount(
                    $ledger,
                    $import->user_id,
                    $identity->displayName,
                    $identity->lastFour,
                    $ownership,
                );

            $updates = [
                'external_account_name' => $identity->displayName,
                'masked_identifier' => $identity->lastFour === null ? null : '•••• ' . $identity->lastFour,
                'suggested_account_id' => $alreadyLinked ? null : $candidate?->id,
            ];

            if (!$alreadyLinked) {
                $updates['ownership_type'] = $ownership;
            }

            if (!$alreadyLinked && $autoCreateAccounts) {
                $account = $candidate ?? $this->createMappedAccount(
                    $ledger,
                    $import->user_id,
                    $identity->displayName,
                    $identity->lastFour,
                    $ownership,
                );
                $updates['account_id'] = $account->id;
                $updates['suggested_account_id'] = null;
            }

            $mapping->update($updates);

            return $mapping->fresh(['account', 'suggestedAccount']) ?? $mapping;
        });
    }

    private function findExistingMapping(
        int $ledgerId,
        int $userId,
        StatementImportIdentity $identity,
    ): ?BankAccountMapping {
        $mappings = BankAccountMapping::query()
            ->where('ledger_id', $ledgerId)
            ->where('user_id', $userId)
            ->get();

        $byFingerprint = $mappings->firstWhere('external_account_fingerprint', $identity->fingerprint);

        if ($byFingerprint instanceof BankAccountMapping) {
            return $byFingerprint;
        }

        $byDigits = $mappings->first(
            fn (BankAccountMapping $mapping): bool => $identity->matchesMapping($mapping),
        );

        return $byDigits instanceof BankAccountMapping ? $byDigits : null;
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

        $normalizedName = StatementImportIdentity::normalize($name);
        $bestAccount = null;
        $bestScore = 0.0;

        foreach ($accounts as $account) {
            $normalizedAccountName = StatementImportIdentity::normalize($account->name);

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
        $mapping = $this->mappingForTransaction($mappings, $bankAccountExternalId);

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

        $transactionFingerprint = StatementImportIdentity::transactionFingerprint(
            $mapping->external_account_fingerprint,
            $date,
            $amount,
            $rawDescription,
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

            if ($this->isDuplicateContent($import, $mapping, $entry->id, $date, $amount, $rawDescription)) {
                $entry->update(['status' => 'duplicate']);

                return 'duplicate';
            }

            $confidence = is_numeric($parsedTransaction['confidence'] ?? null)
                ? min(1, max(0, (float) $parsedTransaction['confidence']))
                : null;
            $rationale = $this->optionalString($parsedTransaction, 'rationale');
            $split = $this->suggestedSplit($parsedTransaction, $import->user_id);
            $externalTransactionId = $this->optionalString($parsedTransaction, 'external_id');

            $pendingTransaction = $this->createPendingTransactionAction->execute(
                new CreatePendingTransactionData(
                    ledgerId: $import->ledger_id,
                    userId: $import->user_id,
                    payerAccountId: $mapping->account_id,
                    destinationAccountId: $this->destinationAccountId($membership),
                    rawData: [
                        ...$parsedTransaction,
                        'description' => $rawDescription,
                        'statement_import_id' => $import->public_id,
                        'statement_import_entry_id' => $entry->id,
                        'bank_account_mapping_id' => $mapping->id,
                        'bank_account_name' => $mapping->external_account_name,
                        'ownership' => $mapping->ownership_type,
                        'suggested_account_id' => $mapping->suggested_account_id,
                        'sharing_type' => $split['sharing_type'],
                        'external_transaction_id' => $externalTransactionId,
                    ],
                    description: $description,
                    amount: $amount,
                    splitRule: $split['split_rule'],
                    participants: $split['participants'],
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

    /**
     * @param array<string, BankAccountMapping> $mappings
     */
    private function mappingForTransaction(array $mappings, string $bankAccountExternalId): ?BankAccountMapping
    {
        if (isset($mappings[$bankAccountExternalId])) {
            return $mappings[$bankAccountExternalId];
        }

        $digits = StatementImportIdentity::preferredDigits($bankAccountExternalId);

        foreach ($mappings as $mapping) {
            if (StatementImportIdentity::digitsMatch(
                $digits,
                StatementImportIdentity::preferredDigits($mapping->external_account_name, $mapping->masked_identifier),
            )) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $parsedTransaction
     * @return array{split_rule: string, participants: list<array{user_id: int}>, sharing_type: string}
     */
    private function suggestedSplit(array $parsedTransaction, int $userId): array
    {
        $sharingType = mb_strtolower($this->optionalString($parsedTransaction, 'sharing_type') ?? 'shared');

        if (in_array($sharingType, ['individual', 'personal'], true)) {
            return [
                'split_rule' => TransactionSplitRule::Individual->value,
                'participants' => [['user_id' => $userId]],
                'sharing_type' => 'individual',
            ];
        }

        return [
            'split_rule' => TransactionSplitRule::Proportional->value,
            'participants' => [],
            'sharing_type' => 'shared',
        ];
    }

    private function isDuplicateContent(
        StatementImport $import,
        BankAccountMapping $mapping,
        int $currentEntryId,
        string $date,
        int $amount,
        string $rawDescription,
    ): bool {
        $normalized = StatementImportIdentity::normalize($rawDescription);
        $accountDigits = StatementImportIdentity::preferredDigits(
            $mapping->external_account_name,
            $mapping->masked_identifier,
        );

        $entries = StatementImportEntry::query()
            ->where('ledger_id', $import->ledger_id)
            ->whereKeyNot($currentEntryId)
            ->where('raw_data->date', $date)
            ->get();

        foreach ($entries as $entry) {
            $raw = is_array($entry->raw_data) ? $entry->raw_data : [];
            $entryAmount = filter_var($raw['amount_cents'] ?? null, FILTER_VALIDATE_INT);
            $entryDescription = $this->optionalString($raw, 'raw_description')
                ?? $this->optionalString($raw, 'description')
                ?? '';
            $entryDigits = StatementImportIdentity::preferredDigits(
                $this->optionalString($raw, 'bank_account_external_id'),
            );

            if (
                is_int($entryAmount)
                && $entryAmount === $amount
                && StatementImportIdentity::normalize($entryDescription) === $normalized
                && StatementImportIdentity::digitsMatch($accountDigits, $entryDigits)
            ) {
                return true;
            }
        }

        return PendingTransaction::query()
            ->where('ledger_id', $import->ledger_id)
            ->whereIn('status', [
                PendingTransactionStatus::Pending->value,
                PendingTransactionStatus::Approved->value,
            ])
            ->whereDate('date', $date)
            ->where('suggested_amount', $amount)
            ->get()
            ->contains(function (PendingTransaction $pending) use ($accountDigits, $normalized): bool {
                $raw = is_array($pending->raw_data) ? $pending->raw_data : [];
                $pendingDescription = $this->optionalString($raw, 'description')
                    ?? $pending->suggested_description
                    ?? '';
                $pendingDigits = StatementImportIdentity::preferredDigits(
                    $this->optionalString($raw, 'bank_account_external_id'),
                    $this->optionalString($raw, 'bank_account_name'),
                );

                return StatementImportIdentity::normalize($pendingDescription) === $normalized
                    && StatementImportIdentity::digitsMatch($accountDigits, $pendingDigits);
            });
    }

    private function destinationAccountId(LedgerUser $membership): ?int
    {
        if ($membership->default_expense_account_id !== null) {
            return $membership->default_expense_account_id;
        }

        $accountId = Account::query()
            ->where('ledger_id', $membership->ledger_id)
            ->where('type', AccountType::SpaceExpense)
            ->value('id');

        return is_numeric($accountId) ? (int) $accountId : null;
    }

    private function markStage(StatementImport $import, StatementImportStage $stage): void
    {
        $import->update([
            'status' => 'processing',
            'stage' => $stage->value,
        ]);
    }

    private function advanceProgress(StatementImport $import): void
    {
        $import->increment('progress_current');
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

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
