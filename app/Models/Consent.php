<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks GDPR consent for an asset on a per-person basis.
 *
 * @package App\Models
 * @property int $id
 * @property int $asset_id
 * @property string $person_name
 * @property \Illuminate\Support\Carbon $consent_date
 * @property string $consent_type
 * @property string $status  obtained|pending|denied
 * @property string|null $document_path
 * @property string|null $notes
 */
class Consent extends Model
{
    protected $fillable = [
        'asset_id',
        'person_name',
        'consent_date',
        'consent_type',
        'status',
        'document_path',
        'notes',
    ];

    protected $casts = [
        'consent_date' => 'date',
    ];

    /** Returns the asset this consent record belongs to. */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
