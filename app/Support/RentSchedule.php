<?php

namespace App\Support;

use App\Mail\RentConfirmationRequestMail;
use App\Models\AssetRental;
use App\Models\PortalSetting;
use App\Models\RentalPayment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

/**
 * Monthly rent loop:
 *   1. generateDue()   — one expected payment per active agreement per month
 *   2. sendReminders() — email "did the rent arrive?" for every payment that is
 *                        due and still unconfirmed, repeating every N days
 *
 * Settings live in portal_settings (see Settings → Portal).
 */
class RentSchedule
{
    public const SETTING_ENABLED = 'rent_reminders_enabled';

    public const SETTING_EMAIL = 'rent_reminder_email';

    public const SETTING_DUE_DAY = 'rent_due_day';

    public const SETTING_REPEAT_DAYS = 'rent_reminder_repeat_days';

    public static function enabled(): bool
    {
        return PortalSetting::get(self::SETTING_ENABLED, '1') === '1';
    }

    /** Day of month rent is due (1–28 so every month has it). */
    public static function dueDay(): int
    {
        return max(1, min(28, (int) PortalSetting::get(self::SETTING_DUE_DAY, '1')));
    }

    /** Days between repeated reminders for the same payment. */
    public static function repeatDays(): int
    {
        return max(1, (int) PortalSetting::get(self::SETTING_REPEAT_DAYS, '3'));
    }

    /**
     * Who receives the reminders: the configured address(es), or every user
     * holding the admin role when nothing is configured.
     *
     * @return array<int, string>
     */
    public static function recipients(): array
    {
        $configured = PortalSetting::get(self::SETTING_EMAIL);
        if ($configured) {
            return collect(preg_split('/[\s,;]+/', $configured))
                ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
                ->unique()->values()->all();
        }

        $role = (string) config('portal.admin_role', 'Admin');
        if (! Role::query()->where('name', $role)->exists()) {
            return [];
        }

        return User::role($role)->pluck('email')->filter()->unique()->values()->all();
    }

    /**
     * Create the expected payment for the month containing $asOf for every
     * active agreement that covers that month. Idempotent.
     *
     * @return int number of payments created
     */
    public static function generateDue(?CarbonInterface $asOf = null): int
    {
        $asOf = Carbon::instance($asOf ?? now())->startOfDay();
        $period = $asOf->format('Y-m');
        $monthStart = $asOf->copy()->startOfMonth();
        $monthEnd = $asOf->copy()->endOfMonth();
        $dueDate = $monthStart->copy()->day(self::dueDay());

        $rentals = AssetRental::query()
            ->where('is_active', true)
            ->where('amount', '>', 0)
            ->where(fn ($q) => $q->whereNull('agreement_start_date')
                ->orWhereDate('agreement_start_date', '<=', $monthEnd->toDateString()))
            ->where(fn ($q) => $q->whereNull('agreement_end_date')
                ->orWhereDate('agreement_end_date', '>=', $monthStart->toDateString()))
            ->get();

        $created = 0;
        foreach ($rentals as $rental) {
            $exists = RentalPayment::query()
                ->where('asset_rental_id', $rental->id)
                ->where('period', $period)
                ->exists();
            if ($exists) {
                continue;
            }

            $payment = RentalPayment::create([
                'asset_rental_id' => $rental->id,
                'asset_id' => $rental->asset_id,
                'due_date' => $dueDate->toDateString(),
                'period' => $period,
                'amount' => $rental->amount,
                'currency' => $rental->currency ?: 'EUR',
                'status' => RentalPayment::STATUS_PENDING,
            ]);

            Audit::log('rental_payment.generated', $payment, null, $payment->toArray());
            $created++;
        }

        return $created;
    }

    /**
     * Email a confirmation request for every payment that is due and still
     * unconfirmed, unless one went out less than repeatDays() ago.
     *
     * @return int number of reminder emails sent
     */
    public static function sendReminders(?CarbonInterface $now = null, bool $force = false): int
    {
        if (! $force && ! self::enabled()) {
            return 0;
        }

        $recipients = self::recipients();
        if ($recipients === []) {
            return 0;
        }

        $now = Carbon::instance($now ?? now());
        $cutoff = $now->copy()->subDays(self::repeatDays());

        $payments = RentalPayment::query()
            ->with(['asset', 'rental.tenant'])
            ->where('status', RentalPayment::STATUS_PENDING)
            ->whereDate('due_date', '<=', $now->toDateString())
            ->when(! $force, fn ($q) => $q->where(fn ($w) => $w->whereNull('last_reminded_at')
                ->orWhere('last_reminded_at', '<=', $cutoff)))
            ->orderBy('due_date')
            ->get();

        $sent = 0;
        foreach ($payments as $payment) {
            Mail::to($recipients)->send(new RentConfirmationRequestMail($payment));

            $payment->forceFill([
                'reminder_count' => $payment->reminder_count + 1,
                'last_reminded_at' => $now,
            ])->save();
            $sent++;
        }

        return $sent;
    }
}
