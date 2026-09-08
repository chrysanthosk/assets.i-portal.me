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

        $rentals = AssetRental::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('agreement_start_date')
                ->orWhereDate('agreement_start_date', '<=', $monthEnd->toDateString()))
            ->where(fn ($q) => $q->whereNull('agreement_end_date')
                ->orWhereDate('agreement_end_date', '>=', $monthStart->toDateString()))
            ->get();

        $created = 0;
        foreach ($rentals as $rental) {
            // Instalment schedule: every instalment whose month is this month
            if ($rental->isInstallments()) {
                foreach ($rental->installmentList() as $i => $inst) {
                    if ($inst['month'] !== (int) $asOf->format('n')) {
                        continue;
                    }
                    $key = $period.'#'.($i + 1);
                    if (RentalPayment::query()->where('asset_rental_id', $rental->id)->where('period', $key)->exists()) {
                        continue;
                    }
                    $due = $monthStart->copy()->day(min($inst['day'], $monthEnd->day));
                    if (($rental->agreement_start_date && $due->lt($rental->agreement_start_date))
                        || ($rental->agreement_end_date && $due->gt($rental->agreement_end_date))) {
                        continue;
                    }
                    $created += self::createPayment($rental, $due, $key, $inst['amount'],
                        $inst['label'] ?: sprintf('Instalment %d of %d', $i + 1, count($rental->installmentList())));
                }

                continue;
            }

            // Monthly rent: one row per month, due on the due day (next month when paid in arrears)
            if ((float) $rental->amount <= 0) {
                continue;
            }
            if (RentalPayment::query()->where('asset_rental_id', $rental->id)->where('period', $period)->exists()) {
                continue;
            }
            $dueMonth = $rental->paid_in_arrears ? $monthStart->copy()->addMonth() : $monthStart->copy();
            $due = $dueMonth->day(min(self::dueDay(), $dueMonth->copy()->endOfMonth()->day));
            $created += self::createPayment($rental, $due, $period, (float) $rental->amount, null);
        }

        return $created;
    }

    private static function createPayment(AssetRental $rental, CarbonInterface $due, string $period, float $amount, ?string $label): int
    {
        $payment = RentalPayment::create([
            'asset_rental_id' => $rental->id,
            'asset_id' => $rental->asset_id,
            'due_date' => $due->toDateString(),
            'period' => $period,
            'label' => $label,
            'amount' => $amount,
            'currency' => $rental->currency ?: 'EUR',
            'status' => RentalPayment::STATUS_PENDING,
        ]);
        Audit::log('rental_payment.generated', $payment, null, $payment->toArray());

        return 1;
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
