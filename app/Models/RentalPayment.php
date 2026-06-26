<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalPayment extends Model
{
    protected $fillable = [
        'asset_rental_id',
        'asset_id',
        'due_date',
        'amount',
        'currency',
        'paid_date',
        'status',
        'method',
        'reference',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(AssetRental::class, 'asset_rental_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Pending and past its due date.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending'
            && $this->due_date !== null
            && $this->due_date->isPast();
    }
}
