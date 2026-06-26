<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    protected $fillable = [
        'name',
        'asset_type_id',
        'address',
        'notes',

        'purchase_date',
        'purchase_price',
        'currency',

        'owner_entity_id',
        'ownership_percentage',

        'title_deed',
        'title_deed_number',
        'title_deed_date',
        'lawyer_notary',

        'financed',
        'lender',
        'loan_amount',
        'interest_rate',
        'loan_start_date',
        'loan_end_date',
        'monthly_payment',

        'size_sqm',
        'land_sqm',
        'bedrooms',
        'bathrooms',
        'parking',
        'year_built',

        'status',
        'estimated_annual_expenses',
        'city',
        'country',
        'postcode',
    ];

    protected $casts = [
        'title_deed' => 'boolean',
        'financed' => 'boolean',
        'parking' => 'boolean',
        'purchase_date' => 'date',
        'title_deed_date' => 'date',
        'loan_start_date' => 'date',
        'loan_end_date' => 'date',
    ];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(AssetTag::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(AssetRental::class);
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function ownerEntity(): BelongsTo
    {
        return $this->belongsTo(OwnerEntity::class, 'owner_entity_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AssetDocument::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(AssetExpense::class);
    }
}
