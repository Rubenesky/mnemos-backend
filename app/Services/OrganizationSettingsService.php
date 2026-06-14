<?php

// RJC

namespace App\Services;

use App\Models\OrganizationSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Provides cached read/write access to the organization_settings table.
 *
 * All reads are served from a 1-hour cache keyed by CACHE_KEY.
 * Any write invalidates the cache immediately.
 */
class OrganizationSettingsService
{
    private const CACHE_KEY = 'org_settings';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get a single setting value by key, with optional default.
     *
     * @param  mixed  $default  Returned when the key does not exist
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->getAll();

        return $all[$key] ?? $default;
    }

    /**
     * Persist a single key/value pair and invalidate the settings cache.
     */
    public function set(string $key, string $value): void
    {
        OrganizationSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()]
        );
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Return all settings as an associative array keyed by setting key.
     * Result is cached for CACHE_TTL seconds.
     *
     * @return array<string, string|null>
     */
    public function getAll(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return OrganizationSetting::pluck('value', 'key')->toArray();
        });
    }
}
