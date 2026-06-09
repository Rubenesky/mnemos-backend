<?php

use App\Models\Asset;
use App\Models\User;
use App\Services\NaturalLanguageSearchService;

/**
 * IDOR tests for POST /api/search (SearchApiController::search).
 *
 * NaturalLanguageSearchService makes real Gemini HTTP calls, so it is
 * mocked in every test to return a deterministic filter array.
 */

it('editor only sees their own assets in NL search results', function () {
    $editorA = User::factory()->create(['role' => 'editor']);
    $editorB = User::factory()->create(['role' => 'editor']);

    // Asset owned by editor A — title contains "tiger"
    $assetA = Asset::factory()->create(['user_id' => $editorA->id, 'original_name' => 'tiger_photo_a.jpg']);
    $assetA->metadata()->create([
        'title'        => 'tiger photo',
        'description'  => 'A photo of a tiger',
        'tags'         => json_encode(['tiger', 'wildlife']),
        'ai_generated' => false,
    ]);

    // Asset owned by editor B — also title contains "tiger"
    $assetB = Asset::factory()->create(['user_id' => $editorB->id, 'original_name' => 'tiger_photo_b.jpg']);
    $assetB->metadata()->create([
        'title'        => 'tiger photo',
        'description'  => 'Another tiger photo',
        'tags'         => json_encode(['tiger', 'nature']),
        'ai_generated' => false,
    ]);

    // Mock the AI service to avoid real Gemini calls
    $this->mock(NaturalLanguageSearchService::class, function ($mock) {
        $mock->shouldReceive('parseQuery')
             ->once()
             ->andReturn(['search' => 'tiger']);
    });

    $response = $this->actingAs($editorA, 'sanctum')
                     ->postJson('/api/search', ['query' => 'tiger photos']);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($assetA->id)
                ->not->toContain($assetB->id);

    expect($response->json('meta.total'))->toBe(1);
});

it('admin sees all assets in NL search', function () {
    $admin   = User::factory()->create(['role' => 'admin']);
    $editorA = User::factory()->create(['role' => 'editor']);
    $editorB = User::factory()->create(['role' => 'editor']);

    $assetA = Asset::factory()->create(['user_id' => $editorA->id, 'original_name' => 'tiger_a.jpg']);
    $assetA->metadata()->create([
        'title'        => 'tiger photo',
        'description'  => 'Tiger by editor A',
        'tags'         => json_encode(['tiger']),
        'ai_generated' => false,
    ]);

    $assetB = Asset::factory()->create(['user_id' => $editorB->id, 'original_name' => 'tiger_b.jpg']);
    $assetB->metadata()->create([
        'title'        => 'tiger photo',
        'description'  => 'Tiger by editor B',
        'tags'         => json_encode(['tiger']),
        'ai_generated' => false,
    ]);

    // Mock the AI service
    $this->mock(NaturalLanguageSearchService::class, function ($mock) {
        $mock->shouldReceive('parseQuery')
             ->once()
             ->andReturn(['search' => 'tiger']);
    });

    $response = $this->actingAs($admin, 'sanctum')
                     ->postJson('/api/search', ['query' => 'tiger photos']);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($assetA->id)
                ->toContain($assetB->id);

    expect($response->json('meta.total'))->toBe(2);
});
