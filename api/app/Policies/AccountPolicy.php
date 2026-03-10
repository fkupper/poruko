<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->ledgers()->exists();
    }

    public function view(User $user, Account $account): bool
    {
        return $account->ledger->users()->whereKey($user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->ledgers()->exists();
    }

    public function update(User $user, Account $account): bool
    {
        return $this->view($user, $account);
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->view($user, $account);
    }
}
