<?php

// RJC

use App\Models\AiGeneration;
use App\Models\Asset;
use App\Models\AssetMetadata;
use App\Models\User;
use App\Services\AIProvenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Feature tests for AI Provenance endpoints and AIProvenanceService.
 *
 * GET  /api/assets/{id}/provenance
 * POST /api/assets/{id}/provenance/review
 */

// ─────────────────────────────────────────────────────────────────────────────
// AIProvenanceService unit-style tests
// ─────────────────────────────────────────────────────────────────────────────

test('recordGeneration creates an ai_generations row', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    app(AIProvenanceService::class)
        ->recordGeneration($asset, 'description', 'gemini-2.5-flash', 'Test prompt', 'Test response');

    expect(AiGeneration::count())->toBe(1);

    $gen = AiGeneration::first();
    expect($gen->asset_id)->toBe($asset->id)
        ->and($gen->generation_type)->toBe('description')
        ->and($gen->model)->toBe('gemini-2.5-flash')
        ->and($gen->prompt_summary)->toBe('Test prompt')
        ->and($gen->response_preview)->toBe('Test response');
});

test('recordGeneration stamps ai_model and ai_generated_at on first call', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    expect($asset->ai_generated_at)->toBeNull();

    app(AIProvenanceService::class)
        ->recordGeneration($asset, 'description', 'gemini-2.5-flash', 'Prompt', 'Response');

    $fresh = $asset->fresh();
    expect($fresh->ai_model)->toBe('gemini-2.5-flash')
        ->and($fresh->ai_generated_at)->not->toBeNull();
});

test('recordGeneration does not overwrite ai_generated_at on subsequent calls', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    $service = app(AIProvenanceService::class);
    $service->recordGeneration($asset, 'description', 'gemini-2.5-flash', 'First', 'First response');
    $firstTimestamp = $asset->fresh()->ai_generated_at;

    $service->recordGeneration($asset->fresh(), 'alt_text', 'gemini-2.5-flash', 'Second', 'Second response');

    expect($asset->fresh()->ai_generated_at->toISOString())->toBe($firstTimestamp->toISOString());
    expect(AiGeneration::count())->toBe(2);
});

test('markReviewed stamps ai_reviewed_by and ai_reviewed_at on asset', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $editor = User::factory()->create(['role' => 'editor']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    app(AIProvenanceService::class)->markReviewed($asset, $editor->id);

    $fresh = $asset->fresh();
    expect($fresh->ai_reviewed_by)->toBe($editor->id)
        ->and($fresh->ai_reviewed_at)->not->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/assets/{id}/provenance — structure and access control
// ─────────────────────────────────────────────────────────────────────────────

test('unauthenticated request to provenance returns 401', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    $this->getJson("/api/assets/{$asset->id}/provenance")->assertStatus(401);
});

test('provenance endpoint returns correct json structure', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    AssetMetadata::create([
        'asset_id' => $asset->id,
        'title' => 'Test',
        'description' => 'Desc',
        'tags' => [],
        'ai_generated' => true,
    ]);

    $this->actingAs($admin)
        ->getJson("/api/assets/{$asset->id}/provenance")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'ai_generated',
                'ai_model',
                'ai_generated_at',
                'ai_reviewed_by',
                'ai_reviewed_at',
                'generations',
            ],
        ]);
});

test('provenance endpoint returns full generation history', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    $service = app(AIProvenanceService::class);
    $service->recordGeneration($asset, 'description', 'gemini-2.5-flash', 'Prompt A', 'Response A');
    $service->recordGeneration($asset->fresh(), 'alt_text', 'gemini-2.5-flash', 'Prompt B', 'Response B');

    $response = $this->actingAs($admin)
        ->getJson("/api/assets/{$asset->id}/provenance")
        ->assertOk();

    expect($response->json('data.generations'))->toHaveCount(2)
        ->and($response->json('data.ai_model'))->toBe('gemini-2.5-flash')
        ->and($response->json('data.ai_generated_at'))->not->toBeNull();
});

test('editor cannot view provenance of another users asset', function () {
    $editorA = User::factory()->create(['role' => 'editor']);
    $editorB = User::factory()->create(['role' => 'editor']);
    $asset = Asset::factory()->create(['user_id' => $editorA->id]);

    $this->actingAs($editorB)
        ->getJson("/api/assets/{$asset->id}/provenance")
        ->assertStatus(403);
});

test('admin can view provenance of any asset', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $editor = User::factory()->create(['role' => 'editor']);
    $asset = Asset::factory()->create(['user_id' => $editor->id]);

    $this->actingAs($admin)
        ->getJson("/api/assets/{$asset->id}/provenance")
        ->assertOk();
});

test('editor can view provenance of own asset', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $asset = Asset::factory()->create(['user_id' => $editor->id]);

    $this->actingAs($editor)
        ->getJson("/api/assets/{$asset->id}/provenance")
        ->assertOk();
});

// ─────────────────────────────────────────────────────────────────────────────
// POST /api/assets/{id}/provenance/review
// ─────────────────────────────────────────────────────────────────────────────

test('admin can mark any asset as reviewed', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/assets/{$asset->id}/provenance/review")
        ->assertOk()
        ->assertJsonStructure(['data' => ['ai_reviewed_by', 'ai_reviewed_at']]);

    expect($asset->fresh()->ai_reviewed_by)->toBe($admin->id);
});

test('editor can mark their own asset as reviewed', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $asset = Asset::factory()->create(['user_id' => $editor->id]);

    $this->actingAs($editor)
        ->postJson("/api/assets/{$asset->id}/provenance/review")
        ->assertOk();

    expect($asset->fresh()->ai_reviewed_by)->toBe($editor->id);
});

test('editor cannot mark another editors asset as reviewed', function () {
    $editorA = User::factory()->create(['role' => 'editor']);
    $editorB = User::factory()->create(['role' => 'editor']);
    $asset = Asset::factory()->create(['user_id' => $editorA->id]);

    $this->actingAs($editorB)
        ->postJson("/api/assets/{$asset->id}/provenance/review")
        ->assertStatus(403);
});

test('viewer cannot mark asset as reviewed', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $asset = Asset::factory()->create(['user_id' => $viewer->id]);

    $this->actingAs($viewer)
        ->postJson("/api/assets/{$asset->id}/provenance/review")
        ->assertStatus(403);
});

test('volunteer cannot mark asset as reviewed', function () {
    $volunteer = User::factory()->create(['role' => 'volunteer']);
    $asset = Asset::factory()->create(['user_id' => $volunteer->id]);

    $this->actingAs($volunteer)
        ->postJson("/api/assets/{$asset->id}/provenance/review")
        ->assertStatus(403);
});
