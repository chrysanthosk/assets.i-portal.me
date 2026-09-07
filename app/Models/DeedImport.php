<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeedImport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_EXTRACTED = 'extracted';

    public const STATUS_FAILED = 'failed';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'user_id', 'asset_id', 'original_name', 'disk', 'path', 'mime_type', 'size_bytes',
        'status', 'extracted', 'error', 'model', 'input_tokens', 'output_tokens',
    ];

    protected $casts = [
        'extracted' => 'array',
        'size_bytes' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
