<?php

// RJC

use App\Models\OrganizationSetting;
use App\Models\User;
use App\Services\CloudinaryService;
use App\Services\OrganizationSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/**
 * Feature tests for OrganizationSettingsService and the admin settings endpoints.
 */

// Controller constructor injects CloudinaryService, so Laravel resolves it on
// every route in this file — even ones that never touch the SDK. The real
// constructor calls Cloudinary\Configuration::validate() and throws when
// CLOUDINARY_* env vars are missing. A default mock keeps non-upload tests
// from blowing up; tests that exercise upload override it with their own.
beforeEach(function () {
    $this->mock(CloudinaryService::class);
});
test('get and set work correctly', function () {
    $service = app(OrganizationSettingsService::class);
    $service->set('org_name', 'Test Org');
    expect($service->get('org_name'))->toBe('Test Org');
    expect($service->get('missing_key', 'default'))->toBe('default');
});

test('admin can get all settings', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    OrganizationSetting::create(['key' => 'org_name', 'value' => 'Test Org', 'updated_at' => now()]);

    $response = $this->actingAs($admin)->getJson('/api/admin/settings');
    $response->assertOk()->assertJsonPath('org_name', 'Test Org');
});

test('admin can update multiple settings', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    OrganizationSetting::insert([
        ['key' => 'org_name',   'value' => 'Old', 'updated_at' => now()],
        ['key' => 'org_locale', 'value' => 'es',  'updated_at' => now()],
    ]);

    $response = $this->actingAs($admin)->patchJson('/api/admin/settings', [
        'org_name' => 'New Org',
        'org_locale' => 'en',
    ]);

    $response->assertOk()
        ->assertJsonPath('org_name', 'New Org')
        ->assertJsonPath('org_locale', 'en');

    $this->assertDatabaseHas('organization_settings', ['key' => 'org_name',   'value' => 'New Org']);
    $this->assertDatabaseHas('organization_settings', ['key' => 'org_locale', 'value' => 'en']);
});

test('public gallery uses real org_name', function () {
    OrganizationSetting::insert([['key' => 'org_name', 'value' => 'ONG Real', 'updated_at' => now()]]);

    $response = $this->getJson('/api/public/collections');
    $response->assertOk()->assertJsonPath('org_name', 'ONG Real');
});

test('patch accepts null and empty-string optional fields', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    OrganizationSetting::insert([
        ['key' => 'org_name',        'value' => 'Test',               'updated_at' => now()],
        ['key' => 'org_description', 'value' => 'Old desc',           'updated_at' => now()],
        ['key' => 'org_website',     'value' => 'https://example.com', 'updated_at' => now()],
        ['key' => 'org_email',       'value' => 'a@b.com',            'updated_at' => now()],
        ['key' => 'org_locale',      'value' => 'es',                 'updated_at' => now()],
    ]);

    $response = $this->actingAs($admin)->patchJson('/api/admin/settings', [
        'org_name' => 'Test',
        'org_description' => '',    // empty string — must not 422
        'org_website' => null,  // null — must not 422
        'org_email' => null,  // null — must not 422
        'org_locale' => 'es',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('organization_settings', ['key' => 'org_description', 'value' => '']);
});

test('admin can upload logo (mocked cloudinary)', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    OrganizationSetting::insert([['key' => 'org_logo_url', 'value' => '', 'updated_at' => now()]]);

    $this->mock(CloudinaryService::class)
        ->shouldReceive('upload')
        ->once()
        ->andReturn([
            'url' => 'https://res.cloudinary.com/test/logo.jpg',
            'public_id' => 'test/logo',
            'format' => 'jpg',
        ]);

    $response = $this->actingAs($admin)
        ->postJson('/api/admin/settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.jpg', 100, 100),
        ]);

    $response->assertOk()->assertJsonPath('org_logo_url', 'https://res.cloudinary.com/test/logo.jpg');
    $this->assertDatabaseHas('organization_settings', [
        'key' => 'org_logo_url',
        'value' => 'https://res.cloudinary.com/test/logo.jpg',
    ]);
});
