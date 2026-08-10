<?php

namespace App\Modules\Ledger\Queries;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserLedgersIndexQuery
{
    /**
     * @return Collection<int, Ledger>
     */
    public function execute(User $user): Collection
    {
        return $user->ledgers()
            ->withCount('users')
            ->orderBy('id')
            ->get();
    }
}
