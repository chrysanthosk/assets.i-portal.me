<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetExpense extends Model
{
    /**
     * Allowed expense categories (kept in code so reporting can rely on them).
     */
    public const CATEGORIES = [
        'Maintenance',
        'Repairs',
        'Property Tax',
        'Insurance',
        'Utilities',
        'Management Fee',
        'Legal',
        'Other',
    ];

    protected $fillable = [
        'asset_id',
        'spent_on',
        'category',
        'amount',
        'currency',
        'vendor',
        'description',
    ];

    protected $casts = [
        'spent_on' => 'date',
        'amount' => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
