<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\AssetRental;
use App\Models\AssetType;
use App\Models\DeedImport;
use App\Models\PortalSetting;
use App\Models\RentalPayment;
use App\Models\User;
use App\Support\Agreements\AgreementExtractor;
use App\Support\Agreements\AgreementMapper;
use App\Support\Agreements\AgreementSchema;
use App\Support\RentSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InstallmentAgreementTest extends TestCase
{
    use RefreshDatabase;

    /** Terms as read from the Prengos Villa 24 contract (first contract year). */
    private array $terms = [
        'counterparty' => 'Z&X Holiday Villas', 'counterparty_type' => 'management_company',
        'property_reference' => 'Prengos Villa 24', 'property_address' => 'KALITHEAS 24 ESPRIT VILLAS No24',
        'contract_start' => '2024-04-01', 'contract_end' => '2027-03-31', 'signed_on' => '2023-12-07', 'auto_renews' => 'yes',
        'currency' => 'EUR', 'schedule_type' => 'installments', 'monthly_amount' => null, 'annual_amount' => 18000.0, 'amounts_exclude_vat' => 'yes',
        'installments' => [
            ['date' => '2024-04-15', 'amount' => 2700.0, 'percent' => 15.0, 'label' => '15 % of guarantee'],
            ['date' => '2024-05-31', 'amount' => 2700.0, 'percent' => 15.0, 'label' => '15 % of guarantee'],
            ['date' => '2024-06-30', 'amount' => 2700.0, 'percent' => 15.0, 'label' => '15 % of guarantee'],
            ['date' => '2024-08-30', 'amount' => 5400.0, 'percent' => 30.0, 'label' => '30 % of guarantee'],
            ['date' => '2024-10-31', 'amount' => 3600.0, 'percent' => 20.0, 'label' => '20 % of guarantee'],
            ['date' => '2024-12-31', 'amount' => 0.0, 'percent' => null, 'label' => 'Year-end commission'],
            ['date' => '2025-03-31', 'amount' => 900.0, 'percent' => 5.0, 'label' => '5 % of guarantee'],
        ],
        'commission_terms' => '50 % of revenue above EUR 32,000 + VAT per year.', 'obligations' => 'Public liability insurance; tourist licence.',
        'notes' => null, 'warnings' => [],
    ];

    private function asset(string $name = 'Prengos 24'): Asset
    {
        $type = AssetType::firstOrCreate(['name' => 'House'], ['is_active' => true, 'sort_order' => 1]);

        return Asset::create(['name' => $name, 'asset_type_id' => $type->id, 'currency' => 'EUR', 'status' => 'Airbnb/Short-term', 'ownership_percentage' => 100]);
    }

    private function user(): User
    {
        $u = User::factory()->create();
        Permission::findOrCreate('manage_asset_rentals', 'web');
        $u->givePermissionTo('manage_asset_rentals');

        return $u;
    }

    public function test_installments_generate_on_their_dates_every_year_and_repeat(): void
    {
        $asset = $this->asset();
        $rental = AssetRental::create([
            'asset_id' => $asset->id, 'tenant_name' => 'Z&X', 'agreement_start_date' => '2024-04-01', 'agreement_end_date' => '2027-03-31',
            'rent_type' => 'Other', 'is_active' => true, 'currency' => 'EUR',
            'payment_schedule' => 'installments', 'amount' => 18000,
            'installments' => AgreementMapper::toRentalAttributes($this->terms)['installments'],
        ]);

        // April 2026: only the 15 April instalment, with its label, for the 2026 year
        $this->assertSame(1, RentSchedule::generateDue(Carbon::parse('2026-04-03')));
        $p = RentalPayment::sole();
        $this->assertSame('2026-04-15', $p->due_date->toDateString());
        $this->assertSame('2700.00', $p->amount);
        $this->assertSame('15 % of guarantee', $p->label);
        $this->assertSame('2026-04#2', $p->period); // calendar order: 31 Mar is #1
        $this->assertStringContainsString('15 Apr 2026', $p->periodLabel());

        // Idempotent; nothing in a month without instalments; two years later still repeats
        $this->assertSame(0, RentSchedule::generateDue(Carbon::parse('2026-04-20')));
        $this->assertSame(0, RentSchedule::generateDue(Carbon::parse('2026-07-01')));
        $this->assertSame(1, RentSchedule::generateDue(Carbon::parse('2026-12-01')));
        $this->assertSame('0.00', RentalPayment::where('period', '2026-12#7')->sole()->amount); // commission, amount set later

        // Beyond the end date: nothing
        $this->assertSame(0, RentSchedule::generateDue(Carbon::parse('2027-04-02')));
        // Monthly equivalent for dashboards
        $this->assertSame(1500.0, $rental->fresh()->monthlyEquivalent());
    }

    public function test_monthly_rent_paid_in_arrears_is_due_the_following_month(): void
    {
        $asset = $this->asset('Dubai');
        AssetRental::create(['asset_id' => $asset->id, 'tenant_name' => 'HiGuests', 'agreement_start_date' => '2026-01-01', 'rent_type' => 'Airbnb',
            'is_active' => true, 'currency' => 'AED', 'amount' => 4000, 'paid_in_arrears' => true]);
        PortalSetting::set(RentSchedule::SETTING_DUE_DAY, '10');

        $this->assertSame(1, RentSchedule::generateDue(Carbon::parse('2026-08-01')));
        $p = RentalPayment::sole();
        $this->assertSame('2026-08', $p->period);
        $this->assertSame('2026-09-10', $p->due_date->toDateString());
        $this->assertSame('August 2026', $p->periodLabel());
    }

    public function test_agreement_form_accepts_an_installment_schedule(): void
    {
        $asset = $this->asset();
        $this->actingAs($this->user())->post(route('assets.rentals.storeOrUpdate'), [
            'asset_id' => $asset->id, 'tenant_name' => 'Z&X', 'agreement_start_date' => '2024-04-01', 'rent_type' => 'Other', 'is_active' => 1,
            'currency' => 'EUR', 'payment_schedule' => 'installments',
            'installments' => [['day' => 15, 'month' => 4, 'amount' => 2700, 'label' => 'a'], ['day' => 31, 'month' => 3, 'amount' => 900, 'label' => 'b']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $r = AssetRental::sole();
        $this->assertTrue($r->isInstallments());
        $this->assertSame('3600.00', $r->amount);
        $this->assertSame([3, 4], array_column($r->installmentList(), 'month')); // sorted by date

        // Missing rows → validation error
        $this->actingAs($this->user())->post(route('assets.rentals.storeOrUpdate'), [
            'asset_id' => $asset->id, 'agreement_start_date' => '2024-04-01', 'rent_type' => 'Other', 'is_active' => 1, 'currency' => 'EUR',
            'payment_schedule' => 'installments',
        ])->assertSessionHasErrors('installments');
    }

    public function test_contract_import_creates_agreement_with_schedule_and_files_the_contract(): void
    {
        Storage::fake('local');
        $terms = $this->terms;
        $this->app->instance(AgreementExtractor::class, new class($terms) implements AgreementExtractor
        {
            public function __construct(private array $terms) {}

            public function extract(string $absolutePath, string $mimeType): array
            {
                return $this->terms;
            }

            public function isConfigured(): bool
            {
                return true;
            }
        });
        $asset = $this->asset('Prengos Villa 24');
        $user = $this->user();

        $this->actingAs($user)->get(route('assets.rentals.import.create'))->assertOk()->assertSee('Import agreement');
        $this->actingAs($user)->post(route('assets.rentals.import.store'), ['file' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf')])
            ->assertRedirect();
        $import = DeedImport::sole();
        $this->assertSame('agreement', $import->kind);

        $this->actingAs($user)->get(route('assets.rentals.import.review', $import))->assertOk()
            ->assertSee('Z&amp;X Holiday Villas', false)->assertSee('18,000.00')->assertSee('Year-end commission')->assertSee('15 % of guarantee');

        $prefill = AgreementMapper::toRentalAttributes($terms);
        $this->assertSame($asset->id, $prefill['asset_id']);       // matched by name
        $this->assertSame('installments', $prefill['payment_schedule']);
        $this->assertCount(7, $prefill['installments']);
        $this->assertSame(['day' => 15, 'month' => 4, 'amount' => 2700.0, 'label' => '15 % of guarantee'], $prefill['installments'][0]);

        $post = $prefill;
        $post['agreement_end_date'] = ''; // auto-renews: user clears the end date
        Carbon::setTestNow('2026-09-08');
        $this->actingAs($user)->post(route('assets.rentals.import.confirm', $import), $post)
            ->assertRedirect(route('assets.show', [$asset->id, 'tab' => 'payments']))->assertSessionHasNoErrors();
        Carbon::setTestNow();

        $rental = AssetRental::sole();
        $this->assertSame(5, RentalPayment::count()); // instalments already due in 2026
        $this->assertSame('Z&X Holiday Villas', $rental->tenant_name);
        $this->assertNull($rental->agreement_end_date);
        $this->assertTrue($rental->isInstallments());
        $this->assertSame('18000.00', $rental->amount);
        $this->assertStringContainsString('Commission', $rental->notes);

        $doc = AssetDocument::sole();
        $this->assertSame('Contract', $doc->doc_type);
        Storage::disk('local')->assertExists($doc->path);
        $this->assertSame('completed', $import->fresh()->status);
    }

    public function test_new_agreement_backfills_payments_already_due_this_year(): void
    {
        Carbon::setTestNow('2026-09-08');
        $asset = $this->asset();
        $rental = AssetRental::create([
            'asset_id' => $asset->id, 'tenant_name' => 'Z&X', 'agreement_start_date' => '2024-04-01', 'agreement_end_date' => null,
            'rent_type' => 'Other', 'is_active' => true, 'currency' => 'EUR', 'payment_schedule' => 'installments', 'amount' => 18000,
            'installments' => AgreementMapper::toRentalAttributes($this->terms)['installments'],
        ]);

        // Mar 31, Apr 15, May 31, Jun 30, Aug 30 have passed; Oct 31 and Dec 31 have not
        $this->assertSame(5, RentSchedule::backfill($rental));
        $this->assertSame(['2026-03-31', '2026-04-15', '2026-05-31', '2026-06-30', '2026-08-30'],
            RentalPayment::orderBy('due_date')->pluck('due_date')->map->toDateString()->all());
        $this->assertSame(5, RentalPayment::query()->awaitingConfirmation()->count());
        $this->assertSame(0, RentSchedule::backfill($rental)); // idempotent

        // Monthly, paid in arrears, started in July: July and August (due in Aug/Sep) plus September
        $dubai = AssetRental::create(['asset_id' => $this->asset('Dubai')->id, 'tenant_name' => 'HiGuests', 'agreement_start_date' => '2026-07-01',
            'rent_type' => 'Airbnb', 'is_active' => true, 'currency' => 'AED', 'amount' => 4000, 'paid_in_arrears' => true]);
        $this->assertSame(3, RentSchedule::backfill($dubai));
        $this->assertSame(['2026-07', '2026-08', '2026-09'], RentalPayment::where('asset_rental_id', $dubai->id)->orderBy('due_date')->pluck('period')->all());
        Carbon::setTestNow();
    }

    public function test_agreement_schema_normalizes(): void
    {
        $n = AgreementSchema::normalize(['annual_amount' => '18,000', 'schedule_type' => 'unknown', 'currency' => ' EUR ',
            'installments' => [['date' => '2024-04-15', 'amount' => '2,700', 'percent' => '15', 'label' => '']], 'warnings' => ['']]);
        $this->assertSame(18000.0, $n['annual_amount']);
        $this->assertNull($n['schedule_type']);
        $this->assertSame('EUR', $n['currency']);
        $this->assertSame(2700.0, $n['installments'][0]['amount']);
        $this->assertNull($n['installments'][0]['label']);
        $this->assertSame([], $n['warnings']);
    }
}
