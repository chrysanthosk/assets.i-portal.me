<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetExpense;
use App\Models\AssetType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssetExpenseTest extends TestCase
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
        $this->get('/expenses')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/expenses')->assertForbidden();
    }

    public function test_user_with_permission_can_record_an_expense(): void
    {
        $user = $this->userWith('manage_asset_expenses');
        $asset = $this->makeAsset();

        $this->actingAs($user)->get('/expenses')->assertOk();

        $this->actingAs($user)->post(route('expenses.store'), [
            'asset_id' => $asset->id,
            'spent_on' => '2026-03-01',
            'category' => 'Maintenance',
            'amount' => 250.50,
            'currency' => 'EUR',
            'vendor' => 'Acme Repairs',
        ])->assertRedirect();

        $this->assertDatabaseHas('asset_expenses', [
            'asset_id' => $asset->id,
            'category' => 'Maintenance',
            'vendor' => 'Acme Repairs',
        ]);
    }

    public function test_invalid_category_is_rejected(): void
    {
        $user = $this->userWith('manage_asset_expenses');
        $asset = $this->makeAsset();

        $this->actingAs($user)->post(route('expenses.store'), [
            'asset_id' => $asset->id,
            'spent_on' => '2026-03-01',
            'category' => 'Bogus',
            'amount' => 10,
            'currency' => 'EUR',
        ])->assertSessionHasErrors('category');

        $this->assertSame(0, AssetExpense::count());
    }
}
