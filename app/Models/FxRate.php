<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxRate extends Model
{
    protected $fillable = [
        'currency',
        'rate_to_base',
    ];

    protected $casts = [
        'rate_to_base' => 'decimal:8',
    ];
}
