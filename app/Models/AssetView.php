<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records a single view event for an asset.
 *
 * Records are immutable (write-once). The `viewed_at` column acts as
 * created_at; there is no updated_at. IPs are stored as SHA-256 hashes
 * so the original address is never persisted (GDPR compliance).
 *
 * @property int $id
 * @property int $asset_id
 * @property \Illuminate\Support\Carbon $viewed_at
 * @property string|null $ip_hash
 *
 * @author  RJC
 */
class AssetView extends Model
{
    /**
     * Map created_at → viewed_at so Laravel auto-populates the column on create.
     */
    public const CREATED_AT = 'viewed_at';

    /**
     * No updated_at — these records are immutable.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'asset_id',
        'ip_hash',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    /**
     * Returns the asset this view event belongs to.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
