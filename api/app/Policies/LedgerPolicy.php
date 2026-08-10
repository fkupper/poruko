<?php

namespace App\Policies;

use App\Models\Ledger;
use App\Models\User;

class LedgerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->ledgers()->exists();
    }

    public function view(User $user, Ledger $ledger): bool
    {
        return $ledger->users()->whereKey($user->id)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ledger $ledger): bool
    {
        return $ledger->users()->whereKey($user->id)->exists()
            && $user->can('settings');
    }

    public function manageSettlements(User $user, Ledger $ledger): bool
    {
        return $ledger->users()->whereKey($user->id)->exists()
            && $user->can('settlements');
    }

    public function delete(User $user, Ledger $ledger): bool
    {
        return $this->update($user, $ledger);
    }
}
