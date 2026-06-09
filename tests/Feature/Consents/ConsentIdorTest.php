<?php

use App\Models\Asset;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('editor cannot list consents belonging to another user\'s asset', function () {
    $editorA = User::factory()->create(['role' => 'editor']);
    $editorB = User::factory()->create(['role' => 'editor']);

    $asset = Asset::factory()->create(['user_id' => $editorA->id]);
    Consent::factory()->count(2)->create(['asset_id' => $asset->id]);

    $response = $this->actingAs($editorB, 'sanctum')
        ->getJson("/api/consents?asset_id={$asset->id}");

    $response->assertStatus(200);
    expect($response->json('data.data'))->toHaveCount(0);
});

it('editor can list their own consents', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $asset  = Asset::factory()->create(['user_id' => $editor->id]);
    Consent::factory()->count(2)->create(['asset_id' => $asset->id]);

    $response = $this->actingAs($editor, 'sanctum')
        ->getJson('/api/consents');

    $response->assertStatus(200);
    expect($response->json('data.data'))->toHaveCount(2);
});

it('editor cannot view a consent belonging to another user\'s asset', function () {
    $editorA = User::factory()->create(['role' => 'editor']);
    $editorB = User::factory()->create(['role' => 'editor']);

    $asset   = Asset::factory()->create(['user_id' => $editorA->id]);
    $consent = Consent::factory()->create(['asset_id' => $asset->id]);

    $this->actingAs($editorB, 'sanctum')
        ->getJson("/api/consents/{$consent->id}")
        ->assertStatus(403);
});

it('editor can view their own consent', function () {
    $editor  = User::factory()->create(['role' => 'editor']);
    $asset   = Asset::factory()->create(['user_id' => $editor->id]);
    $consent = Consent::factory()->create(['asset_id' => $asset->id]);

    $this->actingAs($editor, 'sanctum')
        ->getJson("/api/consents/{$consent->id}")
        ->assertStatus(200);
});

it('editor cannot update a consent belonging to another user\'s asset', function () {
    $editorA = User::factory()->create(['role' => 'editor']);
    $editorB = User::factory()->create(['role' => 'editor']);

    $asset   = Asset::factory()->create(['user_id' => $editorA->id]);
    $consent = Consent::factory()->create(['asset_id' => $asset->id]);

    $this->actingAs($editorB, 'sanctum')
        ->putJson("/api/consents/{$consent->id}", ['status' => 'obtained'])
        ->assertStatus(403);
});

it('admin can list all consents regardless of asset owner', function () {
    $admin   = User::factory()->create(['role' => 'admin']);
    $editorA = User::factory()->create(['role' => 'editor']);
    $editorB = User::factory()->create(['role' => 'editor']);

    $assetA = Asset::factory()->create(['user_id' => $editorA->id]);
    $assetB = Asset::factory()->create(['user_id' => $editorB->id]);
    Consent::factory()->create(['asset_id' => $assetA->id]);
    Consent::factory()->create(['asset_id' => $assetB->id]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/consents');

    $response->assertStatus(200);
    expect(count($response->json('data.data')))->toBeGreaterThanOrEqual(2);
});

it('admin can view any consent', function () {
    $admin   = User::factory()->create(['role' => 'admin']);
    $editor  = User::factory()->create(['role' => 'editor']);
    $asset   = Asset::factory()->create(['user_id' => $editor->id]);
    $consent = Consent::factory()->create(['asset_id' => $asset->id]);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/consents/{$consent->id}")
        ->assertStatus(200);
});

it('admin can update any consent', function () {
    $admin   = User::factory()->create(['role' => 'admin']);
    $editor  = User::factory()->create(['role' => 'editor']);
    $asset   = Asset::factory()->create(['user_id' => $editor->id]);
    $consent = Consent::factory()->create(['asset_id' => $asset->id, 'status' => 'pending']);

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson("/api/consents/{$consent->id}", ['status' => 'obtained']);

    $response->assertStatus(200);
    expect($consent->fresh()->status)->toBe('obtained');
});

it('editor cannot bypass publication check for another user\'s asset', function () {
    $editorA = User::factory()->create(['role' => 'editor']);
    $editorB = User::factory()->create(['role' => 'editor']);

    $asset = Asset::factory()->create(['user_id' => $editorA->id]);

    $this->actingAs($editorB, 'sanctum')
        ->getJson("/api/assets/{$asset->id}/publication-check")
        ->assertStatus(403);
});
