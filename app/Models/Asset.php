<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'name',
        'address',
        'notes',

        'purchase_date','purchase_price','currency',

        // legacy fields remain in DB but we won't rely on them in UI anymore
        'type',
        'owner_entity',

        // new normalized fields
        'asset_type_id',
        'owner_entity_id',

        'ownership_percentage',

        'title_deed','title_deed_number','title_deed_date','lawyer_notary',

        'financed','lender','loan_amount','interest_rate','loan_start_date','loan_end_date','monthly_payment',

        'size_sqm','land_sqm','bedrooms','bathrooms','parking','year_built',

        'status','estimated_annual_expenses',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'title_deed' => 'boolean',
        'title_deed_date' => 'date',
        'financed' => 'boolean',
        'loan_start_date' => 'date',
        'loan_end_date' => 'date',
        'parking' => 'boolean',
    ];

    public function typeRef()
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function ownerEntityRef()
    {
        return $this->belongsTo(OwnerEntity::class, 'owner_entity_id');
    }

    public function tags()
    {
        return $this->belongsToMany(AssetTag::class, 'asset_asset_tag')->withTimestamps();
    }

    public function rentals()
    {
        return $this->hasMany(AssetRental::class);
    }
}
