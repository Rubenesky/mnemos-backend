<?php

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

// Public press room returns only is_press_kit=true AND is_public=true assets
test('public press room returns press kit assets', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $kit = Asset::factory()->create(['user_id' => $admin->id, 'is_press_kit' => true, 'is_public' => true]);
    Asset::factory()->create(['user_id' => $admin->id, 'is_press_kit' => false, 'is_public' => true]);
    Asset::factory()->create(['user_id' => $admin->id, 'is_press_kit' => true, 'is_public' => false]);

    $response = $this->getJson('/api/public/press-room');

    $response->assertOk()->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $kit->id);
});

// Admin can mark an asset as press kit
test('admin can toggle press kit', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    $response = $this->actingAs($admin)->patchJson("/api/assets/{$asset->id}/press-kit", [
        'is_press_kit'          => true,
        'press_kit_description' => 'Official press photos from the 2024 campaign.',
    ]);

    $response->assertOk()->assertJsonPath('is_press_kit', true);
    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'is_press_kit' => true]);
});

// Editor can toggle press kit
test('editor can toggle press kit', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $asset = Asset::factory()->create(['user_id' => $editor->id]);

    $response = $this->actingAs($editor)->patchJson("/api/assets/{$asset->id}/press-kit", [
        'is_press_kit' => true,
    ]);

    $response->assertOk();
});

// Viewer cannot toggle press kit
test('viewer cannot toggle press kit', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $asset = Asset::factory()->create(['user_id' => $viewer->id]);

    $response = $this->actingAs($viewer)->patchJson("/api/assets/{$asset->id}/press-kit", [
        'is_press_kit' => true,
    ]);

    $response->assertForbidden();
});

// Volunteer cannot toggle press kit
test('volunteer cannot toggle press kit', function () {
    $volunteer = User::factory()->create(['role' => 'volunteer']);
    $asset = Asset::factory()->create(['user_id' => $volunteer->id]);

    $response = $this->actingAs($volunteer)->patchJson("/api/assets/{$asset->id}/press-kit", [
        'is_press_kit' => true,
    ]);

    $response->assertForbidden();
});

// Unauthenticated user cannot toggle
test('unauthenticated user cannot toggle press kit', function () {
    $asset = Asset::factory()->create();

    $response = $this->patchJson("/api/assets/{$asset->id}/press-kit", [
        'is_press_kit' => true,
    ]);

    $response->assertUnauthorized();
});
