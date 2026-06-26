<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'id_number',
        'notes',
    ];

    public function rentals(): HasMany
    {
        return $this->hasMany(AssetRental::class);
    }
}
