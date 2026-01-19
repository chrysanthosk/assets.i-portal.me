<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PortalBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        // Clear Spatie permission cache to avoid stale permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Read registry
        $registry = config('portal_permissions.permissions', []);

        // 1) Ensure permissions exist
        foreach ($registry as $permName => $meta) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        // 2) Ensure default roles exist
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $userRole  = Role::firstOrCreate(['name' => 'User',  'guard_name' => 'web']);

        // 3) Assign default permissions to roles based on registry
        //    (This avoids "Admin gets everything blindly" and makes future changes predictable.)
        foreach ($registry as $permName => $meta) {
            $defaultRoles = $meta['default_roles'] ?? [];

            foreach ($defaultRoles as $roleName) {
                $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
                $role->givePermissionTo($permName);
            }
        }

        // 4) Safety: ensure Admin always has all permissions that exist in DB
        //    (So new permissions show up immediately for Admin, even if someone forgets registry defaults.)
        $adminRole->syncPermissions(Permission::all());

        // 5) Safety: ensure User always has dashboard at minimum
        $userRole->givePermissionTo('view_dashboard');

        // 6) Default admin user
        $admin = User::firstOrCreate(
            ['username' => 'admin@example.com'],
            [
                'email' => 'admin@example.com',
                'name' => 'First',
                'surname' => 'Admin',
                'password' => Hash::make('ChangeMe123!'),
            ]
        );

        // Ensure the admin is Admin
        $admin->syncRoles(['Admin']);

        // Clear cache again after changes
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
