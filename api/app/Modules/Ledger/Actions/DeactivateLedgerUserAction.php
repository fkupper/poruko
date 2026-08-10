<?php

namespace App\Modules\Ledger\Actions;

use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\User;

class DeactivateLedgerUserAction
{
    public function execute(Ledger $ledger, User $userToDeactivate, User $actingUser): void
    {
        if ($userToDeactivate->id === $actingUser->id) {
            abort(422, 'You cannot deactivate yourself.');
        }

        $ledgerUser = LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $userToDeactivate->id)
            ->firstOrFail();

        $ledgerUser->delete();

        // Option A: Close their financial profile so they stop accruing new space expenses
        \App\Models\FinancialProfile::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $userToDeactivate->id)
            ->whereNull('valid_to')
            ->update(['valid_to' => \Carbon\CarbonImmutable::now()->format('Y-m-d')]);
    }
}
