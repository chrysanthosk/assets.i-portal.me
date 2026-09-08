<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetExpense;
use App\Models\AssetRental;
use App\Models\AssetType;
use App\Models\RentalPayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssetShowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_page_shows_figures_and_tabs(): void
    {
        $user = User::factory()->create();
        foreach (['manage_assets', 'manage_asset_rentals', 'manage_rental_payments', 'manage_asset_expenses'] as $p) {
            Permission::findOrCreate($p, 'web');
            $user->givePermissionTo($p);
        }

        $type = AssetType::create(['name' => 'Apartment', 'is_active' => true, 'sort_order' => 1]);
        $asset = Asset::create([
            'name' => 'Sea View 3', 'asset_type_id' => $type->id, 'currency' => 'EUR', 'status' => 'Rented (long-term)',
            'ownership_percentage' => 100, 'purchase_price' => 150000, 'title_deed' => true, 'title_deed_number' => '0/8443',
            'title_deed_data' => ['registration_number' => '0/8443', 'district' => 'Paphos', 'sheet' => '51', 'plan' => '04', 'section' => '0', 'plot' => 'EPI 1868'],
        ]);
        $tenant = Tenant::create(['name' => 'Maria K']);
        $rental = AssetRental::create([
            'asset_id' => $asset->id, 'tenant_id' => $tenant->id, 'agreement_start_date' => now()->subMonths(2)->toDateString(),
            'rent_type' => 'Long-term', 'is_active' => true, 'amount' => 850, 'currency' => 'EUR',
        ]);
        RentalPayment::create(['asset_rental_id' => $rental->id, 'asset_id' => $asset->id, 'due_date' => now()->subMonth(),
            'period' => now()->subMonth()->format('Y-m'), 'amount' => 850, 'currency' => 'EUR', 'status' => 'paid', 'paid_date' => now()->subMonth()]);
        RentalPayment::create(['asset_rental_id' => $rental->id, 'asset_id' => $asset->id, 'due_date' => now()->subDays(3),
            'period' => now()->format('Y-m'), 'amount' => 850, 'currency' => 'EUR']);
        AssetExpense::create(['asset_id' => $asset->id, 'spent_on' => now()->subDays(10), 'category' => AssetExpense::CATEGORIES[0],
            'amount' => 120, 'currency' => 'EUR', 'vendor' => 'Plumber Co']);

        $page = $this->actingAs($user)->get(route('assets.show', $asset))->assertOk();

        $page->assertSee('Sea View 3')
            ->assertSee('Monthly rent')->assertSee('EUR 850.00')->assertSee('Maria K')
            ->assertSee('Outstanding')
            ->assertSee('Overview')->assertSee('Documents')->assertSee('Agreements')->assertSee('Payments')->assertSee('Expenses')
            ->assertSee('51 / 04 / 0 / EPI 1868')
            ->assertSee('Plumber Co')
            ->assertSee('Overdue');
    }
}
