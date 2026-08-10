<?php

namespace App\Modules\Ledger\Actions;

use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\User;

class RestoreLedgerUserAction
{
    public function execute(Ledger $ledger, User $userToRestore): void
    {
        $activeMembership = LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $userToRestore->id)
            ->first();

        if ($activeMembership !== null) {
            abort(422, 'User is already an active member of this ledger.');
        }

        $ledgerUser = LedgerUser::onlyTrashed()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $userToRestore->id)
            ->first();

        if ($ledgerUser === null) {
            abort(404, 'Deactivated membership not found.');
        }

        $ledgerUser->restore();

        setPermissionsTeamId($ledger->id);
        $spatieRole = $this->mapPivotRoleToSpatieRole($ledgerUser->role);

        if ($spatieRole !== null && !$userToRestore->hasRole($spatieRole)) {
            $userToRestore->assignRole($spatieRole);
        }
    }

    private function mapPivotRoleToSpatieRole(string $pivotRole): ?string
    {
        return match ($pivotRole) {
            'admin' => 'Admin',
            'member' => 'Member',
            default => null,
        };
    }
}
