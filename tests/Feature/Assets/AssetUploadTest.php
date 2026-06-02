<?php

use App\Models\Asset;
use App\Models\AssetMetadata;
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
            'url'       => 'https://res.cloudinary.com/test/image/upload/fake.jpg',
        ]);
    });

    $this->mock(GeminiService::class, function ($mock) {
        $mock->shouldReceive('generateAssetMetadata')->andReturn([
            'title'       => 'Imagen de prueba',
            'description' => 'Descripción generada',
            'tags'        => ['prueba'],
        ]);
    });

    $this->mock(DuplicateDetectionService::class, function ($mock) {
        $mock->shouldReceive('findSimilar')->andReturn([]);
    });
});

it('sube un archivo jpg válido y devuelve 201', function () {
    $user = User::factory()->create(['role' => 'editor']);
    $file = UploadedFile::fake()->image('foto.jpg', 800, 600);

    $this->actingAs($user, 'sanctum')
         ->postJson('/api/assets', ['file' => $file])
         ->assertStatus(201)
         ->assertJsonStructure(['data' => ['id', 'url', 'status']]);
});

it('rechaza un archivo exe con 422', function () {
    $user = User::factory()->create(['role' => 'editor']);
    $file = UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream');

    $this->actingAs($user, 'sanctum')
         ->postJson('/api/assets', ['file' => $file])
         ->assertStatus(422);
});

it('rechaza archivos mayores de 10MB con 422', function () {
    $user = User::factory()->create(['role' => 'editor']);
    $file = UploadedFile::fake()->create('grande.jpg', 11000, 'image/jpeg');

    $this->actingAs($user, 'sanctum')
         ->postJson('/api/assets', ['file' => $file])
         ->assertStatus(422);
});

it('detecta duplicado exacto por hash y devuelve 409', function () {
    $user = User::factory()->create(['role' => 'editor']);
    $file = UploadedFile::fake()->image('foto.jpg');
    $hash = md5_file($file->getRealPath());

    Asset::factory()->create(['file_hash' => $hash]);

    $this->actingAs($user, 'sanctum')
         ->postJson('/api/assets', ['file' => $file])
         ->assertStatus(409)
         ->assertJsonStructure(['existing_asset']);
});

it('el asset procesado tiene metadatos generados', function () {
    $user = User::factory()->create(['role' => 'editor']);
    $file = UploadedFile::fake()->image('foto.jpg');

    $response = $this->actingAs($user, 'sanctum')
                     ->postJson('/api/assets', ['file' => $file])
                     ->assertStatus(201);

    $assetId = $response->json('data.id');
    expect(AssetMetadata::where('asset_id', $assetId)->exists())->toBeTrue();
});
