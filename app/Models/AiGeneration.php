<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a single AI generation event for an asset.
 *
 * Records are immutable (write-once): there is no updated_at column.
 * Each row captures the model used, a summary of the prompt, and a
 * preview of the response to satisfy EU AI Act traceability requirements.
 *
 * @property int         $id
 * @property int         $asset_id
 * @property string      $generation_type  alt_text|tags|description|report|story
 * @property string      $model
 * @property string      $prompt_summary
 * @property string      $response_preview
 * @property int|null    $user_id
 * @property \Illuminate\Support\Carbon $created_at
 *
 * @package App\Models
 * @author  RJC
 */
class AiGeneration extends Model
{
    /**
     * Disable updated_at — these records are immutable.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'asset_id',
        'generation_type',
        'model',
        'prompt_summary',
        'response_preview',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Returns the asset this generation event belongs to.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Returns the user who triggered this generation, or null for system-initiated events.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
