<?php

namespace App\Policies;

use App\Models\Ledger;
use App\Models\RecurringTransaction;
use App\Models\User;

class RecurringTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->ledgers()->exists();
    }

    public function view(User $user, RecurringTransaction $recurringTransaction): bool
    {
        return $user->ledgers()->whereKey($recurringTransaction->ledger_id)->exists();
    }

    public function create(User $user, Ledger $ledger): bool
    {
        return $ledger->users()->whereKey($user->id)->exists();
    }

    public function update(User $user, RecurringTransaction $recurringTransaction): bool
    {
        return $user->ledgers()->whereKey($recurringTransaction->ledger_id)->exists();
    }

    public function delete(User $user, RecurringTransaction $recurringTransaction): bool
    {
        return $user->ledgers()->whereKey($recurringTransaction->ledger_id)->exists();
    }
}
