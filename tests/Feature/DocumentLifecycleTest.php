<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\AssetType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DocumentLifecycleTest extends TestCase
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

    public function test_expiry_helpers(): void
    {
        $expired = new AssetDocument(['expires_at' => now()->subDay()]);
        $soon = new AssetDocument(['expires_at' => now()->addDays(10)]);
        $later = new AssetDocument(['expires_at' => now()->addDays(120)]);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($expired->isExpiringSoon());

        $this->assertTrue($soon->isExpiringSoon());
        $this->assertFalse($soon->isExpired());

        $this->assertFalse($later->isExpiringSoon());
    }

    public function test_upload_stores_type_and_expiry(): void
    {
        Storage::fake('local');
        $user = $this->userWith('manage_assets');
        $asset = $this->makeAsset();

        $this->actingAs($user)->post(route('assets.documents.store', $asset), [
            'file' => UploadedFile::fake()->create('insurance.pdf', 50, 'application/pdf'),
            'doc_type' => 'Insurance',
            'expires_at' => now()->addMonths(6)->toDateString(),
        ])->assertRedirect();

        $doc = AssetDocument::first();
        $this->assertNotNull($doc);
        $this->assertSame('Insurance', $doc->doc_type);
        $this->assertNotNull($doc->expires_at);
    }

    public function test_invalid_doc_type_is_rejected(): void
    {
        Storage::fake('local');
        $user = $this->userWith('manage_assets');
        $asset = $this->makeAsset();

        $this->actingAs($user)->post(route('assets.documents.store', $asset), [
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            'doc_type' => 'NotAType',
        ])->assertSessionHasErrors('doc_type');
    }
}
