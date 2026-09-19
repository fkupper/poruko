<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\PendingTransactionStatus;
use App\Models\Ledger;
use App\Models\PendingTransaction;
use App\Models\User;
use App\Modules\Ledger\Exceptions\PendingTransactionReviewException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class RejectPendingTransactionsAction
{
    /**
     * @param list<int> $pendingTransactionIds
     * @return Collection<int, PendingTransaction>
     */
    public function execute(
        Ledger $ledger,
        User $reviewer,
        array $pendingTransactionIds,
        ?string $reason = null,
    ): Collection {
        if (!$ledger->users()->whereKey($reviewer->id)->exists()) {
            throw new PendingTransactionReviewException('Only active ledger members may reject transactions.');
        }

        return DB::transaction(function () use ($ledger, $reviewer, $pendingTransactionIds, $reason): Collection {
            $pendingTransactions = PendingTransaction::query()
                ->where('ledger_id', $ledger->id)
                ->whereIn('id', $pendingTransactionIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($pendingTransactions->count() !== count($pendingTransactionIds)) {
                throw new PendingTransactionReviewException('One or more pending transactions do not belong to this ledger.');
            }

            return collect($pendingTransactionIds)
                ->map(function (int $pendingTransactionId) use ($pendingTransactions, $reviewer, $reason): PendingTransaction {
                    /** @var PendingTransaction $pendingTransaction */
                    $pendingTransaction = $pendingTransactions->get($pendingTransactionId);

                    if ($pendingTransaction->status === PendingTransactionStatus::Approved) {
                        throw new PendingTransactionReviewException('Approved transactions cannot be rejected.');
                    }

                    if ($pendingTransaction->status === PendingTransactionStatus::Pending) {
                        $pendingTransaction->update([
                            'status' => PendingTransactionStatus::Rejected,
                            'reviewed_by_user_id' => $reviewer->id,
                            'reviewed_at' => now(),
                            'rejection_reason' => $reason,
                        ]);
                    }

                    return $pendingTransaction->refresh();
                })
                ->values();
        });
    }
}
