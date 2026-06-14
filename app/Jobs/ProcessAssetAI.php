<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\AssetMetadata;
use App\Services\AIProvenanceService;
use App\Services\DuplicateDetectionService;
use App\Services\GeminiService;
use App\Services\TextExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAssetAI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 120;

    public function __construct(public readonly int $assetId) {}

    public function handle(): void
    {
        $asset = Asset::find($this->assetId);

        if (! $asset) {
            Log::warning('ProcessAssetAI: asset not found', ['asset_id' => $this->assetId]);

            return;
        }

        if ($asset->status === 'processed') {
            return;
        }

        $gemini = app(GeminiService::class);
        $extractor = app(TextExtractionService::class);
        $provenance = app(AIProvenanceService::class);

        $extractedText = null;

        if ($extractor->isSupported($asset->mime_type) && $asset->cloudinary_url) {
            $extractedText = $extractor->extract($asset->cloudinary_url, $asset->mime_type);
            if (! empty($extractedText)) {
                $asset->update(['extracted_text' => $extractedText]);
            }
        }

        $metadata = $gemini->generateAssetMetadata(
            $asset->original_name,
            $asset->mime_type,
            $asset->path,
            $asset->cloudinary_url,
            $extractedText
        );

        AssetMetadata::updateOrCreate(
            ['asset_id' => $asset->id],
            [
                'title' => $metadata['title'],
                'description' => $metadata['description'],
                'tags' => $metadata['tags'],
                'ai_generated' => true,
            ]
        );

        // Record provenance for the metadata generation
        try {
            $provenance->recordGeneration(
                $asset,
                'description',
                GeminiService::MODEL,
                "Generate metadata (title, description, tags) for {$asset->mime_type}: {$asset->original_name}",
                mb_substr($metadata['description'] ?? '', 0, 200)
            );
        } catch (\Throwable $e) {
            Log::warning('AIProvenance: failed to record metadata generation', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);
        }

        $duplicateDetector = app(DuplicateDetectionService::class);
        $duplicateDetector->findSimilar(
            $asset->id,
            $metadata['description'] ?? '',
            $metadata['tags'] ?? []
        );

        // Generate alt-text for image assets
        if (str_starts_with($asset->mime_type, 'image/') && $asset->cloudinary_url) {
            $altText = $gemini->generateAltText($asset->cloudinary_url);
            if (! empty($altText)) {
                $asset->update(['alt_text' => $altText]);

                // Record provenance for the alt-text generation
                try {
                    $provenance->recordGeneration(
                        $asset->fresh(),
                        'alt_text',
                        GeminiService::MODEL,
                        "Generate alt-text for image: {$asset->original_name}",
                        mb_substr($altText, 0, 200)
                    );
                } catch (\Throwable $e) {
                    Log::warning('AIProvenance: failed to record alt-text generation', [
                        'asset_id' => $asset->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $asset->update(['status' => 'processed']);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessAssetAI permanently failed', [
            'asset_id' => $this->assetId,
            'error' => $exception->getMessage(),
        ]);

        Asset::where('id', $this->assetId)->update(['status' => 'error']);
    }
}
