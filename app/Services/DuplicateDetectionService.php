<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Detects semantically similar assets by comparing descriptions and tags via the Gemini API.
 */
class DuplicateDetectionService
{
    private string $apiKey;

    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    }

    public function findSimilar(int $assetId, string $description, array $tags): array
    {
        if (empty($description) || $description === 'No description generated.') {
            return [];
        }

        // Get all processed assets except the current one
        $existingAssets = Asset::with('metadata')
            ->where('id', '!=', $assetId)
            ->where('status', 'processed')
            ->whereHas('metadata', function ($q) {
                $q->whereNotNull('description');
            })
            ->latest()
            ->limit(200)
            ->get();

        if ($existingAssets->isEmpty()) {
            return [];
        }

        // Prepare the data for comparison
        $assetsData = $existingAssets->map(function ($asset) {
            return [
                'id' => $asset->id,
                'title' => $asset->metadata?->title ?? '',
                'description' => $asset->metadata?->description ?? '',
                'tags' => $asset->metadata?->tags ?? [],
            ];
        })->toArray();

        $prompt = "You are a duplicate detection system for digital assets.

Newly uploaded asset:
- Description: \"{$description}\"
- Tags: ".implode(', ', $tags).'

Existing assets:
'.json_encode($assetsData, JSON_UNESCAPED_UNICODE).'

Analyse whether the new asset is similar to any of the existing ones based on description and tags.
Consider them similar if they share the same subject, visual content, or context (similarity > 70%).

Respond ONLY with a JSON in this format:
{"similar": [{"id": 1, "similarity": 85, "reason": "Same type of mountain landscape"}]}
If there are no similar assets respond: {"similar": []}
JSON only, no explanations or markdown.';

        try {
            $response = Http::post("{$this->apiUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
            ]);

            if ($response->failed()) {
                Log::error('Duplicate detection error', ['response' => $response->body()]);

                return [];
            }

            $text = $response->json('candidates.0.content.parts.0.text');
            $clean = preg_replace('/```json|```/', '', $text);
            $data = json_decode(trim($clean), true);

            Log::info('Duplicate detection result', ['data' => $data]);

            return $data['similar'] ?? [];

        } catch (\Exception $e) {
            Log::error('Duplicate detection exception', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
