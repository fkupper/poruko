<?php

namespace App\Policies;

use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->ledgers()->exists();
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $transaction->ledger->users()->whereKey($user->id)->exists();
    }

    public function create(User $user, Ledger $ledger): bool
    {
        return $ledger->users()->whereKey($user->id)->exists();
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return false;
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return false;
    }
}
