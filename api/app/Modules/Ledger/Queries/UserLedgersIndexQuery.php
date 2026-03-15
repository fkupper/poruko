<?php

namespace App\Modules\Ledger\Queries;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserLedgersIndexQuery
{
    public function __construct(
        private readonly Ledger $ledgers,
    ) {}

    /**
     * @return Collection<int, Ledger>
     */
    public function execute(User $user): Collection
    {
        return $this->ledgers->newQuery()
            ->whereHas('users', fn ($query) => $query->whereKey($user->id))
            ->withCount('users')
            ->orderBy('id')
            ->get();
    }
}
