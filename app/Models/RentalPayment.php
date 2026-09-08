<?php

namespace App\Models;

use Carbon\Carbon;
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
        'label',
        'amount',
        'currency',
        'paid_date',
        'status',
        'method',
        'reference',
        'notes',
        'statement',
        'reminder_count',
        'last_reminded_at',
        'confirmed_at',
        'not_received_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_date' => 'date',
        'amount' => 'decimal:2',
        'statement' => 'array',
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

    /**
     * Counts for the bell / sidebar badge in one query.
     *
     * @return array{unconfirmed:int, overdue:int}
     */
    public static function attentionCounts(): array
    {
        $today = now()->toDateString();
        $row = static::query()->selectRaw(
            'SUM(CASE WHEN status = ? AND due_date <= ? THEN 1 ELSE 0 END) AS unconfirmed, '
            .'SUM(CASE WHEN status = ? OR (status = ? AND due_date < ?) THEN 1 ELSE 0 END) AS overdue',
            [self::STATUS_PENDING, $today, self::STATUS_NOT_RECEIVED, self::STATUS_PENDING, $today]
        )->first();

        return ['unconfirmed' => (int) ($row->unconfirmed ?? 0), 'overdue' => (int) ($row->overdue ?? 0)];
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
        if ($this->label) {
            return $this->label.($this->due_date ? ' · '.$this->due_date->format('d M Y') : '');
        }
        if ($this->period && preg_match('/^\d{4}-\d{2}$/', $this->period)) {
            return Carbon::createFromFormat('Y-m', $this->period)->format('F Y');
        }

        return $this->due_date ? $this->due_date->format('F Y') : '';
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
