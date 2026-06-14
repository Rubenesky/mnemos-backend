<?php

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('editor solo ve sus propios assets en el listado', function () {
    $owner = User::factory()->create(['role' => 'editor']);
    $other = User::factory()->create(['role' => 'editor']);

    Asset::factory()->count(3)->create(['user_id' => $owner->id]);
    Asset::factory()->count(2)->create(['user_id' => $other->id]);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/assets');

    $response->assertStatus(200);
    expect($response->json('meta.total'))->toBe(3);
});

it('admin ve todos los assets en el listado', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $editor = User::factory()->create(['role' => 'editor']);

    Asset::factory()->count(3)->create(['user_id' => $admin->id]);
    Asset::factory()->count(2)->create(['user_id' => $editor->id]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/assets');

    $response->assertStatus(200);
    expect($response->json('meta.total'))->toBe(5);
});

it('editor no puede ver el asset de otro usuario', function () {
    $owner = User::factory()->create(['role' => 'editor']);
    $other = User::factory()->create(['role' => 'editor']);
    $asset = Asset::factory()->create(['user_id' => $other->id]);

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/assets/{$asset->id}")
        ->assertStatus(403);
});

it('editor puede ver su propio asset', function () {
    $owner = User::factory()->create(['role' => 'editor']);
    $asset = Asset::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/assets/{$asset->id}")
        ->assertStatus(200);
});

it('editor no puede editar el asset de otro usuario', function () {
    $owner = User::factory()->create(['role' => 'editor']);
    $other = User::factory()->create(['role' => 'editor']);
    $asset = Asset::factory()->create(['user_id' => $other->id]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/assets/{$asset->id}", ['title' => 'Intento de hack'])
        ->assertStatus(403);
});

it('admin puede ver el asset de cualquier usuario', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $other = User::factory()->create(['role' => 'editor']);
    $asset = Asset::factory()->create(['user_id' => $other->id]);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/assets/{$asset->id}")
        ->assertStatus(200);
});
