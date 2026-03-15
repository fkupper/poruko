<?php

namespace App\Policies;

use App\Models\Ledger;
use App\Models\User;

class FinancialProfilePolicy
{
    /**
     * A user can view another member's profile if both are in the same ledger.
     */
    public function view(User $authUser, Ledger $ledger, User $targetUser): bool
    {
        $ids = array_unique([$authUser->id, $targetUser->id]);

        return $ledger->users()->whereIn('users.id', $ids)->count() === count($ids);
    }

    /**
     * A user can only update their own financial profile within the ledger.
     */
    public function update(User $authUser, Ledger $ledger, User $targetUser): bool
    {
        return $authUser->id === $targetUser->id
            && $ledger->users()->whereKey($authUser->id)->exists();
    }
}
