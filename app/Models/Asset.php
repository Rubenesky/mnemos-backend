<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Represents a digital asset (image, video, document, or other file) stored in the system.
 *
 * @property bool $is_public Whether this asset is visible on the public gallery (no auth required).
 * @package App\Models
 */
class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_name',
        'filename',
        'mime_type',
        'size',
        'path',
        'file_hash',
        'cloudinary_public_id',
        'cloudinary_url',
        'status',
        'is_public',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function metadata(): HasOne
    {
        return $this->hasOne(AssetMetadata::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /** Returns all consent records associated with this asset. */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }
}