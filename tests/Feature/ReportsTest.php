<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetExpense;
use App\Models\AssetRental;
use App\Models\AssetType;
use App\Models\FxRate;
use App\Models\PortalSetting;
use App\Models\RentalPayment;
use App\Models\User;
use App\Support\Fx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Fx::flush();
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    public function test_fx_converts_to_base_currency(): void
    {
        PortalSetting::create(['key' => 'base_currency', 'value' => 'EUR']);
        FxRate::create(['currency' => 'USD', 'rate_to_base' => 0.5]);
        Fx::flush();

        $this->assertSame('EUR', Fx::base());
        $this->assertEqualsWithDelta(100.0, Fx::toBase(100, 'EUR'), 0.001);
        $this->assertEqualsWithDelta(50.0, Fx::toBase(100, 'USD'), 0.001);
        // Unknown currency passes through and is reported.
        $this->assertEqualsWithDelta(100.0, Fx::toBase(100, 'GBP'), 0.001);
        $this->assertContains('GBP', Fx::unknownCurrencies());
    }

    public function test_pnl_report_nets_income_minus_expenses_in_base(): void
    {
        PortalSetting::create(['key' => 'base_currency', 'value' => 'EUR']);
        FxRate::create(['currency' => 'USD', 'rate_to_base' => 0.5]);

        $type = AssetType::create(['name' => 'Apartment', 'is_active' => true, 'sort_order' => 1]);
        $asset = Asset::create([
            'name' => 'Flat', 'asset_type_id' => $type->id, 'type' => 'Apartment',
            'currency' => 'EUR', 'status' => 'Rented', 'ownership_percentage' => 100,
        ]);
        $rental = AssetRental::create([
            'asset_id' => $asset->id, 'year' => 2026, 'month' => 1,
            'agreement_start_date' => '2026-01-01', 'rent_type' => 'Long-term',
            'is_active' => true, 'amount' => 1000, 'currency' => 'EUR',
        ]);

        // 1000 EUR paid + 200 USD paid (=100 EUR) => income 1100 EUR
        RentalPayment::create([
            'asset_rental_id' => $rental->id, 'asset_id' => $asset->id,
            'due_date' => '2026-02-01', 'paid_date' => '2026-02-01', 'status' => 'paid',
            'amount' => 1000, 'currency' => 'EUR',
        ]);
        RentalPayment::create([
            'asset_rental_id' => $rental->id, 'asset_id' => $asset->id,
            'due_date' => '2026-03-01', 'paid_date' => '2026-03-01', 'status' => 'paid',
            'amount' => 200, 'currency' => 'USD',
        ]);
        // 300 EUR expenses
        AssetExpense::create([
            'asset_id' => $asset->id, 'spent_on' => '2026-04-01',
            'category' => 'Repairs', 'amount' => 300, 'currency' => 'EUR',
        ]);

        $user = $this->userWith('view_reports');

        $this->actingAs($user)
            ->get(route('reports.index', ['year' => 2026]))
            ->assertOk()
            ->assertSee('1,100.00')   // income in base
            ->assertSee('800.00');    // net = 1100 - 300

        // CSV export downloads with the expected rows
        $response = $this->actingAs($user)->get(route('reports.export', ['year' => 2026]));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('TOTAL', $csv);
        $this->assertStringContainsString('800.00', $csv);
    }

    public function test_reports_require_permission(): void
    {
        $this->get('/reports')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/reports')->assertForbidden();
    }
}
