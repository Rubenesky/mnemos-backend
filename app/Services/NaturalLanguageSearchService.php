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

    private function sanitizeInput(string $input): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $input);
        return mb_substr(trim($clean), 0, 500);
    }

    public function parseQuery(string $userQuery): array
    {
        $userQuery = $this->sanitizeInput($userQuery);

        $today      = now()->format('Y-m-d');
        $weekStart  = now()->startOfWeek()->format('Y-m-d');
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $yearStart  = now()->startOfYear()->format('Y-m-d');
        $yesterday  = now()->subDay()->format('Y-m-d');
        $lastWeek   = now()->subWeek()->format('Y-m-d');
        $lastMonth  = now()->subMonth()->format('Y-m-d');

        $systemInstruction = "You are an expert assistant for digital asset search. Convert natural-language searches into structured JSON filters.

AVAILABLE FILTERS:
- search: keyword (ROOT form, most specific word; ignore generic words like 'file','image','photo','document')
- type: ONLY 'image', 'application/pdf', or 'video'
- status: ONLY 'processed' or 'pending'
- date_from / date_to: dates in Y-m-d format
  today={$today}, this_week={$weekStart}, this_month={$monthStart}, this_year={$yearStart}
  yesterday={$yesterday}, last_week={$lastWeek}, last_month={$lastMonth}

RULES: Respond ONLY with valid JSON. Only include filters that clearly apply. Never invent filters.";

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->post($this->apiUrl, [
                    'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $userQuery]]],
                    ],
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