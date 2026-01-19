<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'name','type','address','notes',
        'purchase_date','purchase_price','currency','owner_entity','ownership_percentage',
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

    public function tags()
    {
        return $this->belongsToMany(AssetTag::class, 'asset_asset_tag')->withTimestamps();
    }

    public function rentals()
    {
        return $this->hasMany(AssetRental::class);
    }
}
