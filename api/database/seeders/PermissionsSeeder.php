<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    /**
     * Spatie roles/permissions use the web guard. During Sanctum API requests
     * auth.defaults.guard may be "sanctum", so findOrCreate must pin guard explicitly.
     */
    private const GUARD = 'web';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'users',
            'settings',
            'accounts',
            'transactions',
            'recurring',
            'settlements',
            'ai_ingestion',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, self::GUARD);
        }

        $admin = Role::findOrCreate('Admin', self::GUARD);
        $admin->syncPermissions($permissions);

        $member = Role::findOrCreate('Member', self::GUARD);
        $member->syncPermissions([]);
    }
}
