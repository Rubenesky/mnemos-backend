<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates alternative SEO-optimised titles, descriptions, and tags for an asset using the Gemini API.
 *
 * @package App\Services
 */
class AIVariantsService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    }

    public function generateVariants(string $title, string $description, array $tags): array
    {
        $tagsStr = implode(', ', $tags);

        $prompt = "Eres un experto en SEO y marketing de contenidos digitales.

Dado este asset digital con los siguientes metadatos:
- Título actual: \"{$title}\"
- Descripción actual: \"{$description}\"
- Tags actuales: {$tagsStr}

Genera variantes mejoradas en formato JSON con exactamente estas claves:
- titles: array de 3 títulos alternativos más SEO-friendly y descriptivos (máximo 60 caracteres cada uno)
- descriptions: array de 2 descripciones mejoradas más detalladas y atractivas (máximo 200 caracteres cada una)
- additional_tags: array de 5 tags adicionales relevantes que no están en los actuales

Responde SOLO con el JSON, sin explicaciones ni markdown.";

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
                Log::error('AI Variants error', ['response' => $response->body()]);
                return [];
            }

            $text  = $response->json('candidates.0.content.parts.0.text');
            $clean = preg_replace('/```json|```/', '', $text);
            $data  = json_decode(trim($clean), true);

            Log::info('AI Variants generated', ['data' => $data]);

            return $data ?? [];

        } catch (\Exception $e) {
            Log::error('AI Variants exception', ['error' => $e->getMessage()]);
            return [];
        }
    }
}