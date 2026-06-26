<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetRental;
use App\Models\AssetType;
use App\Models\RentalPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RentalPaymentTest extends TestCase
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

    private function makeRental(): AssetRental
    {
        $type = AssetType::create(['name' => 'Apartment', 'is_active' => true, 'sort_order' => 1]);
        $asset = Asset::create([
            'name' => 'Flat', 'asset_type_id' => $type->id, 'type' => 'Apartment',
            'currency' => 'EUR', 'status' => 'Rented', 'ownership_percentage' => 100,
        ]);

        return AssetRental::create([
            'asset_id' => $asset->id, 'year' => 2026, 'month' => 1,
            'agreement_start_date' => '2026-01-01', 'rent_type' => 'Long-term',
            'is_active' => true, 'amount' => 1000, 'currency' => 'EUR',
        ]);
    }

    public function test_permission_is_required(): void
    {
        $this->get('/payments')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/payments')->assertForbidden();
    }

    public function test_recording_a_payment_without_paid_date_is_pending(): void
    {
        $user = $this->userWith('manage_rental_payments');
        $rental = $this->makeRental();

        $this->actingAs($user)->post(route('payments.store'), [
            'asset_rental_id' => $rental->id,
            'due_date' => '2026-02-01',
            'amount' => 1000,
            'currency' => 'EUR',
        ])->assertRedirect();

        $payment = RentalPayment::first();
        $this->assertSame('pending', $payment->status);
        $this->assertSame($rental->asset_id, $payment->asset_id);
    }

    public function test_recording_with_paid_date_is_paid_and_can_be_marked_paid(): void
    {
        $user = $this->userWith('manage_rental_payments');
        $rental = $this->makeRental();

        // Paid up front
        $this->actingAs($user)->post(route('payments.store'), [
            'asset_rental_id' => $rental->id,
            'due_date' => '2026-02-01',
            'amount' => 1000,
            'currency' => 'EUR',
            'paid_date' => '2026-02-02',
        ]);
        $this->assertSame('paid', RentalPayment::first()->status);

        // A pending one can be marked paid
        $pending = RentalPayment::create([
            'asset_rental_id' => $rental->id, 'asset_id' => $rental->asset_id,
            'due_date' => '2020-01-01', 'amount' => 500, 'currency' => 'EUR', 'status' => 'pending',
        ]);
        $this->assertTrue($pending->isOverdue());

        $this->actingAs($user)->post(route('payments.markPaid', $pending))->assertRedirect();
        $this->assertSame('paid', $pending->fresh()->status);
        $this->assertNotNull($pending->fresh()->paid_date);
    }
}
