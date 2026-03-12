<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\PostingDirection;
use App\Enums\TransactionSplitRule;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Posting;
use App\Models\Transaction;
use App\Modules\Ledger\Data\PostManualTransactionData;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;
use App\Modules\Ledger\Services\FinancialProfileService;
use Illuminate\Support\Facades\DB;

class PostManualTransactionAction
{
    public function __construct(
        private readonly FinancialProfileService $financialProfileService,
    ) {}
    public function execute(PostManualTransactionData $payload): Transaction
    {
        $ledgerId = $payload->ledgerId;
        $payerAccountId = $payload->payerAccountId;
        $amount = $payload->amount;
        $splitRule = $payload->splitRule;
        $participants = $payload->participants;
        $date = $payload->date;
        $description = $payload->description;
        $type = $payload->type;

        if ($amount <= 0) {
            throw new InvalidLedgerPostingException('Amount must be greater than zero.');
        }

        $this->assertAccountsBelongToLedger(
            $ledgerId,
            $payerAccountId,
            array_map(static fn (array $participant): int => (int) $participant['account_id'], $participants),
        );

        $allocatedParticipants = $this->allocateParticipants($amount, $splitRule, $participants, $ledgerId, $date);

        return DB::transaction(function () use (
            $ledgerId,
            $payerAccountId,
            $amount,
            $splitRule,
            $allocatedParticipants,
            $description,
            $date,
            $type
        ): Transaction {
            $transaction = Transaction::query()->create([
                'ledger_id' => $ledgerId,
                'payer_account_id' => $payerAccountId,
                'amount' => $amount,
                'type' => $type,
                'split_rule' => $splitRule,
                'participants' => $allocatedParticipants,
                'description' => $description,
                'date' => $date,
            ]);

            $postings = [
                [
                    'transaction_id' => $transaction->id,
                    'account_id' => $payerAccountId,
                    'amount' => $amount,
                    'direction' => PostingDirection::Credit->value,
                ],
            ];

            foreach ($allocatedParticipants as $participant) {
                $postings[] = [
                    'transaction_id' => $transaction->id,
                    'account_id' => (int) $participant['account_id'],
                    'amount' => (int) $participant['amount'],
                    'direction' => PostingDirection::Debit->value,
                ];
            }

            Posting::query()->insert($postings);

            $sumDebits = (int) collect($postings)
                ->where('direction', PostingDirection::Debit->value)
                ->sum('amount');
            $sumCredits = (int) collect($postings)
                ->where('direction', PostingDirection::Credit->value)
                ->sum('amount');

            if ($sumDebits !== $sumCredits) {
                throw new InvalidLedgerPostingException('Debits and credits are unbalanced.');
            }

            return $transaction->load(['payerAccount', 'postings.account']);
        });
    }

    /**
     * @param  array<int, int>  $participantAccountIds
     */
    private function assertAccountsBelongToLedger(int $ledgerId, int $payerAccountId, array $participantAccountIds): void
    {
        $accountIds = collect([$payerAccountId, ...$participantAccountIds])->unique()->values();

        $existingCount = Account::query()
            ->where('ledger_id', $ledgerId)
            ->whereIn('id', $accountIds)
            ->count();

        if ($existingCount !== $accountIds->count()) {
            throw new InvalidLedgerPostingException('One or more accounts do not belong to the target ledger.');
        }
    }

    /**
     * @param  array<int, array{account_id:int, amount?:int}>  $participants
     * @return array<int, array{account_id:int, amount:int}>
     */
    private function allocateParticipants(int $amount, string $splitRule, array $participants, int $ledgerId, string $date): array
    {
        if (count($participants) === 0) {
            throw new InvalidLedgerPostingException('At least one participant is required.');
        }

        $rule = TransactionSplitRule::tryFrom($splitRule);

        return match ($rule) {
            TransactionSplitRule::Equal => $this->allocateEqualParticipants($amount, $participants),
            TransactionSplitRule::Individual => $this->allocateIndividualParticipants($amount, $participants),
            TransactionSplitRule::Proportional => $this->allocateProportionalParticipants($amount, $participants, $ledgerId, $date),
            default => throw new InvalidLedgerPostingException('Unsupported split rule for manual transaction.'),
        };
    }

    /**
     * @param  array<int, array{account_id:int, amount?:int}>  $participants
     * @return array<int, array{account_id:int, amount:int}>
     */
    private function allocateEqualParticipants(int $amount, array $participants): array
    {
        $participantCount = count($participants);
        $baseAmount = intdiv($amount, $participantCount);
        $remainder = $amount % $participantCount;

        $allocated = [];

        foreach ($participants as $index => $participant) {
            $participantAmount = $baseAmount;

            if ($index < $remainder) {
                $participantAmount++;
            }

            $allocated[] = [
                'account_id' => (int) $participant['account_id'],
                'amount' => $participantAmount,
            ];
        }

        return $allocated;
    }

    /**
     * @param  array<int, array{account_id:int, amount?:int}>  $participants
     * @return array<int, array{account_id:int, amount:int}>
     */
    private function allocateIndividualParticipants(int $amount, array $participants): array
    {
        $allocated = [];
        $sum = 0;

        foreach ($participants as $participant) {
            $participantAmount = (int) ($participant['amount'] ?? -1);

            if ($participantAmount < 0) {
                throw new InvalidLedgerPostingException('Individual split requires a non-negative amount per participant.');
            }

            $sum += $participantAmount;
            $allocated[] = [
                'account_id' => (int) $participant['account_id'],
                'amount' => $participantAmount,
            ];
        }

        if ($sum !== $amount) {
            throw new InvalidLedgerPostingException('Individual split participant amounts must equal transaction amount.');
        }

        return $allocated;
    }

    /**
     * Allocate proportionally based on each participant's shareable income.
     *
     * @param  array<int, array{account_id:int, amount?:int}>  $participants
     * @return array<int, array{account_id:int, amount:int}>
     */
    private function allocateProportionalParticipants(int $amount, array $participants, int $ledgerId, string $date): array
    {
        $accountIds = array_map(
            static fn (array $participant): int => (int) $participant['account_id'],
            $participants,
        );

        $accounts = Account::query()
            ->with('owner')
            ->whereIn('id', $accountIds)
            ->get()
            ->keyBy('id');

        $ledger = Ledger::query()->findOrFail($ledgerId);

        $incomes = [];
        foreach ($participants as $participant) {
            $accountId = (int) $participant['account_id'];
            $account = $accounts->get($accountId);

            if ($account === null || $account->owner_id === null) {
                throw new InvalidLedgerPostingException('Proportional split requires participant accounts with an owner.');
            }

            $incomes[$accountId] = $this->financialProfileService->shareableIncomeOn(
                $ledger,
                $account->owner,
                $date,
            );
        }

        $totalIncome = array_sum($incomes);

        if ($totalIncome <= 0) {
            throw new InvalidLedgerPostingException('Proportional split requires at least one participant with shareable income.');
        }

        $allocated = [];
        $runningTotal = 0;
        $lastIndex = count($participants) - 1;

        foreach ($participants as $index => $participant) {
            $accountId = (int) $participant['account_id'];

            if ($index === $lastIndex) {
                $participantAmount = $amount - $runningTotal;
            } else {
                $participantAmount = (int) floor(($incomes[$accountId] / $totalIncome) * $amount);
                $runningTotal += $participantAmount;
            }

            $allocated[] = [
                'account_id' => $accountId,
                'amount' => $participantAmount,
            ];
        }

        return $allocated;
    }
}
