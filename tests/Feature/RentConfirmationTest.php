<?php

namespace Tests\Feature;

use App\Mail\RentConfirmationRequestMail;
use App\Models\Asset;
use App\Models\AssetRental;
use App\Models\AssetType;
use App\Models\PortalSetting;
use App\Models\RentalPayment;
use App\Models\User;
use App\Support\RentSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PortalSetting::set(RentSchedule::SETTING_EMAIL, 'owner@example.com');
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

    private function makeRental(array $overrides = []): AssetRental
    {
        $type = AssetType::firstOrCreate(['name' => 'Apartment'], ['is_active' => true, 'sort_order' => 1]);
        $asset = Asset::create([
            'name' => 'Flat '.uniqid(), 'asset_type_id' => $type->id,
            'currency' => 'EUR', 'status' => 'Rented', 'ownership_percentage' => 100,
        ]);

        return AssetRental::create(array_merge([
            'asset_id' => $asset->id, 'year' => 2026, 'month' => 1, 'tenant_name' => 'Maria',
            'agreement_start_date' => '2026-01-01', 'rent_type' => 'Long-term',
            'is_active' => true, 'amount' => 850, 'currency' => 'EUR',
        ], $overrides));
    }

    public function test_generation_creates_one_payment_per_agreement_per_month_and_is_idempotent(): void
    {
        PortalSetting::set(RentSchedule::SETTING_DUE_DAY, '5');
        $rental = $this->makeRental();
        $this->makeRental(['is_active' => false]);                     // inactive: skipped
        $this->makeRental(['agreement_end_date' => '2026-03-31']);     // ended before period: skipped

        $this->assertSame(1, RentSchedule::generateDue(Carbon::parse('2026-09-10')));
        $this->assertSame(0, RentSchedule::generateDue(Carbon::parse('2026-09-20')));

        $payment = RentalPayment::sole();
        $this->assertSame($rental->id, $payment->asset_rental_id);
        $this->assertSame('2026-09', $payment->period);
        $this->assertSame('2026-09-05', $payment->due_date->toDateString());
        $this->assertSame('850.00', $payment->amount);
        $this->assertSame('pending', $payment->status);

        // Next month gets its own row
        $this->assertSame(1, RentSchedule::generateDue(Carbon::parse('2026-10-01')));
    }

    public function test_reminders_go_out_for_due_unconfirmed_payments_and_respect_the_repeat_interval(): void
    {
        Mail::fake();
        PortalSetting::set(RentSchedule::SETTING_REPEAT_DAYS, '3');
        $rental = $this->makeRental();

        $due = RentalPayment::create(['asset_rental_id' => $rental->id, 'asset_id' => $rental->asset_id,
            'due_date' => now()->subDay(), 'period' => now()->format('Y-m'), 'amount' => 850, 'currency' => 'EUR']);
        RentalPayment::create(['asset_rental_id' => $rental->id, 'asset_id' => $rental->asset_id,
            'due_date' => now()->addDays(10), 'amount' => 850, 'currency' => 'EUR']);            // not due yet
        RentalPayment::create(['asset_rental_id' => $rental->id, 'asset_id' => $rental->asset_id,
            'due_date' => now()->subDays(40), 'amount' => 850, 'currency' => 'EUR', 'status' => 'paid']); // done

        $this->assertSame(1, RentSchedule::sendReminders());
        Mail::assertSent(RentConfirmationRequestMail::class, fn ($m) => $m->hasTo('owner@example.com') && $m->payment->is($due));

        $due->refresh();
        $this->assertSame(1, $due->reminder_count);
        $this->assertNotNull($due->last_reminded_at);

        // Same day again: nothing (interval not elapsed)
        $this->assertSame(0, RentSchedule::sendReminders());

        // Four days later: repeat
        $this->assertSame(1, RentSchedule::sendReminders(now()->addDays(4)));
        $this->assertSame(2, $due->fresh()->reminder_count);

        // Disabled: nothing, unless forced
        PortalSetting::set(RentSchedule::SETTING_ENABLED, '0');
        $this->assertSame(0, RentSchedule::sendReminders(now()->addDays(8)));
        $this->assertSame(1, RentSchedule::sendReminders(now()->addDays(8), true));
    }

    public function test_reminder_email_contains_signed_links_and_they_confirm_without_login(): void
    {
        $rental = $this->makeRental();
        $payment = RentalPayment::create(['asset_rental_id' => $rental->id, 'asset_id' => $rental->asset_id,
            'due_date' => now()->subDay(), 'period' => now()->format('Y-m'), 'amount' => 850, 'currency' => 'EUR']);

        $mail = new RentConfirmationRequestMail($payment);
        $html = $mail->render();
        $this->assertStringContainsString(e($mail->receivedUrl), $html);
        $this->assertStringContainsString(e($mail->notReceivedUrl), $html);
        $this->assertStringContainsString('Yes, received', $html);

        // GET shows the page but does not change anything (mail scanners prefetch links)
        $this->get($mail->receivedUrl)->assertOk()->assertSee('Yes, I received');
        $this->assertSame('pending', $payment->fresh()->status);

        // POST records it
        $this->post($mail->receivedUrl)->assertOk()->assertSee('received');
        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame(now()->toDateString(), $payment->paid_date->toDateString());
        $this->assertNotNull($payment->confirmed_at);

        // Answering again is a no-op
        $this->post($mail->notReceivedUrl)->assertOk()->assertSee('already answered');
        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_not_received_link_flags_the_payment(): void
    {
        $rental = $this->makeRental();
        $payment = RentalPayment::create(['asset_rental_id' => $rental->id, 'asset_id' => $rental->asset_id,
            'due_date' => now()->subDay(), 'amount' => 850, 'currency' => 'EUR']);

        $url = URL::temporarySignedRoute('rent.confirm', now()->addDay(), ['payment' => $payment->id, 'answer' => 'not-received']);
        $this->post($url)->assertOk()->assertSee('not received');

        $payment->refresh();
        $this->assertSame('not_received', $payment->status);
        $this->assertNotNull($payment->not_received_at);
        $this->assertTrue($payment->isOverdue());
    }

    public function test_tampered_or_unsigned_confirmation_links_are_rejected(): void
    {
        $rental = $this->makeRental();
        $payment = RentalPayment::create(['asset_rental_id' => $rental->id, 'asset_id' => $rental->asset_id,
            'due_date' => now()->subDay(), 'amount' => 850, 'currency' => 'EUR']);

        $this->get("/rent/confirm/{$payment->id}/received")->assertForbidden();
        $this->post("/rent/confirm/{$payment->id}/received")->assertForbidden();

        $signed = URL::temporarySignedRoute('rent.confirm', now()->addDay(), ['payment' => $payment->id, 'answer' => 'received']);
        $this->post(str_replace('/received', '/not-received', $signed))->assertForbidden();

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_unconfirmed_page_lists_due_payments_and_allows_inline_answers(): void
    {
        $user = $this->userWith('manage_rental_payments');
        $rental = $this->makeRental();
        $due = RentalPayment::create(['asset_rental_id' => $rental->id, 'asset_id' => $rental->asset_id,
            'due_date' => now()->subDays(2), 'period' => now()->format('Y-m'), 'amount' => 850, 'currency' => 'EUR']);
        RentalPayment::create(['asset_rental_id' => $rental->id, 'asset_id' => $rental->asset_id,
            'due_date' => now()->addDays(5), 'amount' => 850, 'currency' => 'EUR']);

        $this->get('/payments/unconfirmed')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/payments/unconfirmed')->assertForbidden();

        $this->actingAs($user)->get('/payments/unconfirmed')
            ->assertOk()
            ->assertSee('Awaiting confirmation')
            ->assertSee('owner@example.com')
            ->assertSee($rental->asset->name);

        $this->actingAs($user)->post("/payments/{$due->id}/not-received")->assertRedirect();
        $this->assertSame('not_received', $due->fresh()->status);

        $this->actingAs($user)->post("/payments/{$due->id}/paid")->assertRedirect();
        $this->assertSame('paid', $due->fresh()->status);
    }

    public function test_manual_generate_and_send_buttons(): void
    {
        Mail::fake();
        $user = $this->userWith('manage_rental_payments');
        $this->makeRental();

        $this->actingAs($user)->from('/payments/unconfirmed')->post('/payments/generate')
            ->assertRedirect('/payments/unconfirmed')->assertSessionHas('success');
        $this->assertSame(1, RentalPayment::count());

        RentalPayment::query()->update(['due_date' => now()->subDay()]);
        $this->actingAs($user)->post('/payments/send-reminders')->assertRedirect();
        Mail::assertSent(RentConfirmationRequestMail::class, 1);
    }

    public function test_portal_settings_store_reminder_options(): void
    {
        $user = $this->userWith('manage_portal_settings');

        $this->actingAs($user)->post('/settings/portal', [
            'portal_name' => 'My portal', 'rent_reminders_enabled' => '1',
            'rent_reminder_email' => 'a@example.com, b@example.com', 'rent_due_day' => 7, 'rent_reminder_repeat_days' => 2,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(['a@example.com', 'b@example.com'], RentSchedule::recipients());
        $this->assertSame(7, RentSchedule::dueDay());
        $this->assertSame(2, RentSchedule::repeatDays());

        $this->actingAs($user)->post('/settings/portal', [
            'portal_name' => 'My portal', 'rent_reminder_email' => 'not-an-email', 'rent_due_day' => 1, 'rent_reminder_repeat_days' => 3,
        ])->assertSessionHasErrors('rent_reminder_email');
    }

    public function test_recipients_fall_back_to_admin_users(): void
    {
        PortalSetting::set(RentSchedule::SETTING_EMAIL, '');
        \Spatie\Permission\Models\Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $admin->assignRole('Admin');
        User::factory()->create(['email' => 'nobody@example.com']);

        $this->assertSame(['admin@example.com'], RentSchedule::recipients());
    }
}
