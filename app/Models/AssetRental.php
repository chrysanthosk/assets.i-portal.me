<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AssetRental extends Model
{
    protected $fillable = [
        'asset_id',

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
     * Agreement is "active for a given month" if:
     *  - is_active = true
     *  - agreement_start_date <= end of that month
     *  - and agreement_end_date is null OR agreement_end_date >= start of that month
     */
    public function scopeActiveForPeriod($query, int $year, int $month)
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfDay()->toDateString();
        $periodEnd = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay()->toDateString();

        return $query
            ->where('is_active', true)
            ->whereNotNull('agreement_start_date')
            ->whereDate('agreement_start_date', '<=', $periodEnd)
            ->where(function ($q) use ($periodStart) {
                $q->whereNull('agreement_end_date')
                    ->orWhereDate('agreement_end_date', '>=', $periodStart);
            });
    }
}
