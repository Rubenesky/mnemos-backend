<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Detects semantically similar assets by comparing descriptions and tags via the Gemini API.
 *
 * @package App\Services
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
        if (empty($description) || $description === 'Sin descripción generada.') {
            return [];
        }

        // Obtenemos todos los assets procesados excepto el actual
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

        // Preparamos los datos para comparar
        $assetsData = $existingAssets->map(function ($asset) {
            return [
                'id'          => $asset->id,
                'title'       => $asset->metadata?->title ?? '',
                'description' => $asset->metadata?->description ?? '',
                'tags'        => $asset->metadata?->tags ?? [],
            ];
        })->toArray();

        $prompt = "Eres un sistema de detección de duplicados de activos digitales.

Nuevo asset subido:
- Descripción: \"{$description}\"
- Tags: " . implode(', ', $tags) . "

Assets existentes:
" . json_encode($assetsData, JSON_UNESCAPED_UNICODE) . "

Analiza si el nuevo asset es similar a alguno de los existentes basándote en la descripción y tags.
Considera similar si comparten el mismo tema, contenido visual o contexto (similaridad > 70%).

Responde SOLO con un JSON con este formato:
{\"similar\": [{\"id\": 1, \"similarity\": 85, \"reason\": \"Mismo tipo de paisaje montañoso\"}]}
Si no hay similares responde: {\"similar\": []}
Solo JSON, sin explicaciones ni markdown.";

        try {
            $response = Http::post("{$this->apiUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
                Log::error('Duplicate detection error', ['response' => $response->body()]);
                return [];
            }

            $text  = $response->json('candidates.0.content.parts.0.text');
            $clean = preg_replace('/```json|```/', '', $text);
            $data  = json_decode(trim($clean), true);

            Log::info('Duplicate detection result', ['data' => $data]);

            return $data['similar'] ?? [];

        } catch (\Exception $e) {
            Log::error('Duplicate detection exception', ['error' => $e->getMessage()]);
            return [];
        }
    }
}