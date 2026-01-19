<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetTag extends Model
{
    protected $fillable = ['name'];

    public function assets()
    {
        return $this->belongsToMany(Asset::class, 'asset_asset_tag')->withTimestamps();
    }
}
