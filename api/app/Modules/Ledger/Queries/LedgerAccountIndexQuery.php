<?php

namespace App\Modules\Ledger\Queries;

use App\Enums\AccountType;
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
            ->whereNotIn('type', AccountType::internalTypes())
            ->withSum(['postings as total_credits' => function ($q) {
                $q->where('direction', 'credit');
            },
            ], 'amount')
            ->withSum(['postings as total_debits' => function ($q) {
                $q->where('direction', 'debit');
            },
            ], 'amount')
            ->leftJoin('ledger_user', function (JoinClause $join) use ($ledger): void {
                $join->on('ledger_user.user_id', '=', 'accounts.owner_id')
                    ->where('ledger_user.ledger_id', '=', $ledger->id);
            })
            ->selectRaw('accounts.*, COALESCE(accounts.id = ledger_user.main_personal_account_id, false) AS is_main, (accounts.owner_id IS NULL OR ledger_user.deleted_at IS NULL) AS owner_is_active')
            ->orderByDesc('accounts.created_at')
            ->get();
    }
}
