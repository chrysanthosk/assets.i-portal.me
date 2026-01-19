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
        // 1) Ensure permissions exist
        foreach (config('portal_permissions', []) as $key => $label) {
            Permission::firstOrCreate(['name' => $key]);
        }

        // 2) Roles (permission sets)
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $userRole  = Role::firstOrCreate(['name' => 'User']);

        // Admin gets all permissions
        $adminRole->syncPermissions(Permission::all());

        // User gets dashboard only
        $userRole->syncPermissions(['view_dashboard']);

        // 3) Default admin user
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
