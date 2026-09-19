<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\PendingTransactionStatus;
use App\Enums\TransactionType;
use App\Models\Ledger;
use App\Models\PendingTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Ledger\Data\PostManualTransactionData;
use App\Modules\Ledger\Exceptions\PendingTransactionReviewException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class ApprovePendingTransactionsAction
{
    public function __construct(
        private PostManualTransactionAction $postManualTransactionAction,
    ) {}

    /**
     * @param list<int> $pendingTransactionIds
     * @return Collection<int, Transaction>
     */
    public function execute(Ledger $ledger, User $reviewer, array $pendingTransactionIds): Collection
    {
        if (!$ledger->users()->whereKey($reviewer->id)->exists()) {
            throw new PendingTransactionReviewException('Only active ledger members may approve transactions.');
        }

        return DB::transaction(function () use ($ledger, $reviewer, $pendingTransactionIds): Collection {
            $pendingTransactions = PendingTransaction::query()
                ->where('ledger_id', $ledger->id)
                ->whereIn('id', $pendingTransactionIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($pendingTransactions->count() !== count($pendingTransactionIds)) {
                throw new PendingTransactionReviewException('One or more pending transactions do not belong to this ledger.');
            }

            $activeLedgerUserIds = array_map(
                'intval',
                $ledger->users()->pluck('users.id')->all(),
            );

            return collect($pendingTransactionIds)
                ->map(function (int $pendingTransactionId) use ($activeLedgerUserIds, $pendingTransactions, $reviewer): Transaction {
                    /** @var PendingTransaction $pendingTransaction */
                    $pendingTransaction = $pendingTransactions->get($pendingTransactionId);

                    if ($pendingTransaction->status === PendingTransactionStatus::Approved) {
                        if ($pendingTransaction->committed_transaction_id === null) {
                            throw new PendingTransactionReviewException('The approved transaction no longer has a committed ledger transaction.');
                        }

                        $committedTransaction = Transaction::query()->find($pendingTransaction->committed_transaction_id);

                        if (!$committedTransaction instanceof Transaction) {
                            throw new PendingTransactionReviewException('The approved transaction no longer has a committed ledger transaction.');
                        }

                        return $committedTransaction;
                    }

                    if ($pendingTransaction->status !== PendingTransactionStatus::Pending) {
                        throw new PendingTransactionReviewException('Rejected transactions cannot be approved.');
                    }

                    if (
                        $pendingTransaction->payer_account_id === null
                        || $pendingTransaction->destination_account_id === null
                        || $pendingTransaction->suggested_amount === null
                        || $pendingTransaction->suggested_split_rule === null
                        || $pendingTransaction->date === null
                    ) {
                        throw new PendingTransactionReviewException(
                            "Pending transaction {$pendingTransaction->id} is missing required posting details.",
                        );
                    }

                    $participantUserIds = array_values(array_unique(array_map(
                        static fn (array $participant): int => (int) ($participant['user_id'] ?? 0),
                        $pendingTransaction->suggested_participants ?? [],
                    )));

                    if (
                        in_array(0, $participantUserIds, true)
                        || array_diff($participantUserIds, $activeLedgerUserIds) !== []
                    ) {
                        throw new PendingTransactionReviewException(
                            "Pending transaction {$pendingTransaction->id} contains a participant who is not an active ledger member.",
                        );
                    }

                    $sourceMetadata = array_filter([
                        'pending_transaction_id' => $pendingTransaction->id,
                        'proposed_by_user_id' => $pendingTransaction->user_id,
                        'confidence' => $pendingTransaction->confidence,
                        'rationale' => $pendingTransaction->rationale,
                    ], static fn (mixed $value): bool => $value !== null);

                    $transaction = $this->postManualTransactionAction->execute(
                        new PostManualTransactionData(
                            ledgerId: $pendingTransaction->ledger_id,
                            payerAccountId: $pendingTransaction->payer_account_id,
                            destinationAccountId: $pendingTransaction->destination_account_id,
                            amount: $pendingTransaction->suggested_amount,
                            splitRule: $pendingTransaction->suggested_split_rule->value,
                            participants: $pendingTransaction->suggested_participants ?? [],
                            date: $pendingTransaction->date->toDateString(),
                            description: $pendingTransaction->suggested_description,
                            type: TransactionType::Manual->value,
                            source: $pendingTransaction->source->value,
                            sourceMetadata: $sourceMetadata,
                        ),
                    );

                    $pendingTransaction->update([
                        'status' => PendingTransactionStatus::Approved,
                        'reviewed_by_user_id' => $reviewer->id,
                        'reviewed_at' => now(),
                        'rejection_reason' => null,
                        'committed_transaction_id' => $transaction->id,
                    ]);

                    return $transaction;
                })
                ->values();
        });
    }
}
