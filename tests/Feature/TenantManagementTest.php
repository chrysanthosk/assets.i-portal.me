<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetRental;
use App\Models\AssetType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TenantManagementTest extends TestCase
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

    public function test_guests_are_redirected(): void
    {
        $this->get('/tenants')->assertRedirect('/login');
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get('/tenants')->assertForbidden();
    }

    public function test_user_with_permission_can_create_and_list_tenants(): void
    {
        $user = $this->userWith('manage_tenants');

        $this->actingAs($user)->get('/tenants')->assertOk();

        $this->actingAs($user)->post('/tenants', [
            'name' => 'Jane Tenant',
            'email' => 'jane@example.com',
            'phone' => '12345',
        ])->assertRedirect();

        $this->assertDatabaseHas('tenants', ['name' => 'Jane Tenant', 'email' => 'jane@example.com']);
    }

    public function test_linking_a_tenant_to_a_rental_syncs_the_name(): void
    {
        $user = $this->userWith('manage_asset_rentals');
        $type = AssetType::create(['name' => 'Apartment', 'is_active' => true, 'sort_order' => 1]);
        $asset = Asset::create([
            'name' => 'Flat', 'asset_type_id' => $type->id, 'type' => 'Apartment',
            'currency' => 'EUR', 'status' => 'Rented', 'ownership_percentage' => 100,
        ]);
        $tenant = Tenant::create(['name' => 'Linked Tenant']);

        $this->actingAs($user)->post(route('assets.rentals.storeOrUpdate'), [
            'asset_id' => $asset->id,
            'tenant_id' => $tenant->id,
            'agreement_start_date' => '2026-01-01',
            'rent_type' => 'Long-term',
            'is_active' => 1,
            'amount' => 1000,
            'currency' => 'EUR',
        ])->assertRedirect();

        $rental = AssetRental::first();
        $this->assertSame($tenant->id, $rental->tenant_id);
        $this->assertSame('Linked Tenant', $rental->tenant_name);
    }
}
