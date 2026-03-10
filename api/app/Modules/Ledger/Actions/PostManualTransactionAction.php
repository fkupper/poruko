<?php

namespace App\Modules\Ledger\Actions;

use App\Models\Account;
use App\Models\Posting;
use App\Models\Transaction;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;
use Illuminate\Support\Facades\DB;

class PostManualTransactionAction
{
    /**
     * @param array{
     *   ledger_id:int,
     *   payer_account_id:int,
     *   amount:int,
     *   split_rule:string,
     *   participants:array<int, array{account_id:int, amount?:int}>,
     *   description?:string|null,
     *   date:string,
     *   type?:string
     * } $payload
     */
    public function execute(array $payload): Transaction
    {
        $ledgerId = $payload['ledger_id'];
        $payerAccountId = $payload['payer_account_id'];
        $amount = $payload['amount'];
        $splitRule = $payload['split_rule'];
        $participants = $payload['participants'];
        $date = $payload['date'];
        $description = $payload['description'] ?? null;
        $type = $payload['type'] ?? 'manual';

        if ($amount <= 0) {
            throw new InvalidLedgerPostingException('Amount must be greater than zero.');
        }

        $this->assertAccountsBelongToLedger(
            $ledgerId,
            $payerAccountId,
            array_map(static fn (array $participant): int => (int) $participant['account_id'], $participants),
        );

        $allocatedParticipants = $this->allocateParticipants($amount, $splitRule, $participants);

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
                    'direction' => 'credit',
                ],
            ];

            foreach ($allocatedParticipants as $participant) {
                $postings[] = [
                    'transaction_id' => $transaction->id,
                    'account_id' => (int) $participant['account_id'],
                    'amount' => (int) $participant['amount'],
                    'direction' => 'debit',
                ];
            }

            Posting::query()->insert($postings);

            $sumDebits = (int) collect($postings)
                ->where('direction', 'debit')
                ->sum('amount');
            $sumCredits = (int) collect($postings)
                ->where('direction', 'credit')
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
    private function allocateParticipants(int $amount, string $splitRule, array $participants): array
    {
        if (count($participants) === 0) {
            throw new InvalidLedgerPostingException('At least one participant is required.');
        }

        if ($splitRule === 'equal') {
            return $this->allocateEqualParticipants($amount, $participants);
        }

        if ($splitRule === 'individual') {
            return $this->allocateIndividualParticipants($amount, $participants);
        }

        throw new InvalidLedgerPostingException('Unsupported split rule for manual transaction.');
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
}
