<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\AssetRental;
use App\Models\AssetType;
use App\Models\RentalPayment;
use App\Models\User;
use App\Support\Deeds\DeedExtractionException;
use App\Support\Deeds\DeedMapper;
use App\Support\Statements\StatementExtractor;
use App\Support\Statements\StatementSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentStatementTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $figures = [
        'management_company' => 'HiGuests Dubai', 'property_reference' => 'CHRYSANTHOS_1BR_DIFC_PT_2307', 'owner_name' => 'C. K.',
        'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'statement_date' => '2026-09-07', 'currency' => 'AED',
        'gross_income' => 4889.79, 'management_fee' => 770.14, 'expenses' => 0.0, 'net_payable' => 4119.65, 'notes' => null, 'warnings' => [],
    ];

    private function fake(?\Closure $fn = null): void
    {
        $figures = $this->figures;
        $this->app->instance(StatementExtractor::class, new class($fn ?? fn () => $figures) implements StatementExtractor
        {
            public function __construct(private \Closure $fn) {}

            public function extract(string $absolutePath, string $mimeType): array
            {
                return ($this->fn)();
            }

            public function isConfigured(): bool
            {
                return true;
            }
        });
    }

    private function payment(): RentalPayment
    {
        $type = AssetType::create(['name' => 'Apartment', 'is_active' => true, 'sort_order' => 1]);
        $asset = Asset::create(['name' => 'Park Towers B2307', 'asset_type_id' => $type->id, 'currency' => 'AED', 'status' => 'Airbnb/Short-term', 'ownership_percentage' => 100]);
        $rental = AssetRental::create(['asset_id' => $asset->id, 'agreement_start_date' => '2026-01-01', 'rent_type' => 'Airbnb',
            'is_active' => true, 'amount' => 4000, 'currency' => 'AED', 'tenant_name' => 'HiGuests']);

        return RentalPayment::create(['asset_rental_id' => $rental->id, 'asset_id' => $asset->id, 'due_date' => '2026-09-01',
            'period' => '2026-08', 'amount' => 4000, 'currency' => 'AED']);
    }

    private function user(): User
    {
        $u = User::factory()->create();
        Permission::findOrCreate('manage_rental_payments', 'web');
        $u->givePermissionTo('manage_rental_payments');

        return $u;
    }

    public function test_statement_upload_stores_document_and_hands_figures_to_the_page(): void
    {
        Storage::fake('local');
        $this->fake();
        $p = $this->payment();

        $this->actingAs($this->user())->from('/payments/unconfirmed')
            ->post(route('payments.statement', $p), ['file' => UploadedFile::fake()->create('aug.pdf', 50, 'application/pdf')])
            ->assertRedirect('/payments/unconfirmed')
            ->assertSessionHas('statement', fn ($s) => $s['payment_id'] === $p->id && $s['figures']['net_payable'] === 4119.65);

        $doc = AssetDocument::sole();
        $this->assertSame('Statement', $doc->doc_type);
        $this->assertSame($p->asset_id, $doc->asset_id);
        $this->assertStringContainsString('HiGuests', $doc->notes);
        Storage::disk('local')->assertExists($doc->path);

        // The page then renders the modal pre-filled from the session
        $this->actingAs($this->user())->withSession(['statement' => ['payment_id' => $p->id, 'figures' => $this->figures]])
            ->get('/payments/unconfirmed')->assertOk()->assertSee('4119.65')->assertSee('HiGuests Dubai');
    }

    public function test_adjust_sets_amount_and_can_mark_received_with_statement_breakdown(): void
    {
        $p = $this->payment();

        $this->actingAs($this->user())->post(route('payments.adjust', $p), [
            'amount' => 4119.65, 'currency' => 'AED', 'received' => '1', 'paid_date' => '2026-09-08',
            'notes' => 'Statement from HiGuests', 'statement' => json_encode($this->figures),
        ])->assertRedirect()->assertSessionHas('success');

        $p->refresh();
        $this->assertSame('4119.65', $p->amount);
        $this->assertSame('paid', $p->status);
        $this->assertSame('2026-09-08', $p->paid_date->toDateString());
        $this->assertSame(770.14, $p->statement['management_fee']);

        // Amount-only correction keeps it pending
        $q = RentalPayment::create(['asset_rental_id' => $p->asset_rental_id, 'asset_id' => $p->asset_id, 'due_date' => '2026-10-01', 'period' => '2026-09', 'amount' => 4000, 'currency' => 'AED']);
        $this->actingAs($this->user())->post(route('payments.adjust', $q), ['amount' => 3500])->assertRedirect();
        $this->assertSame('pending', $q->fresh()->status);
        $this->assertSame('3500.00', $q->fresh()->amount);
    }

    public function test_unreadable_statement_reports_error_and_keeps_no_file(): void
    {
        Storage::fake('local');
        $this->fake(fn () => throw new DeedExtractionException('blurry'));
        $p = $this->payment();

        $this->actingAs($this->user())->post(route('payments.statement', $p), ['file' => UploadedFile::fake()->image('s.jpg')])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, AssetDocument::count());
    }

    public function test_statement_schema_normalizes_strings(): void
    {
        $n = StatementSchema::normalize(['gross_income' => '4,889.79', 'management_fee' => 'AED 770.14', 'expenses' => '', 'net_payable' => '4119.65', 'currency' => ' AED ', 'warnings' => ['', 'x']]);
        $this->assertSame(4889.79, $n['gross_income']);
        $this->assertSame(770.14, $n['management_fee']);
        $this->assertNull($n['expenses']);
        $this->assertSame('AED', $n['currency']);
        $this->assertSame(['x'], $n['warnings']);
    }

    public function test_deed_mapper_handles_a_dubai_deed(): void
    {
        AssetType::create(['name' => 'Apartment', 'is_active' => true, 'sort_order' => 1]);
        $attrs = DeedMapper::toAssetAttributes([
            'country' => 'United Arab Emirates', 'registry' => 'DIFC Real Property Register', 'registration_number' => 'PB 02/B2307/0002/0001',
            'file_number' => 'PB 02/B2307', 'district' => 'Dubai International Financial Centre', 'municipality_community' => 'Dubai',
            'building_name' => 'Park Towers', 'unit_number' => 'B2307', 'property_type' => 'apartment', 'enclosed_area_sqm' => 80.6,
            'parking_spaces' => 1, 'registration_date' => '2024-05-02', 'owners' => [['name' => 'Mr. C. K.', 'share' => null, 'share_pct' => 100]],
            'valuations' => [],
        ]);
        $this->assertSame('Park Towers – No. B2307', $attrs['name']);
        $this->assertSame('United Arab Emirates', $attrs['country']);
        $this->assertSame('AED', $attrs['currency']);
        $this->assertSame('Dubai', $attrs['city']);
        $this->assertSame(1, $attrs['parking']);
        $this->assertSame('PB 02/B2307/0002/0001', $attrs['title_deed_number']);
        $this->assertSame('2024-05-02', $attrs['title_deed_date']);
    }
}
