<?php

use App\Models\Asset;
use App\Models\User;
use App\Services\EmergencyKitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

// Toggle tests
test('admin can mark asset as emergency kit', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    $response = $this->actingAs($admin)
        ->patchJson("/api/assets/{$asset->id}/emergency-kit", ['is_emergency_kit' => true]);

    $response->assertOk()->assertJsonPath('is_emergency_kit', true);
    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'is_emergency_kit' => true]);
});

test('editor can mark their own asset as emergency kit', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $asset  = Asset::factory()->create(['user_id' => $editor->id]);

    $response = $this->actingAs($editor)
        ->patchJson("/api/assets/{$asset->id}/emergency-kit", ['is_emergency_kit' => true]);

    $response->assertOk();
});

test('editor cannot mark another users asset as emergency kit', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $other  = User::factory()->create(['role' => 'editor']);
    $asset  = Asset::factory()->create(['user_id' => $other->id]);

    $response = $this->actingAs($editor)
        ->patchJson("/api/assets/{$asset->id}/emergency-kit", ['is_emergency_kit' => true]);

    $response->assertForbidden();
});

test('viewer cannot toggle emergency kit', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $asset  = Asset::factory()->create(['user_id' => $viewer->id]);

    $response = $this->actingAs($viewer)
        ->patchJson("/api/assets/{$asset->id}/emergency-kit", ['is_emergency_kit' => true]);

    $response->assertForbidden();
});

test('unauthenticated user cannot toggle emergency kit', function () {
    $asset = Asset::factory()->create();

    $response = $this->patchJson("/api/assets/{$asset->id}/emergency-kit", ['is_emergency_kit' => true]);

    $response->assertUnauthorized();
});

// Download tests
test('admin can download emergency kit zip', function () {
    Http::fake(['*' => Http::response('fake-file-content', 200)]);

    $admin = User::factory()->create(['role' => 'admin']);
    Asset::factory()->create([
        'user_id'          => $admin->id,
        'is_emergency_kit' => true,
        'cloudinary_url'   => 'https://fake-cloudinary.com/image.jpg',
    ]);

    $response = $this->actingAs($admin)->get('/api/emergency-kit/download');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/zip');
});

test('editor cannot download emergency kit zip', function () {
    $editor = User::factory()->create(['role' => 'editor']);

    $response = $this->actingAs($editor)->get('/api/emergency-kit/download');

    $response->assertForbidden();
});

test('unauthenticated user cannot download emergency kit', function () {
    $response = $this->getJson('/api/emergency-kit/download');

    $response->assertUnauthorized();
});

// Service unit test
test('emergency kit service always includes manifest csv', function () {
    Http::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    Asset::factory()->create(['user_id' => $admin->id, 'is_emergency_kit' => true]);

    $service = app(EmergencyKitService::class);
    $path    = $service->build();

    $this->assertFileExists($path);

    $zip = new ZipArchive();
    $zip->open($path);
    $this->assertNotFalse($zip->locateName('manifest.csv'));
    $zip->close();

    @unlink($path);
});

test('emergency kit service limits assets to 50 maximum', function () {
    Http::fake(['*' => Http::response('fake-binary-content', 200)]);

    $admin = User::factory()->create(['role' => 'admin']);
    Asset::factory()->count(55)->create([
        'user_id'          => $admin->id,
        'is_emergency_kit' => true,
        'cloudinary_url'   => 'https://fake-cloudinary.com/file.jpg',
        'original_name'    => 'photo.jpg',
    ]);

    $service = app(EmergencyKitService::class);
    $path    = $service->build();

    $this->assertFileExists($path);

    $zip = new ZipArchive();
    $zip->open($path);
    // At most 50 asset entries + 1 manifest.csv = 51 entries
    $this->assertLessThanOrEqual(51, $zip->numFiles);
    $this->assertNotFalse($zip->locateName('manifest.csv'));
    $zip->close();

    @unlink($path);
});

test('emergency kit service cleans up temp asset files after build', function () {
    Http::fake(['*' => Http::response('content', 200)]);

    $admin = User::factory()->create(['role' => 'admin']);
    Asset::factory()->count(3)->create([
        'user_id'          => $admin->id,
        'is_emergency_kit' => true,
        'cloudinary_url'   => 'https://fake-cloudinary.com/file.jpg',
    ]);

    $tmpsBefore = glob(sys_get_temp_dir() . '/mnemos_asset_*') ?: [];

    $service = app(EmergencyKitService::class);
    $path    = $service->build();

    $tmpsAfter = glob(sys_get_temp_dir() . '/mnemos_asset_*') ?: [];
    $this->assertCount(count($tmpsBefore), $tmpsAfter, 'Temp asset files were not cleaned up after build()');

    @unlink($path);
});
