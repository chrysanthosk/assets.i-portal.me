<?php

namespace Tests\Feature;

use App\Mail\DocumentExpiryDigestMail;
use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\AssetType;
use App\Models\PortalSetting;
use App\Support\DocumentReminders;
use App\Support\RentSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DocumentReminderTest extends TestCase
{
    use RefreshDatabase;

    private function doc(Asset $asset, string $name, ?string $expires): AssetDocument
    {
        return AssetDocument::create([
            'asset_id' => $asset->id, 'original_name' => $name, 'disk' => 'local', 'path' => 'x/'.$name,
            'mime_type' => 'application/pdf', 'size_bytes' => 10, 'doc_type' => 'Insurance', 'expires_at' => $expires,
        ]);
    }

    public function test_digest_lists_expired_and_expiring_documents_only(): void
    {
        Mail::fake();
        PortalSetting::set(RentSchedule::SETTING_EMAIL, 'owner@example.com');
        $type = AssetType::create(['name' => 'Apartment', 'is_active' => true, 'sort_order' => 1]);
        $asset = Asset::create(['name' => 'Flat', 'asset_type_id' => $type->id, 'currency' => 'EUR', 'status' => 'Vacant', 'ownership_percentage' => 100]);

        $this->doc($asset, 'insurance.pdf', now()->subDays(3)->toDateString());
        $this->doc($asset, 'cert.pdf', now()->addDays(10)->toDateString());
        $this->doc($asset, 'later.pdf', now()->addDays(90)->toDateString());
        $this->doc($asset, 'deed.pdf', null);

        $this->assertSame(1, DocumentReminders::send());
        Mail::assertSent(DocumentExpiryDigestMail::class, function ($m) {
            $html = $m->render();

            return $m->hasTo('owner@example.com')
                && $m->documents->count() === 2
                && str_contains($html, 'insurance.pdf') && str_contains($html, 'cert.pdf')
                && ! str_contains($html, 'later.pdf')
                && str_contains($html, 'tab=documents');
        });

        // Disabled → nothing unless forced
        PortalSetting::set(RentSchedule::SETTING_ENABLED, '0');
        $this->assertSame(0, DocumentReminders::send());
        $this->assertSame(1, DocumentReminders::send(true));
    }

    public function test_nothing_is_sent_when_no_documents_are_due(): void
    {
        Mail::fake();
        PortalSetting::set(RentSchedule::SETTING_EMAIL, 'owner@example.com');
        $this->assertSame(0, DocumentReminders::send());
        Mail::assertNothingSent();
    }

    public function test_warning_window_is_configurable(): void
    {
        PortalSetting::set(DocumentReminders::SETTING_DAYS, '7');
        $this->assertSame(7, DocumentReminders::days());
    }
}
