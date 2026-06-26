<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRental extends Model
{
    protected $table = 'asset_rentals';

    protected $fillable = [
        'asset_id',
        'tenant_id',

        // IMPORTANT (legacy NOT NULL columns)
        'year',
        'month',

        'tenant_name',
        'agreement_start_date',
        'agreement_end_date',
        'rent_type',
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
        'amount' => 'decimal:2',
        'year' => 'integer',
        'month' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
