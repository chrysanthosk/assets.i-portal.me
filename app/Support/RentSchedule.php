<?php

namespace App\Support;

use App\Mail\RentCheckDigestMail;
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
            $created += self::generateDueFor($rental, $asOf);
        }

        return $created;
    }

    /**
     * When an agreement is created part-way through a year, create the payments
     * that were already due since the start of the year (or the agreement start,
     * whichever is later) so they show up on the rent check. Idempotent.
     *
     * @return int number of payments created
     */
    public static function backfill(AssetRental $rental, ?CarbonInterface $until = null): int
    {
        if (! $rental->is_active) {
            return 0;
        }
        $until = Carbon::instance($until ?? now())->startOfDay();
        $from = $until->copy()->startOfYear();
        if ($rental->agreement_start_date && $rental->agreement_start_date->gt($from)) {
            $from = $rental->agreement_start_date->copy()->startOfMonth();
        }
        if ($rental->agreement_end_date && $rental->agreement_end_date->lt($from)) {
            return 0;
        }

        $created = 0;
        $cursor = $from->copy()->startOfMonth();
        // Walk month by month up to the current month and let the normal generator do the work
        while ($cursor->lte($until)) {
            $created += self::generateDueFor($rental, $cursor);
            $cursor->addMonth();
        }

        return $created;
    }

    /**
     * After an agreement is edited: pending generated payments that no longer
     * match it are removed (schedule type changed) or re-priced (monthly amount
     * changed). Rows that were confirmed, reported not received, adjusted from
     * a statement, or already reminded about are left alone.
     *
     * @param  array<string, mixed>  $old  attributes before the edit
     * @return array{removed:int, repriced:int}
     */
    public static function reconcile(AssetRental $rental, array $old): array
    {
        $untouched = fn () => RentalPayment::query()
            ->where('asset_rental_id', $rental->id)
            ->where('status', RentalPayment::STATUS_PENDING)
            ->whereNotNull('period')
            ->whereNull('statement')
            ->where('reminder_count', 0);

        $removed = 0;
        $repriced = 0;

        $scheduleChanged = ($old['payment_schedule'] ?? 'monthly') !== $rental->payment_schedule
            || (bool) ($old['paid_in_arrears'] ?? false) !== (bool) $rental->paid_in_arrears
            || (int) ($old['due_day'] ?? 0) !== (int) $rental->due_day
            || ($rental->isInstallments() && ($old['installments'] ?? null) !== $rental->installments);
        if ($scheduleChanged) {
            $removed = $untouched()->delete();   // backfill() recreates them from the new schedule
        } elseif (! $rental->isInstallments() && (float) ($old['amount'] ?? 0) !== (float) $rental->amount) {
            $repriced = $untouched()->update(['amount' => $rental->amount, 'currency' => $rental->currency ?: 'EUR']);
        }

        return ['removed' => $removed, 'repriced' => $repriced];
    }

    /** Backfill every active agreement (used by the Rent check "Generate" button). */
    public static function backfillAll(?CarbonInterface $until = null): int
    {
        $n = 0;
        foreach (AssetRental::query()->where('is_active', true)->get() as $rental) {
            $n += self::backfill($rental, $until);
        }

        return $n;
    }

    /** generateDue() restricted to one agreement and one month. */
    private static function generateDueFor(AssetRental $rental, CarbonInterface $asOf): int
    {
        $asOf = Carbon::instance($asOf)->startOfDay();
        $period = $asOf->format('Y-m');
        $monthStart = $asOf->copy()->startOfMonth();
        $monthEnd = $asOf->copy()->endOfMonth();

        if (($rental->agreement_start_date && $rental->agreement_start_date->gt($monthEnd))
            || ($rental->agreement_end_date && $rental->agreement_end_date->lt($monthStart))) {
            return 0;
        }

        $created = 0;
        if ($rental->isInstallments()) {
            $list = $rental->installmentList();
            foreach ($list as $i => $inst) {
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
                    $inst['label'] ?: sprintf('Instalment %d of %d', $i + 1, count($list)));
            }

            return $created;
        }

        if ((float) $rental->amount <= 0) {
            return 0;
        }
        if (RentalPayment::query()->where('asset_rental_id', $rental->id)->where('period', $period)->exists()) {
            return 0;
        }
        $dueMonth = $rental->paid_in_arrears ? $monthStart->copy()->addMonth() : $monthStart->copy();
        $due = $dueMonth->day(min($rental->dueDay(), $dueMonth->copy()->endOfMonth()->day));
        // First month of a tenancy: never due before the agreement starts
        if ($rental->agreement_start_date && $due->lt($rental->agreement_start_date)) {
            $due = $rental->agreement_start_date->copy();
        }

        return self::createPayment($rental, $due, $period, (float) $rental->amount, null);
    }

    private static function createPayment(AssetRental $rental, CarbonInterface $due, string $period, float $amount, ?string $label): int
    {
        // Keyed on (agreement, period): safe if the scheduler and the UI button run at once
        $payment = RentalPayment::firstOrCreate(
            ['asset_rental_id' => $rental->id, 'period' => $period],
            [
                'asset_id' => $rental->asset_id,
                'due_date' => $due->toDateString(),
                'label' => $label,
                'amount' => $amount,
                'currency' => $rental->currency ?: 'EUR',
                'status' => RentalPayment::STATUS_PENDING,
            ]
        );
        if (! $payment->wasRecentlyCreated) {
            return 0;
        }
        Audit::log('rental_payment.generated', $payment, null, $payment->toArray());

        return 1;
    }

    /**
     * Email one digest listing every payment that is due and still unconfirmed,
     * unless every such payment was already listed less than repeatDays() ago.
     *
     * @return int number of payments included (0 when nothing was sent)
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

        $due = RentalPayment::query()
            ->with(['asset', 'rental.tenant'])
            ->where('status', RentalPayment::STATUS_PENDING)
            ->whereDate('due_date', '<=', $now->toDateString())
            ->orderBy('due_date')
            ->get();

        // Send when something is new or the repeat interval has passed for at least one item
        $fresh = $force ? $due : $due->filter(fn ($p) => $p->last_reminded_at === null || $p->last_reminded_at->lte($cutoff));
        if ($due->isEmpty() || $fresh->isEmpty()) {
            return 0;
        }

        Mail::to($recipients)->send(new RentCheckDigestMail($due));

        foreach ($due as $payment) {
            $payment->forceFill([
                'reminder_count' => $payment->reminder_count + 1,
                'last_reminded_at' => $now,
            ])->save();
        }

        return $due->count();
    }
}
