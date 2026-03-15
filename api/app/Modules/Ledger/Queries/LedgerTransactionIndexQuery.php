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
        return $this->transaction->newQuery()
            ->with(['creditAccount', 'debitAccount', 'postings'])
            ->forLedger($ledger->id)
            ->betweenDates($filters->fromDate, $filters->toDate)
            ->when(
                $filters->accountId !== null,
                fn ($query) => $query->where(function ($subQuery) use ($filters) {
                    $subQuery->where('credit_account_id', $filters->accountId)
                        ->orWhere('debit_account_id', $filters->accountId);
                }),
            )
            ->latest('date')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }
}
