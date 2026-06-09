<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks GDPR consent for an asset on a per-person basis.
 *
 * @package App\Models
 * @property int $id
 * @property int $asset_id
 * @property string $person_name
 * @property string|null $person_email
 * @property \Illuminate\Support\Carbon $consent_date
 * @property string $consent_type
 * @property string $status  obtained|pending|denied
 * @property string|null $document_path
 * @property string|null $notes
 * @property string|null $token
 * @property \Illuminate\Support\Carbon|null $token_expires_at
 * @property \Illuminate\Support\Carbon|null $responded_at
 */
class Consent extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'person_name',
        'person_email',
        'consent_date',
        'consent_type',
        'status',
        'document_path',
        'notes',
        'token',
        'token_expires_at',
        'responded_at',
    ];

    protected $casts = [
        'consent_date'     => 'date',
        'token_expires_at' => 'datetime',
        'responded_at'     => 'datetime',
    ];

    /** Returns the asset this consent record belongs to. */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
