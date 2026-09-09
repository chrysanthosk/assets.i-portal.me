<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\AssetExpense;
use App\Models\AssetRental;
use App\Models\AssetType;
use App\Models\RentalPayment;
use App\Models\User;
use App\Support\RentSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private function user(string ...$perms): User
    {
        $u = User::factory()->create();
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
            $u->givePermissionTo($p);
        }

        return $u;
    }

    private function asset(string $name = 'Flat'): Asset
    {
        $type = AssetType::firstOrCreate(['name' => 'Apartment'], ['is_active' => true, 'sort_order' => 1]);

        return Asset::create(['name' => $name, 'asset_type_id' => $type->id, 'currency' => 'EUR', 'status' => 'Vacant', 'ownership_percentage' => 100]);
    }

    public function test_document_download_is_scoped_to_its_property(): void
    {
        Storage::fake('local');
        $user = $this->user('manage_assets');
        $a = $this->asset('A');
        $b = $this->asset('B');
        Storage::disk('local')->put('assets/'.$a->id.'/deed.pdf', 'secret');
        $doc = AssetDocument::create(['asset_id' => $a->id, 'original_name' => 'deed.pdf', 'disk' => 'local', 'path' => 'assets/'.$a->id.'/deed.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 6]);

        $this->get(route('assets.documents.download', [$a, $doc]))->assertRedirect('/login');
        $this->actingAs($user)->get(route('assets.documents.download', [$a, $doc]))->assertOk();
        $this->actingAs($user)->get(route('assets.documents.download', [$b, $doc]))->assertNotFound();
    }

    public function test_deleting_a_property_removes_its_files(): void
    {
        Storage::fake('local');
        $user = $this->user('manage_assets');
        $a = $this->asset();
        Storage::disk('local')->put('assets/'.$a->id.'/deed.pdf', 'x');
        AssetDocument::create(['asset_id' => $a->id, 'original_name' => 'deed.pdf', 'disk' => 'local', 'path' => 'assets/'.$a->id.'/deed.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1]);

        $this->actingAs($user)->delete(route('assets.destroy', $a))->assertRedirect();
        Storage::disk('local')->assertMissing('assets/'.$a->id.'/deed.pdf');
        $this->assertSame(0, AssetDocument::count());
    }

    public function test_the_last_admin_cannot_be_deleted_or_demoted(): void
    {
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Viewer', 'web');
        $admin = $this->user('manage_users');
        $admin->assignRole('Admin');
        $other = $this->user('manage_users');
        $other->assignRole('Admin');
        $onlyAdminLater = $admin;

        // Two admins: demoting one is fine
        $this->actingAs($other)->put(route('settings.users.update', $admin), [
            'username' => $admin->username, 'email' => $admin->email, 'name' => 'A', 'surname' => 'B', 'role' => 'Viewer',
        ])->assertRedirect(route('settings.users.index'));
        $this->assertFalse($admin->fresh()->hasRole('Admin'));

        // Now $other is the last admin: cannot be demoted or deleted
        $this->actingAs($admin)->put(route('settings.users.update', $other), [
            'username' => $other->username, 'email' => $other->email, 'name' => 'A', 'surname' => 'B', 'role' => 'Viewer',
        ])->assertSessionHas('error');
        $this->assertTrue($other->fresh()->hasRole('Admin'));
        $this->actingAs($admin)->delete(route('settings.users.destroy', $other))->assertSessionHas('error');
        $this->assertNotNull($other->fresh());
    }

    public function test_expense_currency_must_be_an_iso_code(): void
    {
        $user = $this->user('manage_asset_expenses');
        $a = $this->asset();
        $this->actingAs($user)->post(route('expenses.store'), ['asset_id' => $a->id, 'spent_on' => '2026-09-01', 'category' => AssetExpense::CATEGORIES[0], 'amount' => 10, 'currency' => 'euros'])
            ->assertSessionHasErrors('currency');
    }

    public function test_marking_not_received_clears_the_paid_date(): void
    {
        $user = $this->user('manage_rental_payments');
        $a = $this->asset();
        $r = AssetRental::create(['asset_id' => $a->id, 'agreement_start_date' => '2026-01-01', 'rent_type' => 'Long-term', 'is_active' => true, 'amount' => 100, 'currency' => 'EUR']);
        $p = RentalPayment::create(['asset_rental_id' => $r->id, 'asset_id' => $a->id, 'due_date' => '2026-09-01', 'period' => '2026-09', 'amount' => 100, 'currency' => 'EUR', 'status' => 'paid', 'paid_date' => '2026-09-02']);

        $this->actingAs($user)->post(route('payments.markNotReceived', $p))->assertRedirect();
        $this->assertNull($p->fresh()->paid_date);
        $this->assertSame('not_received', $p->fresh()->status);
    }

    public function test_editing_an_agreement_reconciles_pending_generated_payments(): void
    {
        Carbon::setTestNow('2026-09-09');
        $user = $this->user('manage_asset_rentals');
        $a = $this->asset();
        $r = AssetRental::create(['asset_id' => $a->id, 'tenant_name' => 'T', 'agreement_start_date' => '2026-07-01', 'rent_type' => 'Long-term', 'is_active' => true, 'amount' => 1000, 'currency' => 'EUR']);
        RentSchedule::backfill($r);
        $this->assertSame(3, RentalPayment::count());
        RentalPayment::where('period', '2026-07')->update(['status' => 'paid', 'paid_date' => '2026-07-02']);

        // Amount change re-prices the still-pending rows only
        $base = ['asset_id' => $a->id, 'tenant_name' => 'T', 'agreement_start_date' => '2026-07-01', 'rent_type' => 'Long-term', 'is_active' => 1, 'currency' => 'EUR', 'payment_schedule' => 'monthly'];
        $this->actingAs($user)->put(route('assets.rentals.update', $r), array_merge($base, ['amount' => 1100]))->assertRedirect();
        $this->assertSame('1000.00', RentalPayment::where('period', '2026-07')->sole()->amount);
        $this->assertSame('1100.00', RentalPayment::where('period', '2026-08')->sole()->amount);

        // Switching to instalments removes the pending monthly rows and generates the schedule instead
        $this->actingAs($user)->put(route('assets.rentals.update', $r), array_merge($base, ['payment_schedule' => 'installments',
            'installments' => [['day' => 15, 'month' => 8, 'amount' => 5000, 'label' => 'Summer']]]))->assertRedirect();
        $this->assertSame(0, RentalPayment::where('status', 'pending')->whereNull('label')->count());
        $this->assertSame('paid', RentalPayment::where('period', '2026-07')->sole()->status);   // untouched
        $this->assertSame('5000.00', RentalPayment::where('period', '2026-08#1')->sole()->amount);
        Carbon::setTestNow();
    }
}
