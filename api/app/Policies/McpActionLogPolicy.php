<?php

namespace App\Policies;

use App\Models\Ledger;
use App\Models\McpActionLog;
use App\Models\User;

class McpActionLogPolicy
{
    public function viewAny(User $user, Ledger $ledger): bool
    {
        return $ledger->users()->whereKey($user->id)->exists();
    }

    public function view(User $user, McpActionLog $log): bool
    {
        if ($log->user_id === $user->id) {
            return true;
        }

        if ($log->ledger_id === null) {
            return false;
        }

        $ledger = $log->ledger;

        return $ledger !== null
            && $ledger->users()->whereKey($user->id)->exists()
            && $user->can('settings');
    }
}
