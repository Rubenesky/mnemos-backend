<?php

// RJC

namespace Database\Seeders;

use App\Models\OrganizationSetting;
use Illuminate\Database\Seeder;

/**
 * Seeds the organization_settings table with default key/value pairs.
 *
 * Safe to run multiple times — uses updateOrCreate so existing values are preserved.
 */
class OrganizationSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            'org_name'        => 'Mi Organización',
            'org_description' => '',
            'org_website'     => '',
            'org_email'       => '',
            'org_logo_url'    => '',
            'org_locale'      => 'es',
        ];

        foreach ($defaults as $key => $value) {
            OrganizationSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }
    }
}
