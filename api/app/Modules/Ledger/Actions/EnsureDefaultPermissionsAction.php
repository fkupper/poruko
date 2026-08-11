<?php

namespace App\Modules\Ledger\Actions;

use Database\Seeders\PermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ensures Admin/Member roles and base permissions exist.
 * Fresh installs only run migrations; Spatie roles are not created by schema alone.
 */
final readonly class EnsureDefaultPermissionsAction
{
    public function execute(): void
    {
        // Idempotent: PermissionsSeeder uses findOrCreate (team-aware when teams are enabled).
        app(PermissionsSeeder::class)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
