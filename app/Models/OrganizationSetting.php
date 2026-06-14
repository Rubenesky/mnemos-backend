<?php

// RJC

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the organization_settings table.
 *
 * Each row stores a single key/value configuration pair for the organization.
 * Only updated_at is tracked (no created_at).
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class OrganizationSetting extends Model
{
    /** @var string */
    protected $table = 'organization_settings';

    /** @var array<int, string> */
    protected $fillable = ['key', 'value', 'updated_at'];

    /**
     * Only track updated_at; suppress created_at entirely.
     */
    const CREATED_AT = null;

    const UPDATED_AT = 'updated_at';
}
