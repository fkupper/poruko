<?php

namespace App\Policies;

use App\Models\Ledger;
use App\Models\PendingTransaction;
use App\Models\User;

class PendingTransactionPolicy
{
    public function viewAny(User $user, Ledger $ledger): bool
    {
        return $ledger->users()->whereKey($user->id)->exists();
    }

    public function view(User $user, PendingTransaction $pendingTransaction): bool
    {
        return $user->ledgers()->whereKey($pendingTransaction->ledger_id)->exists();
    }

    public function approve(User $user, PendingTransaction $pendingTransaction): bool
    {
        return $this->view($user, $pendingTransaction);
    }

    public function reject(User $user, PendingTransaction $pendingTransaction): bool
    {
        return $this->view($user, $pendingTransaction);
    }

    public function update(User $user, PendingTransaction $pendingTransaction): bool
    {
        return $this->view($user, $pendingTransaction);
    }
}
