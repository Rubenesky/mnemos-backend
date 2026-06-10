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
 * @property bool        $is_public       Whether this asset is visible on the public gallery.
 * @property string|null $alt_text        Auto-generated accessibility description for image assets.
 * @property string|null $ai_model        AI model used for the first generation (e.g. 'gemini-2.5-flash').
 * @property \Illuminate\Support\Carbon|null $ai_generated_at  When the first AI generation occurred.
 * @property string|null $ai_prompt       Summary of the last prompt sent to the AI model.
 * @property int|null    $ai_reviewed_by  ID of the user who reviewed the AI content.
 * @property \Illuminate\Support\Carbon|null $ai_reviewed_at   When the AI content was reviewed.
 *
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
        'alt_text',
        'is_press_kit',
        'press_kit_description',
        'is_emergency_kit',
        'extracted_text',
        'ai_model',
        'ai_generated_at',
        'ai_prompt',
        'ai_reviewed_by',
        'ai_reviewed_at',
    ];

    protected $casts = [
        'is_public'        => 'boolean',
        'is_press_kit'     => 'boolean',
        'is_emergency_kit' => 'boolean',
        'ai_generated_at'  => 'datetime',
        'ai_reviewed_at'   => 'datetime',
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

    /** Returns all AI generation events recorded for this asset. */
    public function aiGenerations(): HasMany
    {
        return $this->hasMany(AiGeneration::class);
    }

    /** Returns all view events recorded for this asset. */
    public function assetViews(): HasMany
    {
        return $this->hasMany(AssetView::class);
    }

    /** Returns the user who reviewed the AI-generated content, if any. */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_reviewed_by');
    }
}