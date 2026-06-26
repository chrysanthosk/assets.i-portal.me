<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TwoFactorEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['two_factor_enabled' => false]);
        Role::findOrCreate('Admin', 'web');
        Permission::findOrCreate('view_dashboard', 'web');
        $user->assignRole('Admin');
        $user->givePermissionTo('view_dashboard');

        return $user;
    }

    public function test_admin_without_2fa_is_forced_to_profile_when_enforced(): void
    {
        config(['portal.require_2fa_for_admins' => true]);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('profile.edit'));
        // The profile page itself must remain reachable so they can enroll.
        $this->actingAs($admin)->get('/profile')->assertOk();
    }

    public function test_admin_without_2fa_is_allowed_when_not_enforced(): void
    {
        config(['portal.require_2fa_for_admins' => false]);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/dashboard')->assertOk();
    }

    public function test_non_admin_without_2fa_is_not_forced(): void
    {
        config(['portal.require_2fa_for_admins' => true]);

        $user = User::factory()->create(['two_factor_enabled' => false]);
        Permission::findOrCreate('view_dashboard', 'web');
        $user->givePermissionTo('view_dashboard');

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }
}
