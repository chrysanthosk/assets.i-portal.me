<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDocument extends Model
{
    /**
     * Document classifications (kept in code so reminders can rely on them).
     */
    public const TYPES = [
        'Title Deed',
        'Contract',
        'Insurance',
        'Mortgage',
        'Certificate',
        'Invoice',
        'Other',
    ];

    protected $fillable = [
        'asset_id',
        'uploaded_by',
        'title',
        'doc_type',
        'expires_at',
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
        'expires_at' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expires_at !== null
            && ! $this->expires_at->isPast()
            && $this->expires_at->lessThanOrEqualTo(now()->addDays($days));
    }
}
