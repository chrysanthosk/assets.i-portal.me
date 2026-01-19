<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetRental extends Model
{
    protected $fillable = [
        'asset_id',
        'year',
        'month',

        'agreement_start_date',
        'agreement_end_date',
        'tenant_name',
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
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Consider agreement "active" if:
     *  - start_date is set and <= end-of-period
     *  - and end_date is null OR end_date >= start-of-period
     */
    public function scopeActiveForPeriod($query, int $year, int $month)
    {
        $periodStart = sprintf('%04d-%02d-01', $year, $month);
        $periodEnd = \Carbon\Carbon::parse($periodStart)->endOfMonth()->toDateString();

        return $query
            ->where(function ($q) use ($periodEnd) {
                $q->whereNull('agreement_start_date')
                  ->orWhere('agreement_start_date', '<=', $periodEnd);
            })
            ->where(function ($q) use ($periodStart) {
                $q->whereNull('agreement_end_date')
                  ->orWhere('agreement_end_date', '>=', $periodStart);
            })
            ->where('is_active', true);
    }
}
