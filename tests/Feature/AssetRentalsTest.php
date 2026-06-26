<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetRental;
use App\Models\AssetType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssetRentalsTest extends TestCase
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

    private function makeAsset(): Asset
    {
        $type = AssetType::create(['name' => 'Apartment', 'is_active' => true, 'sort_order' => 1]);

        return Asset::create([
            'name' => 'Flat', 'asset_type_id' => $type->id, 'type' => 'Apartment',
            'currency' => 'EUR', 'status' => 'Rented', 'ownership_percentage' => 100,
        ]);
    }

    public function test_permission_is_required(): void
    {
        $this->get('/assets/rentals')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/assets/rentals')->assertForbidden();
    }

    public function test_creating_an_agreement_derives_year_and_month(): void
    {
        $user = $this->userWith('manage_asset_rentals');
        $asset = $this->makeAsset();

        $this->actingAs($user)->post(route('assets.rentals.storeOrUpdate'), [
            'asset_id' => $asset->id,
            'agreement_start_date' => '2026-05-15',
            'rent_type' => 'Long-term',
            'is_active' => 1,
            'amount' => 1200,
            'currency' => 'EUR',
        ])->assertRedirect();

        $rental = AssetRental::first();
        $this->assertSame(2026, $rental->year);
        $this->assertSame(5, $rental->month);
        $this->assertTrue($rental->is_active);
    }

    public function test_an_agreement_can_be_deleted(): void
    {
        $user = $this->userWith('manage_asset_rentals');
        $asset = $this->makeAsset();
        $rental = AssetRental::create([
            'asset_id' => $asset->id, 'year' => 2026, 'month' => 1,
            'agreement_start_date' => '2026-01-01', 'rent_type' => 'Long-term',
            'is_active' => true, 'amount' => 1000, 'currency' => 'EUR',
        ]);

        $this->actingAs($user)->delete(route('assets.rentals.destroy', $rental))->assertRedirect();
        $this->assertModelMissing($rental);
    }
}
