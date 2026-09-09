<?php

namespace App\Models;

use App\Support\RentSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetRental extends Model
{
    protected $table = 'asset_rentals';

    protected $fillable = [
        'asset_id',
        'tenant_id',

        'tenant_name',
        'agreement_start_date',
        'agreement_end_date',
        'rent_type',
        'payment_schedule',
        'paid_in_arrears',
        'due_day',
        'installments',
        'is_active',

        'amount',
        'currency',
        'channel',
        'notes',
    ];

    protected $casts = [
        'agreement_start_date' => 'date',
        'agreement_end_date' => 'date',
        'is_active' => 'boolean',
        'paid_in_arrears' => 'boolean',
        'installments' => 'array',
        'amount' => 'decimal:2',
    ];

    public const SCHEDULE_MONTHLY = 'monthly';

    public const SCHEDULE_INSTALLMENTS = 'installments';

    public function isInstallments(): bool
    {
        return $this->payment_schedule === self::SCHEDULE_INSTALLMENTS;
    }

    /**
     * Instalments sorted by date within the year.
     *
     * @return array<int, array{month:int, day:int, amount:float, label:string|null}>
     */
    public function installmentList(): array
    {
        $rows = array_values(array_filter(array_map(function ($i) {
            $m = (int) ($i['month'] ?? 0);
            $d = (int) ($i['day'] ?? 0);
            if ($m < 1 || $m > 12 || $d < 1 || $d > 31) {
                return null;
            }

            return ['month' => $m, 'day' => $d, 'amount' => (float) ($i['amount'] ?? 0), 'label' => $i['label'] ?? null];
        }, $this->installments ?? [])));
        usort($rows, fn ($a, $b) => [$a['month'], $a['day']] <=> [$b['month'], $b['day']]);

        return $rows;
    }

    /** Day of month rent is due: the agreement's own, else the portal default. */
    public function dueDay(): int
    {
        return $this->due_day ? max(1, min(28, (int) $this->due_day)) : RentSchedule::dueDay();
    }

    /** What this agreement brings in per month, for dashboards (annual ÷ 12 for instalments). */
    public function monthlyEquivalent(): float
    {
        return $this->isInstallments() ? round((float) $this->amount / 12, 2) : (float) $this->amount;
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentalPayment::class);
    }
}
