<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Represents a hierarchical taxonomy category used to organize digital assets.
 *
 * @package App\Models
 */
class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
    ];

    // A category may belong to a parent category
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // A category may have child subcategories
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // A category can contain many assets
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class);
    }
}