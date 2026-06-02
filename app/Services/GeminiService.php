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
        // Si es imagen, usamos Vision con la URL de Cloudinary o storage local
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

            $prompt = "Analiza esta imagen y genera metadatos en formato JSON con exactamente estas claves:
        - title: título descriptivo corto en español (máximo 60 caracteres)
        - description: descripción detallada de lo que ves en la imagen en español (máximo 200 caracteres)
        - tags: array de 5 etiquetas relevantes en español basadas en el contenido visual
        Responde SOLO con el JSON, sin explicaciones ni formato markdown.";

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
            // Leemos la imagen y la convertimos a base64
            $imageData = Storage::disk('public')->get($storagePath);
            $base64    = base64_encode($imageData);

            $prompt = "Analiza esta imagen y genera metadatos en formato JSON con exactamente estas claves:
            - title: título descriptivo corto en español (máximo 60 caracteres)
            - description: descripción detallada de lo que ves en la imagen en español (máximo 200 caracteres)
            - tags: array de 5 etiquetas relevantes en español basadas en el contenido visual
            Responde SOLO con el JSON, sin explicaciones ni formato markdown.";

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
        $prompt = "Analiza este archivo con nombre '{$filename}' y tipo '{$mimeType}'.
        Genera metadatos en formato JSON con exactamente estas claves:
        - title: título descriptivo corto (máximo 60 caracteres)
        - description: descripción útil (máximo 200 caracteres)
        - tags: array de 3 a 5 etiquetas relevantes en español
        Responde SOLO con el JSON, sin explicaciones ni formato markdown.";

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

    private function defaultMetadata(string $filename): array
    {
        return [
            'title'       => pathinfo($filename, PATHINFO_FILENAME),
            'description' => 'Sin descripción generada.',
            'tags'        => [],
        ];
    }
}