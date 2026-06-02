<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Generates AI metadata (title, description, tags) for assets using the Google Gemini Vision API.
 *
 * @package App\Services
 */
class GeminiService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    }

    public function generateAssetMetadata(string $filename, string $mimeType, ?string $storagePath = null, ?string $cloudinaryUrl = null): array
    {
        // For images, use Vision with the Cloudinary URL or local storage
        if (str_starts_with($mimeType, 'image/')) {
            if ($cloudinaryUrl) {
                return $this->analyzeImageFromUrl($filename, $mimeType, $cloudinaryUrl);
            }
            if ($storagePath) {
                return $this->analyzeImageWithVision($filename, $mimeType, $storagePath);
            }
        }
        return $this->analyzeByFilename($filename, $mimeType);
    }

    private function analyzeImageFromUrl(string $filename, string $mimeType, string $imageUrl): array
    {
        try {
            $imageData = Http::get($imageUrl)->body();
            $base64    = base64_encode($imageData);

            $prompt = "Analyse this image and generate metadata in JSON format with exactly these keys:
        - title: short descriptive title (maximum 60 characters)
        - description: detailed description of what you see in the image (maximum 200 characters)
        - tags: array of 5 relevant tags based on the visual content
        Respond ONLY with the JSON, no explanations or markdown formatting.";

            $response = Http::post("{$this->apiUrl}?key={$this->apiKey}", [
                'contents' => [[
                    'parts' => [
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64]],
                        ['text' => $prompt]
                    ]
                ]]
            ]);

            if ($response->failed()) {
                Log::error('Gemini Vision URL error', ['response' => $response->body()]);
                return $this->analyzeByFilename($filename, $mimeType);
            }

            $text  = $response->json('candidates.0.content.parts.0.text');
            $clean = preg_replace('/```json|```/', '', $text);
            $data  = json_decode(trim($clean), true);

            if (!$data || !isset($data['title'])) {
                return $this->analyzeByFilename($filename, $mimeType);
            }

            return $data;

        } catch (\Exception $e) {
            Log::error('Gemini Vision URL exception', ['error' => $e->getMessage()]);
            return $this->analyzeByFilename($filename, $mimeType);
        }
    }

    private function analyzeImageWithVision(string $filename, string $mimeType, string $storagePath): array
    {
        try {
            // Read the image and convert it to base64
            $imageData = Storage::disk('public')->get($storagePath);
            $base64    = base64_encode($imageData);

            $prompt = "Analyse this image and generate metadata in JSON format with exactly these keys:
            - title: short descriptive title (maximum 60 characters)
            - description: detailed description of what you see in the image (maximum 200 characters)
            - tags: array of 5 relevant tags based on the visual content
            Respond ONLY with the JSON, no explanations or markdown formatting.";

            $response = Http::post("{$this->apiUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data'      => $base64,
                                ]
                            ],
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
                Log::error('Gemini Vision error', ['response' => $response->body()]);
                return $this->analyzeByFilename($filename, $mimeType);
            }

            $text = $response->json('candidates.0.content.parts.0.text');
            Log::info('Gemini Vision response', ['text' => $text]);

            $clean = preg_replace('/```json|```/', '', $text);
            $data  = json_decode(trim($clean), true);

            if (!$data || !isset($data['title'])) {
                return $this->analyzeByFilename($filename, $mimeType);
            }

            return $data;

        } catch (\Exception $e) {
            Log::error('Gemini Vision exception', ['error' => $e->getMessage()]);
            return $this->analyzeByFilename($filename, $mimeType);
        }
    }

    private function analyzeByFilename(string $filename, string $mimeType): array
    {
        $prompt = "Analyse this file with name '{$filename}' and type '{$mimeType}'.
        Generate metadata in JSON format with exactly these keys:
        - title: short descriptive title (maximum 60 characters)
        - description: useful description (maximum 200 characters)
        - tags: array of 3 to 5 relevant tags
        Respond ONLY with the JSON, no explanations or markdown formatting.";

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
                Log::error('Gemini API error', ['response' => $response->body()]);
                return $this->defaultMetadata($filename);
            }

            $text  = $response->json('candidates.0.content.parts.0.text');
            $clean = preg_replace('/```json|```/', '', $text);
            $data  = json_decode(trim($clean), true);

            if (!$data || !isset($data['title'])) {
                return $this->defaultMetadata($filename);
            }

            return $data;

        } catch (\Exception $e) {
            Log::error('Gemini Service exception', ['error' => $e->getMessage()]);
            return $this->defaultMetadata($filename);
        }
    }

    /**
     * Generates an accessibility alt-text description for an image using Gemini Vision.
     *
     * @param string $imageUrl URL of the image to describe
     * @return string A concise accessibility description (max 125 characters)
     */
    public function generateAltText(string $imageUrl): string
    {
        try {
            $imageData = Http::get($imageUrl)->body();
            $base64    = base64_encode($imageData);

            // Detect mime type from the URL extension; default to jpeg
            $ext      = strtolower(pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
            $mimeType = match ($ext) {
                'png'  => 'image/png',
                'gif'  => 'image/gif',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            };

            $prompt = 'Write a concise alt-text description for this image. '
                    . 'Focus on what is visually depicted. Maximum 125 characters. '
                    . 'Return only the description, no quotes, no punctuation at the end.';

            $response = Http::post("{$this->apiUrl}?key={$this->apiKey}", [
                'contents' => [[
                    'parts' => [
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64]],
                        ['text' => $prompt],
                    ]
                ]]
            ]);

            if ($response->failed()) {
                Log::error('Gemini generateAltText error', ['response' => $response->body()]);
                return '';
            }

            $text = $response->json('candidates.0.content.parts.0.text');

            return mb_substr(trim((string) $text), 0, 125);

        } catch (\Exception $e) {
            Log::error('Gemini generateAltText exception', ['error' => $e->getMessage()]);
            return '';
        }
    }

    private function defaultMetadata(string $filename): array
    {
        return [
            'title'       => pathinfo($filename, PATHINFO_FILENAME),
            'description' => 'No description generated.',
            'tags'        => [],
        ];
    }
}