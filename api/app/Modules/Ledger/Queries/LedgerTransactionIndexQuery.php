<?php

namespace App\Modules\Ledger\Queries;

use App\Models\Ledger;
use App\Models\Transaction;
use App\Modules\Ledger\Data\TransactionIndexFiltersData;
use Illuminate\Database\Eloquent\Collection;

class LedgerTransactionIndexQuery
{
    public function __construct(
        private readonly Transaction $transactions,
    ) {}

    /**
     * @return Collection<int, Transaction>
     */
    public function execute(Ledger $ledger, TransactionIndexFiltersData $filters): Collection
    {
        return $this->transactions->newQuery()
            ->with(['payerAccount', 'postings'])
            ->forLedger($ledger->id)
            ->betweenDates($filters->fromDate, $filters->toDate)
            ->when(
                $filters->accountId !== null,
                fn ($query) => $query->where(function ($subQuery) use ($filters) {
                    $subQuery->where('payer_account_id', $filters->accountId)
                        ->orWhereJsonContains('participants', [['account_id' => $filters->accountId]]);
                }),
            )
            ->latest('date')
            ->get();
    }
}
