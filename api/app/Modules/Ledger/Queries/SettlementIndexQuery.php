<?php

namespace App\Modules\Ledger\Queries;

use App\Enums\TransactionType;
use App\Models\Ledger;
use App\Models\Settlement;
use Illuminate\Database\Eloquent\Collection;

class SettlementIndexQuery
{
    /**
     * @return Collection<int, Settlement>
     */
    public function execute(Ledger $ledger, int $limit = 12): Collection
    {
        return Settlement::query()
            ->where('ledger_id', $ledger->id)
            ->with(['transactions' => function ($query): void {
                $query->where('type', TransactionType::Settlement->value)
                    ->with(['creditAccount', 'debitAccount'])
                    ->orderBy('date')
                    ->orderBy('id');
            }])
            ->latest('period_end')
            ->limit($limit)
            ->get();
    }
}
