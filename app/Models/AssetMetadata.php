<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores AI-generated and user-edited metadata (title, description, tags) for a digital asset.
 *
 * @package App\Models
 */
class AssetMetadata extends Model
{
    protected $fillable = [
        'asset_id',
        'title',
        'description',
        'tags',
        'ai_generated',
    ];

    // Automatically cast the JSON tags field to a PHP array
    protected $casts = [
        'tags' => 'array',
        'ai_generated' => 'boolean',
    ];

    // Metadata belongs to one asset
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}