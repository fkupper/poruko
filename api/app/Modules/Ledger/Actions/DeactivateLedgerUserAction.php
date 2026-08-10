<?php

namespace App\Modules\Ledger\Actions;

use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\User;
use Carbon\CarbonImmutable;

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

        $pivotRole = $ledgerUser->role;

        $ledgerUser->delete();

        setPermissionsTeamId($ledger->id);
        $spatieRole = $this->mapPivotRoleToSpatieRole($pivotRole);

        if ($spatieRole !== null && $userToDeactivate->hasRole($spatieRole)) {
            $userToDeactivate->removeRole($spatieRole);
        }

        FinancialProfile::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $userToDeactivate->id)
            ->whereNull('valid_to')
            ->update(['valid_to' => CarbonImmutable::now()->format('Y-m-d')]);
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
