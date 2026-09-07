<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\AssetType;
use App\Models\DeedImport;
use App\Models\PortalSetting;
use App\Models\User;
use App\Support\Deeds\ClaudeDeedExtractor;
use App\Support\Deeds\DeedExtraction;
use App\Support\Deeds\DeedExtractionException;
use App\Support\Deeds\DeedExtractor;
use App\Support\Deeds\DeedMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TitleDeedImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $sample = [
        'document_type' => 'Unit sheet',
        'registration_number' => '0/8443',
        'district' => 'Paphos',
        'municipality_community' => 'Ierokipia, Konia',
        'parish' => '00',
        'locality' => 'Lourka',
        'street_address' => 'Georgiou Griva Digeni',
        'building_name' => "CHLOE'S PLACE - BLOCK B",
        'unit_number' => '103',
        'floor' => '1st floor',
        'sheet' => '51', 'plan' => '04', 'section' => '0', 'plot' => 'EPI 1868',
        'registration_date' => '2026-06-03',
        'file_number' => '6/ANP/156/2026',
        'issue_date' => '2026-06-03',
        'owners' => [['name' => 'ΚΑΤΤΙΜΕΡΗΣ ΧΡΥΣΑΝΘΟΣ', 'address' => 'Psaron 1, 8021 Paphos', 'share' => 'ΟΛΟ', 'share_pct' => 100, 'id_number' => '845869/1/1']],
        'property_type' => 'apartment',
        'property_description' => 'Apartment no. 3 on the 1st floor; parking space no. 4 and storage no. 22 on the ground floor (exclusive use).',
        'parking_spaces' => 1,
        'storage_rooms' => 1,
        'enclosed_area_sqm' => 81,
        'covered_veranda_sqm' => 10,
        'uncovered_veranda_sqm' => null,
        'plot_area_sqm' => null,
        'common_property_share_pct' => 2.97,
        'valuations' => [['date' => '2018-01-01', 'amount' => 99800, 'currency' => 'EUR'], ['date' => '2021-01-01', 'amount' => 104400, 'currency' => 'EUR']],
        'rights_and_encumbrances' => 'See attached common property sheet.',
        'notes' => null,
        'source_language' => 'el',
        'warnings' => ['Parish code 00 may be a placeholder'],
    ];

    private function fakeExtractor(?\Closure $extract = null, bool $configured = true): void
    {
        $sample = $this->sample;
        $this->app->instance(DeedExtractor::class, new class($extract ?? fn () => new DeedExtraction($sample, 'claude-test', 1200, 600), $configured) implements DeedExtractor
        {
            public function __construct(private \Closure $extract, private bool $configured) {}

            public function extract(string $absolutePath, string $mimeType): DeedExtraction
            {
                return ($this->extract)($absolutePath, $mimeType);
            }

            public function isConfigured(): bool
            {
                return $this->configured;
            }
        });
    }

    private function user(): User
    {
        $user = User::factory()->create();
        Permission::findOrCreate('manage_assets', 'web');
        $user->givePermissionTo('manage_assets');

        return $user;
    }

    public function test_requires_permission(): void
    {
        $this->fakeExtractor();
        $this->get('/assets/import')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/assets/import')->assertForbidden();
        $this->actingAs($this->user())->get('/assets/import')->assertOk()->assertSee('Import property from title deed');
    }

    public function test_upload_extracts_and_review_creates_asset_with_document(): void
    {
        Storage::fake('local');
        $this->fakeExtractor();
        AssetType::create(['name' => 'House', 'is_active' => true, 'sort_order' => 1]);
        $apartment = AssetType::create(['name' => 'Apartment', 'is_active' => true, 'sort_order' => 2]);
        $user = $this->user();

        $file = UploadedFile::fake()->create('deed.pdf', 300, 'application/pdf');
        $response = $this->actingAs($user)->post('/assets/import', ['file' => $file]);

        $import = DeedImport::sole();
        $response->assertRedirect(route('assets.import.review', $import));
        $this->assertSame('extracted', $import->status);
        $this->assertSame('0/8443', $import->extracted['registration_number']);
        $this->assertSame('claude-test', $import->model);
        Storage::disk('local')->assertExists($import->path);

        // Review page is prefilled from the deed
        $this->actingAs($user)->get(route('assets.import.review', $import))
            ->assertOk()
            ->assertSee("CHLOE'S PLACE - BLOCK B – No. 103")
            ->assertSee('0/8443')
            ->assertSee('Parish code 00 may be a placeholder')
            ->assertSee('99,800.00');

        // Confirm with the prefilled values (user could edit any of them)
        $prefill = DeedMapper::toAssetAttributes($this->sample);
        $this->assertSame($apartment->id, $prefill['asset_type_id']);
        $this->assertSame(100.0, $prefill['ownership_percentage']);
        $this->assertSame('2026-06-03', $prefill['title_deed_date']);
        $this->assertSame(1, $prefill['parking']);

        $this->actingAs($user)->post(route('assets.import.confirm', $import), $prefill + ['purchase_price' => 150000])
            ->assertRedirect();

        $asset = Asset::sole();
        $this->assertSame("CHLOE'S PLACE - BLOCK B – No. 103", $asset->name);
        $this->assertSame($apartment->id, $asset->asset_type_id);
        $this->assertTrue((bool) $asset->title_deed);
        $this->assertSame('0/8443', $asset->title_deed_number);
        $this->assertSame('Cyprus', $asset->country);
        $this->assertSame('Ierokipia, Konia', $asset->city);
        $this->assertEquals(81, (float) $asset->size_sqm);
        $this->assertSame('Paphos', $asset->title_deed_data['district']);
        $this->assertEquals(2.97, $asset->title_deed_data['common_property_share_pct']);

        $doc = AssetDocument::sole();
        $this->assertSame($asset->id, $doc->asset_id);
        $this->assertSame('Title Deed', $doc->doc_type);
        $this->assertSame('deed.pdf', $doc->original_name);
        $this->assertStringStartsWith("assets/{$asset->id}/", $doc->path);
        Storage::disk('local')->assertExists($doc->path);

        $import->refresh();
        $this->assertSame('completed', $import->status);
        $this->assertSame($asset->id, $import->asset_id);

        // The asset page shows the deed card
        $this->actingAs($user)->get(route('assets.show', $asset))->assertOk()->assertSee('Title deed')->assertSee('51 / 04 / 0 / EPI 1868');

        // Re-opening a completed review goes to the asset
        $this->actingAs($user)->get(route('assets.import.review', $import))->assertRedirect(route('assets.show', $asset));
    }

    public function test_extraction_failure_is_reported_and_recorded(): void
    {
        Storage::fake('local');
        $this->fakeExtractor(fn () => throw new DeedExtractionException('The model declined to read this document: blurry'));
        $user = $this->user();

        $this->actingAs($user)->from('/assets/import')
            ->post('/assets/import', ['file' => UploadedFile::fake()->image('deed.jpg')])
            ->assertRedirect('/assets/import')
            ->assertSessionHas('error');

        $import = DeedImport::sole();
        $this->assertSame('failed', $import->status);
        $this->assertStringContainsString('blurry', $import->error);
    }

    public function test_unconfigured_extractor_blocks_upload(): void
    {
        Storage::fake('local');
        $this->fakeExtractor(configured: false);
        $user = $this->user();

        $this->actingAs($user)->get('/assets/import')->assertOk()->assertSee('No Anthropic API key is configured');
        $this->actingAs($user)->post('/assets/import', ['file' => UploadedFile::fake()->create('deed.pdf', 10, 'application/pdf')])
            ->assertSessionHas('error');
        $this->assertSame(0, DeedImport::count());
    }

    public function test_rejects_non_document_uploads(): void
    {
        $this->fakeExtractor();
        $this->actingAs($this->user())
            ->post('/assets/import', ['file' => UploadedFile::fake()->create('deed.exe', 10, 'application/octet-stream')])
            ->assertSessionHasErrors('file');
    }

    public function test_discarding_an_import_removes_the_file(): void
    {
        Storage::fake('local');
        $this->fakeExtractor();
        $user = $this->user();
        $this->actingAs($user)->post('/assets/import', ['file' => UploadedFile::fake()->create('deed.pdf', 10, 'application/pdf')]);
        $import = DeedImport::sole();

        $this->actingAs($user)->delete(route('assets.import.destroy', $import))->assertRedirect(route('assets.import.create'));
        Storage::disk('local')->assertMissing($import->path);
        $this->assertSame(0, DeedImport::count());
    }

    public function test_schema_has_no_union_types_and_normalize_types_the_string_output(): void
    {
        $unions = 0;
        $walk = function ($node) use (&$walk, &$unions) {
            if (! is_array($node)) {
                return;
            }
            if (isset($node['anyOf']) || (isset($node['type']) && is_array($node['type']))) {
                $unions++;
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk(\App\Support\Deeds\DeedSchema::schema());
        $this->assertSame(0, $unions, 'Anthropic allows at most 16 union-typed schema fields');

        $n = \App\Support\Deeds\DeedSchema::normalize([
            'registration_number' => ' 0/8443 ', 'district' => '', 'enclosed_area_sqm' => '81',
            'common_property_share_pct' => '2,97 %', 'parking_spaces' => '1', 'plot_area_sqm' => '', 'property_type' => 'unknown',
            'owners' => [['name' => 'X', 'share' => 'ΟΛΟ', 'share_pct' => '100', 'address' => '', 'id_number' => '']],
            'valuations' => [['date' => '2018-01-01', 'amount' => '99800', 'currency' => 'EUR']],
            'warnings' => ['', 'check parish'],
        ]);
        $this->assertSame('0/8443', $n['registration_number']);
        $this->assertNull($n['district']);
        $this->assertSame(81.0, $n['enclosed_area_sqm']);
        $this->assertSame(2.97, $n['common_property_share_pct']);
        $this->assertSame(1, $n['parking_spaces']);
        $this->assertNull($n['plot_area_sqm']);
        $this->assertNull($n['property_type']);
        $this->assertSame(100.0, $n['owners'][0]['share_pct']);
        $this->assertNull($n['owners'][0]['address']);
        $this->assertSame(99800.0, $n['valuations'][0]['amount']);
        $this->assertSame(['check parish'], $n['warnings']);
    }

    public function test_mapper_handles_shares_and_missing_data(): void
    {
        $this->assertSame(100.0, DeedMapper::sharePct(null));
        $this->assertSame(100.0, DeedMapper::sharePct(['share' => 'ΟΛΟ', 'share_pct' => null]));
        $this->assertSame(50.0, DeedMapper::sharePct(['share' => '1/2', 'share_pct' => null]));
        $this->assertSame(25.5, DeedMapper::sharePct(['share' => '25,5 %', 'share_pct' => null]));
        $this->assertSame(33.0, DeedMapper::sharePct(['share' => 'x', 'share_pct' => 33]));

        $attrs = DeedMapper::toAssetAttributes(['property_type' => 'land', 'locality' => 'Lourka', 'owners' => []]);
        $this->assertSame('Land, Lourka', $attrs['name']);
        $this->assertNull($attrs['asset_type_id']); // no types seeded
        $this->assertSame(0, $attrs['parking']);
        $this->assertNull(DeedMapper::date('not a date'));
    }

    public function test_api_key_is_saved_encrypted_from_portal_settings(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('manage_portal_settings', 'web');
        $user->givePermissionTo('manage_portal_settings');

        $this->actingAs($user)->post('/settings/portal', [
            'portal_name' => 'P', 'rent_due_day' => 1, 'rent_reminder_repeat_days' => 3,
            'anthropic_api_key' => 'sk-ant-test-123',
        ])->assertSessionHas('success');

        $stored = PortalSetting::get(ClaudeDeedExtractor::SETTING_API_KEY);
        $this->assertNotSame('sk-ant-test-123', $stored);
        $this->assertSame('sk-ant-test-123', Crypt::decryptString($stored));
        $this->assertSame('sk-ant-test-123', ClaudeDeedExtractor::apiKey());
        $this->assertTrue((new ClaudeDeedExtractor)->isConfigured());

        // Saving again without typing a key keeps it; the clear box removes it
        $this->actingAs($user)->post('/settings/portal', ['portal_name' => 'P', 'rent_due_day' => 1, 'rent_reminder_repeat_days' => 3]);
        $this->assertSame('sk-ant-test-123', ClaudeDeedExtractor::apiKey());

        $this->actingAs($user)->post('/settings/portal', ['portal_name' => 'P', 'rent_due_day' => 1, 'rent_reminder_repeat_days' => 3, 'anthropic_api_key_clear' => '1']);
        config(['services.anthropic.key' => null]);
        $this->assertNull(ClaudeDeedExtractor::apiKey());
    }
}
