<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\AssetMetadata;
use App\Services\DuplicateDetectionService;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAssetAI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;
    public int $timeout = 60;

    public function __construct(public readonly int $assetId)
    {
    }

    public function handle(): void
    {
        $asset = Asset::find($this->assetId);

        if (!$asset) {
            Log::warning('ProcessAssetAI: asset not found', ['asset_id' => $this->assetId]);
            return;
        }

        $gemini   = app(GeminiService::class);
        $metadata = $gemini->generateAssetMetadata(
            $asset->original_name,
            $asset->mime_type,
            $asset->path,
            $asset->cloudinary_url
        );

        AssetMetadata::create([
            'asset_id'     => $asset->id,
            'title'        => $metadata['title'],
            'description'  => $metadata['description'],
            'tags'         => $metadata['tags'],
            'ai_generated' => true,
        ]);

        $duplicateDetector = app(DuplicateDetectionService::class);
        $duplicateDetector->findSimilar(
            $asset->id,
            $metadata['description'] ?? '',
            $metadata['tags'] ?? []
        );

        $asset->update(['status' => 'processed']);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessAssetAI permanently failed', [
            'asset_id' => $this->assetId,
            'error'    => $exception->getMessage(),
        ]);

        Asset::where('id', $this->assetId)->update(['status' => 'failed']);
    }
}
