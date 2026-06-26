<?php

namespace Tests\Feature;

use App\Models\AssetType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssetManagementTest extends TestCase
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

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/assets')->assertRedirect('/login');
    }

    public function test_authenticated_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/assets')->assertForbidden();
    }

    public function test_user_with_permission_can_list_and_create_assets(): void
    {
        $user = $this->userWith('manage_assets');
        $type = AssetType::create(['name' => 'Apartment', 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($user)->get('/assets')->assertOk();

        $response = $this->actingAs($user)->post('/assets', [
            'name' => 'Flat 1',
            'asset_type_id' => $type->id,
            'currency' => 'EUR',
            'status' => 'Vacant',
            'city' => 'Limassol',
        ]);

        $response->assertRedirect(route('assets.index'));
        $this->assertDatabaseHas('assets', ['name' => 'Flat 1', 'city' => 'Limassol']);
    }

    public function test_asset_search_by_city_does_not_crash(): void
    {
        // Regression: city/country/postcode were referenced in search + $fillable
        // before any migration created the columns, which crashed asset search.
        $user = $this->userWith('manage_assets');
        $type = AssetType::create(['name' => 'House', 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($user)->post('/assets', [
            'name' => 'Seaside Villa',
            'asset_type_id' => $type->id,
            'currency' => 'EUR',
            'status' => 'Vacant',
            'city' => 'Paphos',
        ]);

        $this->actingAs($user)
            ->get('/assets?q=Paphos')
            ->assertOk()
            ->assertSee('Seaside Villa');
    }
}
