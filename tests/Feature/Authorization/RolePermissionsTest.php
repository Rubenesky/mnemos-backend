<?php

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('admin puede borrar un asset', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create();

    $this->actingAs($admin, 'sanctum')
         ->deleteJson("/api/assets/{$asset->id}")
         ->assertStatus(200);
});

it('editor no puede borrar un asset', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $asset  = Asset::factory()->create();

    $this->actingAs($editor, 'sanctum')
         ->deleteJson("/api/assets/{$asset->id}")
         ->assertStatus(403);
});

it('viewer no puede borrar un asset', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $asset  = Asset::factory()->create();

    $this->actingAs($viewer, 'sanctum')
         ->deleteJson("/api/assets/{$asset->id}")
         ->assertStatus(403);
});

it('viewer no puede editar metadatos', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $asset  = Asset::factory()->create();

    $this->actingAs($viewer, 'sanctum')
         ->patchJson("/api/assets/{$asset->id}", ['title' => 'Nuevo título'])
         ->assertStatus(403);
});

it('editor puede editar metadatos', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $asset  = Asset::factory()->create();

    $this->actingAs($editor, 'sanctum')
         ->patchJson("/api/assets/{$asset->id}", [
             'title' => 'Título editado',
             'tags'  => 'tag1, tag2',
         ])
         ->assertStatus(200)
         ->assertJsonPath('data.metadata.title', 'Título editado');
});
