<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\AccountType;
use App\Enums\PostingDirection;
use App\Enums\TransactionSplitRule;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Posting;
use App\Models\Transaction;
use App\Modules\Ledger\Data\PostManualTransactionData;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;
use App\Modules\Ledger\Services\FinancialProfileService;
use App\Modules\Ledger\Services\TransactionSplitService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class PostManualTransactionAction
{
    public function __construct(
        private readonly TransactionSplitService $splitService,
        private readonly FinancialProfileService $financialProfileService,
    ) {}

    public function execute(PostManualTransactionData $payload): Transaction
    {
        if ($payload->amount <= 0) {
            throw new InvalidLedgerPostingException('Amount must be greater than zero.');
        }

        $this->assertAccountsBelongToLedger($payload->ledgerId, $payload->payerAccountId, $payload->destinationAccountId);
        $this->validateParticipants($payload->splitRule, $payload->participants);

        $ledgerAccounts = Account::query()->where('ledger_id', $payload->ledgerId)->get();

        $spaceExpense = $ledgerAccounts->where('id', $payload->destinationAccountId)->where('type', AccountType::SpaceExpense->value)->first();
        $splitClearing = $ledgerAccounts->where('type', AccountType::SplitClearing->value)->first();

        if (!$spaceExpense) {
            throw new InvalidLedgerPostingException('Destination account must be a valid Space Expense account.');
        }

        if (!$splitClearing) {
            throw new InvalidLedgerPostingException('Split Clearing account missing for ledger.');
        }

        $userIds = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $ledgerAccounts->pluck('owner_id')->filter()->all(),
        )));
        $shareableByUser = $this->financialProfileService->shareableIncomeForUsers(
            Ledger::query()->findOrFail($payload->ledgerId),
            $userIds,
            $payload->date,
        );

        $participantUserIds = count($payload->participants) > 0
            ? array_values(array_map(fn (array $p): int => (int) $p['user_id'], $payload->participants))
            : $userIds;

        $allocations = $this->splitService->allocateByRule(
            $payload->splitRule,
            $payload->amount,
            $participantUserIds,
            $payload->participants,
            $shareableByUser,
        );

        $this->assertAllocationsMatchAmount($payload->amount, $allocations);

        return DB::transaction(function () use ($payload, $spaceExpense, $splitClearing, $ledgerAccounts, $allocations): Transaction {
            $transaction = Transaction::query()->create([
                'ledger_id' => $payload->ledgerId,
                'payer_account_id' => $payload->payerAccountId,
                'destination_account_id' => $payload->destinationAccountId,
                'amount' => $payload->amount,
                'type' => $payload->type,
                'source' => $payload->source,
                'source_metadata' => $payload->sourceMetadata,
                'split_rule' => $payload->splitRule,
                'participants' => $payload->participants,
                'description' => $payload->description,
                'date' => $payload->date,
            ]);

            $now = Carbon::now()->toDateTimeString();
            $postings = [];

            // 1. Credit Source (Payer)
            $postings[] = [
                'transaction_id' => $transaction->id,
                'account_id' => $payload->payerAccountId,
                'amount' => $payload->amount,
                'direction' => PostingDirection::Credit->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // 2. Debit Space Expense
            $postings[] = [
                'transaction_id' => $transaction->id,
                'account_id' => $spaceExpense->id,
                'amount' => $payload->amount,
                'direction' => PostingDirection::Debit->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // 3. Credit Split Clearing
            $postings[] = [
                'transaction_id' => $transaction->id,
                'account_id' => $splitClearing->id,
                'amount' => $payload->amount,
                'direction' => PostingDirection::Credit->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // 4. Debit User Liabilities
            foreach ($allocations as $userId => $allocatedAmount) {
                if ($allocatedAmount <= 0) {
                    continue;
                }

                $liabilityAccount = $ledgerAccounts
                    ->where('type', AccountType::UserLiability->value)
                    ->where('owner_id', $userId)
                    ->first();

                if (!$liabilityAccount) {
                    throw new InvalidLedgerPostingException("Liability account missing for user {$userId}.");
                }

                $postings[] = [
                    'transaction_id' => $transaction->id,
                    'account_id' => $liabilityAccount->id,
                    'amount' => $allocatedAmount,
                    'direction' => PostingDirection::Debit->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            Posting::query()->insert($postings);

            return $transaction->load(['payerAccount', 'destinationAccount', 'postings.account']);
        });
    }

    private function assertAccountsBelongToLedger(int $ledgerId, int $payerAccountId, int $destinationAccountId): void
    {
        $existingCount = Account::query()
            ->where('ledger_id', $ledgerId)
            ->whereIn('id', [$payerAccountId, $destinationAccountId])
            ->count();

        if ($existingCount !== ($payerAccountId === $destinationAccountId ? 1 : 2)) {
            throw new InvalidLedgerPostingException('One or more accounts do not belong to the target ledger.');
        }
    }

    /**
     * @param list<array{user_id:int, share?:int}> $participants
     */
    private function validateParticipants(string $splitRule, array $participants): void
    {
        $rule = TransactionSplitRule::tryFrom($splitRule);

        if ($rule === TransactionSplitRule::Individual && count($participants) !== 1) {
            throw new InvalidLedgerPostingException('Individual split requires exactly one participant.');
        }

        if ($rule === TransactionSplitRule::Manual) {
            if (count($participants) === 0) {
                throw new InvalidLedgerPostingException('Manual split requires at least one participant.');
            }

            foreach ($participants as $participant) {
                if (!isset($participant['share']) || (int) $participant['share'] <= 0) {
                    throw new InvalidLedgerPostingException('Manual split requires share > 0 on all participants.');
                }
            }
        }
    }

    /**
     * @param array<int, int> $allocations
     */
    private function assertAllocationsMatchAmount(int $amount, array $allocations): void
    {
        if ($amount > 0 && $allocations === []) {
            throw new InvalidLedgerPostingException('Split produced no allocations for a positive amount.');
        }

        if (array_sum($allocations) !== $amount) {
            throw new InvalidLedgerPostingException('Split allocations must sum to the transaction amount.');
        }
    }
}
