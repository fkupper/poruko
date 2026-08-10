<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

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
            \Spatie\Permission\Models\Permission::findOrCreate($permission);
        }

        $admin = \Spatie\Permission\Models\Role::findOrCreate('Admin');
        $admin->syncPermissions($permissions);

        $member = \Spatie\Permission\Models\Role::findOrCreate('Member');
        $member->syncPermissions([]);
    }
}
