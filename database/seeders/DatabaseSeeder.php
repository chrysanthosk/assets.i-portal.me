<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 1) Sync permissions + roles from config
        $this->call([
            PortalPermissionsSeeder::class,
            AssetTypeSeeder::class,
            OwnerEntitySeeder::class,
        ]);

        // 2) Create initial user (only if not exists)
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'username' => 'admin@example.com',
                'password' => bcrypt('admin1234'), // change later
            ]
        );

        // 3) Ensure Admin role exists + assign it to that user
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        if (! $user->hasRole('Admin')) {
            $user->assignRole($adminRole);
        }
    }
}
