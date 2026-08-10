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
        return $user->ledgers()->whereKey($transaction->ledger_id)->exists();
    }

    public function create(User $user, Ledger $ledger): bool
    {
        return $ledger->users()->whereKey($user->id)->exists();
    }

    public function update(User $user, Transaction $transaction): bool
    {
        if ($user->can('transactions')) {
            return true;
        }

        if ($transaction->payerAccount && $transaction->payerAccount->owner_id === $user->id) {
            return true;
        }

        foreach ($transaction->participants as $participant) {
            if (isset($participant['user_id']) && $participant['user_id'] === $user->id) {
                return true;
            }
        }

        return false;
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }
}
