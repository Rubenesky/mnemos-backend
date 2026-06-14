<?php

use App\Jobs\ProcessAssetAI;
use App\Models\Asset;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('gemini timeout leaves asset in error status and does not throw uncaptured exception', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    $this->mock(GeminiService::class, function ($mock) {
        $mock->shouldReceive('generateAssetMetadata')
            ->andThrow(new \RuntimeException('Gemini API timeout'));
        $mock->shouldReceive('generateAltText')
            ->andThrow(new \RuntimeException('Gemini API timeout'));
    });

    $job = new ProcessAssetAI($asset->id);
    $exception = null;

    try {
        $job->handle();
    } catch (\Throwable $e) {
        $exception = $e;
    }

    // Exception propagates to the queue worker (which will retry/call failed())
    expect($exception)->not->toBeNull()
        ->and($exception->getMessage())->toBe('Gemini API timeout');

    // Simulate queue worker calling failed() after retries exhausted
    $job->failed($exception);

    $asset->refresh();
    expect($asset->status)->toBe('error');
});
