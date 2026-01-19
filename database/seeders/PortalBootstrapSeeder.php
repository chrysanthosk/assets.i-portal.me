<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PortalBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        // Permissions registry (new format)
        $registry = config('portal_permissions.permissions', []);

        // 1) Ensure permissions exist
        foreach ($registry as $permName => $meta) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        // 2) Ensure default roles exist
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $userRole  = Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);

        // 3) Apply defaults:
        // Admin always gets ALL permissions
        $adminRole->syncPermissions(Permission::all());

        // User gets only the permissions where default_roles includes "User"
        $userDefaultPerms = [];
        foreach ($registry as $permName => $meta) {
            $defaultRoles = $meta['default_roles'] ?? [];
            if (in_array('User', $defaultRoles, true)) {
                $userDefaultPerms[] = $permName;
            }
        }
        if (empty($userDefaultPerms)) {
            $userDefaultPerms = ['view_dashboard'];
        }
        $userRole->syncPermissions($userDefaultPerms);

        // 4) For any OTHER existing roles, add new permissions that should be default for them (without removing existing)
        $allRoles = Role::all();
        foreach ($allRoles as $role) {
            if (in_array($role->name, ['Admin','User'], true)) {
                continue;
            }

            $rolePerms = $role->permissions->pluck('name')->all();

            foreach ($registry as $permName => $meta) {
                $defaultRoles = $meta['default_roles'] ?? [];
                if (in_array($role->name, $defaultRoles, true) && !in_array($permName, $rolePerms, true)) {
                    $rolePerms[] = $permName;
                }
            }

            $role->syncPermissions($rolePerms);
        }

        // 5) Default admin user
        $admin = User::firstOrCreate(
            ['username' => 'admin@example.com'],
            [
                'email' => 'admin@example.com',
                'name' => 'First',
                'surname' => 'Admin',
                'password' => Hash::make('ChangeMe123!'),
            ]
        );

        $admin->syncRoles(['Admin']);
    }
}
