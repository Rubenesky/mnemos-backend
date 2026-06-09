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
            'public_id' => 'test/fake-docx-id',
            'url'       => 'https://res.cloudinary.com/test/raw/upload/fake.docx',
        ]);
    });

    $this->mock(GeminiService::class, function ($mock) {
        $mock->shouldReceive('generateAssetMetadata')->andReturn([
            'title'       => 'Documento Word de prueba',
            'description' => 'Contenido del Word de prueba',
            'tags'        => ['word', 'documento', 'prueba'],
        ]);
        $mock->shouldReceive('generateAltText')->andReturn('');
    });

    $this->mock(DuplicateDetectionService::class, function ($mock) {
        $mock->shouldReceive('findSimilar')->andReturn([]);
    });

    $this->mock(TextExtractionService::class, function ($mock) {
        $mock->shouldReceive('isSupported')
             ->andReturnUsing(fn($mime) => in_array($mime, [
                 'application/msword',
                 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
             ]));
        $mock->shouldReceive('extract')->andReturn('Texto extraído del documento Word de prueba.');
    });
});

it('sube un DOCX válido y devuelve 201', function () {
    $user = User::factory()->create(['role' => 'editor']);
    $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    $file = UploadedFile::fake()->create('informe.docx', 300, $mime);

    $this->actingAs($user, 'sanctum')
         ->postJson('/api/assets', ['file' => $file])
         ->assertStatus(201)
         ->assertJsonStructure(['data' => ['id', 'url', 'status']]);
});

it('sube un DOC válido y devuelve 201', function () {
    $user = User::factory()->create(['role' => 'editor']);
    $file = UploadedFile::fake()->create('informe.doc', 300, 'application/msword');

    $this->actingAs($user, 'sanctum')
         ->postJson('/api/assets', ['file' => $file])
         ->assertStatus(201)
         ->assertJsonStructure(['data' => ['id', 'url', 'status']]);
});

it('el asset DOCX tiene metadatos generados por IA', function () {
    $user = User::factory()->create(['role' => 'editor']);
    $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    $file = UploadedFile::fake()->create('memoria.docx', 400, $mime);

    $response = $this->actingAs($user, 'sanctum')
                     ->postJson('/api/assets', ['file' => $file])
                     ->assertStatus(201);

    $assetId = $response->json('data.id');
    expect(AssetMetadata::where('asset_id', $assetId)->exists())->toBeTrue();
});

it('guarda el mime_type correcto para DOCX', function () {
    $user = User::factory()->create(['role' => 'editor']);
    $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    $file = UploadedFile::fake()->create('contrato.docx', 200, $mime);

    $response = $this->actingAs($user, 'sanctum')
                     ->postJson('/api/assets', ['file' => $file])
                     ->assertStatus(201);

    $assetId = $response->json('data.id');
    $asset   = Asset::find($assetId);
    expect($asset->mime_type)->toBe($mime);
});
