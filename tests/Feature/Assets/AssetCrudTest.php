<?php

use App\Models\Asset;
use App\Models\AssetMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('listado devuelve paginación correcta', function () {
    $user = User::factory()->create();
    Asset::factory()->count(5)->create();

    $this->actingAs($user, 'sanctum')
         ->getJson('/api/assets')
         ->assertStatus(200)
         ->assertJsonStructure([
             'data',
             'meta' => ['total', 'per_page', 'current_page', 'last_page'],
         ]);
});

it('detalle de asset tiene estructura completa', function () {
    $user  = User::factory()->create();
    $asset = Asset::factory()->create(['user_id' => $user->id]);
    AssetMetadata::create([
        'asset_id'     => $asset->id,
        'title'        => 'Título de prueba',
        'description'  => 'Descripción',
        'tags'         => ['tag1'],
        'ai_generated' => true,
    ]);

    $this->actingAs($user, 'sanctum')
         ->getJson("/api/assets/{$asset->id}")
         ->assertStatus(200)
         ->assertJsonStructure([
             'data' => ['id', 'url', 'metadata', 'categories', 'uploaded_by'],
         ]);
});

it('editar metadatos actualiza título y tags', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $asset  = Asset::factory()->create();

    $this->actingAs($editor, 'sanctum')
         ->patchJson("/api/assets/{$asset->id}", [
             'title' => 'Título actualizado',
             'tags'  => 'nuevo, tag',
         ])
         ->assertStatus(200)
         ->assertJsonPath('data.metadata.title', 'Título actualizado');
});
