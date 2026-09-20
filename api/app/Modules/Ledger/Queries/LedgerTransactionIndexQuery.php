<?php

namespace App\Modules\Ledger\Queries;

use App\Models\Ledger;
use App\Models\Transaction;
use App\Modules\Ledger\Data\TransactionIndexFiltersData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LedgerTransactionIndexQuery
{
    public function __construct(
        private readonly Transaction $transaction,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function execute(Ledger $ledger, TransactionIndexFiltersData $filters): LengthAwarePaginator
    {
        $allAccountIds = array_filter(array_unique(array_merge(
            $filters->accountId !== null ? [$filters->accountId] : [],
            $filters->accountIds ?? [],
        )));

        return $this->transaction->newQuery()
            ->with(['payerAccount', 'destinationAccount', 'postings.account'])
            ->forLedger($ledger->id)
            ->betweenDates($filters->fromDate, $filters->toDate)
            ->when(
                !empty($allAccountIds),
                fn ($query) => $query->where(function ($subQuery) use ($allAccountIds) {
                    $subQuery->whereIn('payer_account_id', $allAccountIds)
                        ->orWhereIn('destination_account_id', $allAccountIds)
                        ->orWhereHas('postings', function ($q) use ($allAccountIds) {
                            $q->whereIn('account_id', $allAccountIds);
                        });
                }),
            )
            ->when(
                !empty($filters->creatorUserIds),
                fn ($query) => $query->whereHas('payerAccount', function ($q) use ($filters) {
                    $q->whereIn('owner_id', $filters->creatorUserIds);
                }),
            )
            ->when(
                !empty($filters->splitRules),
                fn ($query) => $query->whereIn('split_rule', $filters->splitRules),
            )
            ->when(
                !empty($filters->types),
                fn ($query) => $query->whereIn('type', $filters->types),
            )
            ->when(
                $filters->settlementId !== null,
                fn ($query) => $query->where('settlement_id', $filters->settlementId),
            )
            ->latest('date')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }
}
