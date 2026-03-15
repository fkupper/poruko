<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\PostingDirection;
use App\Enums\TransactionSplitRule;
use App\Models\Account;
use App\Models\Posting;
use App\Models\Transaction;
use App\Modules\Ledger\Data\PostManualTransactionData;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class PostManualTransactionAction
{
    public function execute(PostManualTransactionData $payload): Transaction
    {
        if ($payload->amount <= 0) {
            throw new InvalidLedgerPostingException('Amount must be greater than zero.');
        }

        $this->assertAccountsBelongToLedger(
            $payload->ledgerId,
            $payload->creditAccountId,
            $payload->debitAccountId,
        );

        $this->validateParticipants($payload->splitRule, $payload->participants);

        return DB::transaction(function () use ($payload): Transaction {
            $transaction = Transaction::query()->create([
                'ledger_id' => $payload->ledgerId,
                'credit_account_id' => $payload->creditAccountId,
                'debit_account_id' => $payload->debitAccountId,
                'amount' => $payload->amount,
                'type' => $payload->type,
                'split_rule' => $payload->splitRule,
                'participants' => $payload->participants,
                'description' => $payload->description,
                'date' => $payload->date,
            ]);

            $now = Carbon::now()->toDateTimeString();
            Posting::query()->insert([
                [
                    'transaction_id' => $transaction->id,
                    'account_id' => $payload->creditAccountId,
                    'amount' => $payload->amount,
                    'direction' => PostingDirection::Credit->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'transaction_id' => $transaction->id,
                    'account_id' => $payload->debitAccountId,
                    'amount' => $payload->amount,
                    'direction' => PostingDirection::Debit->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            return $transaction->load(['creditAccount', 'debitAccount', 'postings']);
        });
    }

    private function assertAccountsBelongToLedger(int $ledgerId, int $creditAccountId, int $debitAccountId): void
    {
        $accountIds = collect([$creditAccountId, $debitAccountId])->unique()->values();

        $existingCount = Account::query()
            ->where('ledger_id', $ledgerId)
            ->whereIn('id', $accountIds)
            ->count();

        if ($existingCount !== $accountIds->count()) {
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
            foreach ($participants as $participant) {
                if (!isset($participant['share']) || (int) $participant['share'] <= 0) {
                    throw new InvalidLedgerPostingException('Manual split requires share > 0 on all participants.');
                }
            }
        }
    }
}
