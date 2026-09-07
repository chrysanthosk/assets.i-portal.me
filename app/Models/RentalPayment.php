<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalPayment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_NOT_RECEIVED = 'not_received';

    protected $fillable = [
        'asset_rental_id',
        'asset_id',
        'due_date',
        'period',
        'amount',
        'currency',
        'paid_date',
        'status',
        'method',
        'reference',
        'notes',
        'reminder_count',
        'last_reminded_at',
        'confirmed_at',
        'not_received_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_date' => 'date',
        'amount' => 'decimal:2',
        'reminder_count' => 'integer',
        'last_reminded_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'not_received_at' => 'datetime',
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(AssetRental::class, 'asset_rental_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** Due (or past due) and nobody has said yet whether the money arrived. */
    public function scopeAwaitingConfirmation(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->whereDate('due_date', '<=', now()->toDateString());
    }

    /** Anything still unpaid: pending past due, or explicitly reported as not received. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', self::STATUS_NOT_RECEIVED)
                ->orWhere(fn (Builder $p) => $p->where('status', self::STATUS_PENDING)
                    ->whereDate('due_date', '<', now()->toDateString()));
        });
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_NOT_RECEIVED
            || ($this->status === self::STATUS_PENDING && $this->due_date && $this->due_date->isPast());
    }

    /** Human label for the covered period, e.g. "September 2026". */
    public function periodLabel(): string
    {
        $date = $this->period
            ? \Carbon\Carbon::createFromFormat('Y-m', $this->period)
            : $this->due_date;

        return $date ? $date->format('F Y') : '';
    }

    public function tenantName(): ?string
    {
        return $this->rental?->tenant?->name ?? $this->rental?->tenant_name;
    }

    public function markReceived(?string $method = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_PAID,
            'paid_date' => now()->toDateString(),
            'confirmed_at' => now(),
            'not_received_at' => null,
            'method' => $method ?? $this->method,
        ])->save();
    }

    public function markNotReceived(): void
    {
        $this->forceFill([
            'status' => self::STATUS_NOT_RECEIVED,
            'not_received_at' => now(),
            'confirmed_at' => now(),
        ])->save();
    }
}
