<?php

namespace App\Modules\Ledger\Queries;

use App\Models\Account;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\JoinClause;

class LedgerAccountIndexQuery
{
    /**
     * @return EloquentCollection<int, Account>
     */
    public function get(Ledger $ledger): EloquentCollection
    {
        return $ledger->accounts()
            ->leftJoin('ledger_user', function (JoinClause $join) use ($ledger): void {
                $join->on('ledger_user.user_id', '=', 'accounts.owner_id')
                    ->where('ledger_user.ledger_id', '=', $ledger->id);
            })
            ->selectRaw('accounts.*, COALESCE(accounts.id = ledger_user.main_personal_account_id, false) AS is_main')
            ->orderByDesc('accounts.created_at')
            ->get();
    }
}
