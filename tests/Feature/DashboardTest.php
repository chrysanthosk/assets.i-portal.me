<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    public function test_dashboard_requires_permission(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertForbidden();
    }

    public function test_dashboard_renders_with_asset_metrics(): void
    {
        $user = $this->userWith('view_dashboard');
        $type = AssetType::create(['name' => 'House', 'is_active' => true, 'sort_order' => 1]);
        Asset::create([
            'name' => 'Villa', 'asset_type_id' => $type->id, 'type' => 'House',
            'currency' => 'EUR', 'status' => 'Rented', 'ownership_percentage' => 100,
            'purchase_price' => 250000,
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Outstanding')   // arrears widget
            ->assertSee('Documents');    // expiry widget
    }
}
