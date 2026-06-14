<?php

// RJC

use App\Models\Asset;
use App\Models\User;
use App\Services\CloudinaryService;
use App\Services\DuplicateDetectionService;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('upload')->andReturn([
            'public_id' => 'test/fake-id',
            'url' => 'https://res.cloudinary.com/test/image/upload/fake.jpg',
        ]);
    });

    $this->mock(GeminiService::class, function ($mock) {
        $mock->shouldReceive('generateAssetMetadata')->andReturn([
            'title' => 'Test title',
            'description' => 'Test description',
            'tags' => ['test'],
        ]);
        $mock->shouldReceive('generateAltText')->andReturn('');
    });

    $this->mock(DuplicateDetectionService::class, function ($mock) {
        $mock->shouldReceive('findSimilar')->andReturn([]);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Role access
// ─────────────────────────────────────────────────────────────────────────────

it('volunteer cannot use bulk upload', function () {
    $volunteer = User::factory()->create(['role' => 'volunteer']);

    $this->actingAs($volunteer, 'sanctum')
        ->postJson('/api/assets/bulk', ['files' => [UploadedFile::fake()->image('a.jpg')]])
        ->assertForbidden();
});

it('viewer cannot use bulk upload', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);

    $this->actingAs($viewer, 'sanctum')
        ->postJson('/api/assets/bulk', ['files' => [UploadedFile::fake()->image('a.jpg')]])
        ->assertForbidden();
});

// ─────────────────────────────────────────────────────────────────────────────
// Successful uploads
// ─────────────────────────────────────────────────────────────────────────────

it('admin can bulk upload multiple files', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $files = [
        UploadedFile::fake()->image('photo1.jpg', 100, 100),
        UploadedFile::fake()->image('photo2.jpg', 200, 200),
    ];

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/assets/bulk', ['files' => $files])
        ->assertOk()
        ->assertJsonStructure(['results'])
        ->assertJsonCount(2, 'results');

    expect($response->json('results.0.status'))->toBe('success');
    expect($response->json('results.1.status'))->toBe('success');
    expect(Asset::count())->toBe(2);
});

it('editor can bulk upload multiple files', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $files = [
        UploadedFile::fake()->image('img1.jpg', 100, 100),
        UploadedFile::fake()->image('img2.jpg', 200, 200),
        UploadedFile::fake()->image('img3.jpg', 300, 300),
    ];

    $this->actingAs($editor, 'sanctum')
        ->postJson('/api/assets/bulk', ['files' => $files])
        ->assertOk()
        ->assertJsonCount(3, 'results');

    expect(Asset::count())->toBe(3);
});

// ─────────────────────────────────────────────────────────────────────────────
// Validation
// ─────────────────────────────────────────────────────────────────────────────

it('rejects more than 20 files with 422', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $files = array_fill(0, 21, UploadedFile::fake()->image('img.jpg'));

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/assets/bulk', ['files' => $files])
        ->assertUnprocessable();
});

it('rejects empty files array with 422', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/assets/bulk', ['files' => []])
        ->assertUnprocessable();
});

// ─────────────────────────────────────────────────────────────────────────────
// Per-file error handling
// ─────────────────────────────────────────────────────────────────────────────

it('invalid file returns error while valid files still succeed', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $validJpg = UploadedFile::fake()->image('good.jpg', 100, 100);
    $badExe = UploadedFile::fake()->create('bad.exe', 100, 'application/octet-stream');
    $validPng = UploadedFile::fake()->image('also-good.png', 200, 200);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/assets/bulk', ['files' => [$validJpg, $badExe, $validPng]])
        ->assertOk()
        ->assertJsonCount(3, 'results');

    expect($response->json('results.0.status'))->toBe('success');
    expect($response->json('results.1.status'))->toBe('error');
    expect($response->json('results.2.status'))->toBe('success');
    expect(Asset::count())->toBe(2);
});

// ─────────────────────────────────────────────────────────────────────────────
// Response format
// ─────────────────────────────────────────────────────────────────────────────

it('response has correct per-file structure for success and error', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $valid = UploadedFile::fake()->image('photo.jpg', 150, 150);
    $invalid = UploadedFile::fake()->create('doc.exe', 100, 'application/octet-stream');

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/assets/bulk', ['files' => [$valid, $invalid]])
        ->assertOk();

    $results = $response->json('results');

    // Successful result has integer asset_id and null error
    expect($results[0])->toMatchArray([
        'filename' => 'photo.jpg',
        'status' => 'success',
        'error' => null,
    ]);
    expect($results[0]['asset_id'])->toBeInt();

    // Error result has null asset_id and a non-empty error message
    expect($results[1])->toMatchArray([
        'filename' => 'doc.exe',
        'status' => 'error',
        'asset_id' => null,
    ]);
    expect($results[1]['error'])->toBeString()->not->toBeEmpty();
});
