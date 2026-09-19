<?php

namespace App\Modules\Ledger\Queries;

use App\Enums\PendingTransactionStatus;
use App\Models\Ledger;
use App\Models\PendingTransaction;
use Illuminate\Database\Eloquent\Collection;

class PendingTransactionIndexQuery
{
    /**
     * @return Collection<int, PendingTransaction>
     */
    public function execute(Ledger $ledger): Collection
    {
        return PendingTransaction::query()
            ->where('ledger_id', $ledger->id)
            ->where('status', PendingTransactionStatus::Pending->value)
            ->with(['proposer', 'payerAccount', 'destinationAccount'])
            ->oldest()
            ->get();
    }
}
