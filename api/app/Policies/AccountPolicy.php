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

    /**
     * Space/shared accounts (no owner) may be used by any ledger member.
     * Personally owned accounts may be used only by their owner.
     */
    public function useAsSource(User $user, Account $account): bool
    {
        if (!$this->view($user, $account)) {
            return false;
        }

        return $account->owner_id === null || $account->owner_id === $user->id;
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
