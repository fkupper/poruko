<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->ledgers()->exists();
    }

    public function view(User $user, Account $account): bool
    {
        return $user->ledgers()->whereKey($account->ledger_id)->exists();
    }

    public function create(User $user, Ledger $ledger): bool
    {
        return $ledger->users()->whereKey($user->id)->exists();
    }

    public function update(User $user, Account $account): bool
    {
        if ($user->can('accounts')) {
            return true;
        }

        if ($account->owner_id === null || $account->owner_id !== $user->id) {
            return false;
        }

        return $this->view($user, $account);
    }

    public function delete(User $user, Account $account): bool
    {
        if ($user->can('accounts')) {
            return true;
        }

        if ($account->owner_id === null || $account->owner_id !== $user->id) {
            return false;
        }

        return $this->view($user, $account);
    }
}
