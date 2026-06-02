<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Parses natural-language search queries into structured filter arrays using the Gemini API.
 *
 * @package App\Services
 */
class NaturalLanguageSearchService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    }

    public function parseQuery(string $userQuery): array
    {
        $prompt = "You are an expert assistant for digital asset search. Your task is to convert natural-language searches into structured filters.

    AVAILABLE FILTERS:
    - search: keyword to search for. IMPORTANT RULES:
    * Always use the ROOT of the word (no plural, no suffixes)
    * Examples: 'landscapes'→'landscape', 'mountains'→'mountain', 'logos'→'logo', 'photographs'→'photograph', 'buildings'→'building', 'people'→'person', 'animals'→'animal', 'cars'→'car', 'trees'→'tree'
    * If there are multiple keywords, choose the MOST SPECIFIC one
    * Ignore generic words like 'file', 'image', 'photo', 'document'
    - type: ONLY these exact values:
    * 'image' → when the user mentions: image, photo, photograph, illustration, screenshot, png, jpg, jpeg, gif, svg, avif, webp
    * 'application/pdf' → when they mention: pdf, pdf document, pdf file
    * 'video' → when they mention: video, mp4, mov, avi
    * Do not include if the type is not clear
    - status: ONLY 'processed' or 'pending'
    * 'processed' → processed, with metadata, analysed
    * 'pending' → pending, unprocessed, unanalysed
    * Do not include if not mentioned
    - date_from: start date in Y-m-d format
    * 'today' → " . now()->format('Y-m-d') . "
    * 'this week' → " . now()->startOfWeek()->format('Y-m-d') . "
    * 'this month' → " . now()->startOfMonth()->format('Y-m-d') . "
    * 'this year' → " . now()->startOfYear()->format('Y-m-d') . "
    * 'yesterday' → " . now()->subDay()->format('Y-m-d') . "
    * 'last week' → " . now()->subWeek()->format('Y-m-d') . "
    * 'last month' → " . now()->subMonth()->format('Y-m-d') . "
    - date_to: end date in Y-m-d format (only if a range is specified)

    EXAMPLE SEARCHES AND EXPECTED RESULTS:
    - 'mountain photos' → {\"type\": \"image\", \"search\": \"mountain\"}
    - 'landscape images uploaded this week' → {\"type\": \"image\", \"search\": \"landscape\", \"date_from\": \"" . now()->startOfWeek()->format('Y-m-d') . "\"}
    - 'pending pdf documents' → {\"type\": \"application/pdf\", \"status\": \"pending\"}
    - 'processed company logos' → {\"type\": \"image\", \"search\": \"logo\", \"status\": \"processed\"}
    - 'photos uploaded today' → {\"type\": \"image\", \"date_from\": \"" . now()->format('Y-m-d') . "\"}
    - 'files from this month' → {\"date_from\": \"" . now()->startOfMonth()->format('Y-m-d') . "\"}
    - 'images of smiling people' → {\"type\": \"image\", \"search\": \"person\"}
    - 'screenshots' → {\"type\": \"image\", \"search\": \"screenshot\"}
    - 'recent videos' → {\"type\": \"video\", \"date_from\": \"" . now()->subWeek()->format('Y-m-d') . "\"}

    GENERAL RULES:
    - Respond ONLY with valid JSON, no explanations or markdown
    - Only include filters that clearly apply
    - If the search is ambiguous, use only 'search' with the most relevant word
    - Never invent filters not in the list

    The user is searching for: \"{$userQuery}\"";

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
                Log::error('NL Search error', ['response' => $response->body()]);
                return [];
            }

            $text  = $response->json('candidates.0.content.parts.0.text');
            $clean = preg_replace('/```json|```/', '', $text);
            $data  = json_decode(trim($clean), true);

            Log::info('NL Search parsed', ['query' => $userQuery, 'filters' => $data]);

            return $data ?? [];

        } catch (\Exception $e) {
            Log::error('NL Search exception', ['error' => $e->getMessage()]);
            return [];
        }
    }
}