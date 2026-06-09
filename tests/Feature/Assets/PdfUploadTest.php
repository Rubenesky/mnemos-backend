<?php

use App\Models\Asset;
use App\Models\AssetMetadata;
use App\Models\User;
use App\Services\CloudinaryService;
use App\Services\DuplicateDetectionService;
use App\Services\GeminiService;
use App\Services\TextExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('upload')->andReturn([
            'public_id' => 'test/fake-pdf-id',
            'url'       => 'https://res.cloudinary.com/test/raw/upload/fake.pdf',
        ]);
    });

    $this->mock(GeminiService::class, function ($mock) {
        $mock->shouldReceive('generateAssetMetadata')->andReturn([
            'title'       => 'Documento de prueba',
            'description' => 'Contenido del PDF de prueba',
            'tags'        => ['documento', 'prueba'],
        ]);
        $mock->shouldReceive('generateAltText')->andReturn('');
    });

    $this->mock(DuplicateDetectionService::class, function ($mock) {
        $mock->shouldReceive('findSimilar')->andReturn([]);
    });

    $this->mock(TextExtractionService::class, function ($mock) {
        $mock->shouldReceive('isSupported')->with('application/pdf')->andReturn(true);
        $mock->shouldReceive('extract')->andReturn('Texto extraído del PDF de prueba.');
    });
});

it('sube un PDF válido y devuelve 201', function () {
    $user = User::factory()->create(['role' => 'editor']);
    $file = UploadedFile::fake()->create('informe.pdf', 500, 'application/pdf');

    $this->actingAs($user, 'sanctum')
         ->postJson('/api/assets', ['file' => $file])
         ->assertStatus(201)
         ->assertJsonStructure(['data' => ['id', 'url', 'status']]);
});

it('el asset PDF tiene metadatos generados por IA', function () {
    $user = User::factory()->create(['role' => 'editor']);
    $file = UploadedFile::fake()->create('informe.pdf', 500, 'application/pdf');

    $response = $this->actingAs($user, 'sanctum')
                     ->postJson('/api/assets', ['file' => $file])
                     ->assertStatus(201);

    $assetId = $response->json('data.id');
    expect(AssetMetadata::where('asset_id', $assetId)->exists())->toBeTrue();
});

it('rechaza un archivo disfrazado de PDF con 422', function () {
    $user = User::factory()->create(['role' => 'editor']);
    $file = UploadedFile::fake()->create('fake.pdf', 100, 'application/octet-stream');

    $this->actingAs($user, 'sanctum')
         ->postJson('/api/assets', ['file' => $file])
         ->assertStatus(422);
});

it('guarda el mime_type correcto para PDF', function () {
    $user = User::factory()->create(['role' => 'editor']);
    $file = UploadedFile::fake()->create('contrato.pdf', 200, 'application/pdf');

    $response = $this->actingAs($user, 'sanctum')
                     ->postJson('/api/assets', ['file' => $file])
                     ->assertStatus(201);

    $assetId = $response->json('data.id');
    $asset   = Asset::find($assetId);
    expect($asset->mime_type)->toBe('application/pdf');
});
