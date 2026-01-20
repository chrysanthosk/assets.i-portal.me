<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDocument extends Model
{
    protected $fillable = [
        'asset_id',
        'uploaded_by',
        'title',
        'original_name',
        'disk',
        'path',
        'file_path',   // legacy support
        'mime_type',
        'mime',        // legacy support
        'size_bytes',
        'size',        // legacy support
        'notes',
    ];

    protected $casts = [
        'asset_id' => 'integer',
        'uploaded_by' => 'integer',
        'size_bytes' => 'integer',
        'size' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
