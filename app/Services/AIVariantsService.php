<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates alternative SEO-optimised titles, descriptions, and tags for an asset using the Gemini API.
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

        $prompt = "You are an expert in SEO and digital content marketing.

Given this digital asset with the following metadata:
- Current title: \"{$title}\"
- Current description: \"{$description}\"
- Current tags: {$tagsStr}

Generate improved variants in JSON format with exactly these keys:
- titles: array of 3 alternative titles that are more SEO-friendly and descriptive (maximum 60 characters each)
- descriptions: array of 2 improved descriptions that are more detailed and engaging (maximum 200 characters each)
- additional_tags: array of 5 additional relevant tags not already in the current tags

Respond ONLY with the JSON, no explanations or markdown.";

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
                Log::error('AI Variants error', ['response' => $response->body()]);

                return [];
            }

            $text = $response->json('candidates.0.content.parts.0.text');
            $clean = preg_replace('/```json|```/', '', $text);
            $data = json_decode(trim($clean), true);

            Log::info('AI Variants generated', ['data' => $data]);

            return $data ?? [];

        } catch (\Exception $e) {
            Log::error('AI Variants exception', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
