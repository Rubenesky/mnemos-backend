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
    /** Gemini model identifier — referenced by AIProvenanceService for audit logging. */
    public const MODEL = 'gemini-2.5-flash';

    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . self::MODEL . ':generateContent';
    }

    public function generateAssetMetadata(string $filename, string $mimeType, ?string $storagePath = null, ?string $cloudinaryUrl = null, ?string $extractedText = null): array
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

        // For documents with extracted text, use content-aware analysis
        if (!empty($extractedText) && mb_strlen($extractedText) > 50) {
            return $this->analyzeDocumentContent($filename, $mimeType, $extractedText);
        }

        return $this->analyzeByFilename($filename, $mimeType);
    }

    /**
     * Generates AI metadata for a document by analysing its extracted text content.
     *
     * @param string $filename     Original filename (already sanitised)
     * @param string $mimeType     MIME type of the document
     * @param string $extractedText Plain-text content extracted from the document (up to 4000 chars)
     * @return array{title: string, description: string, tags: list<string>}
     */
    private function analyzeDocumentContent(string $filename, string $mimeType, string $extractedText): array
    {
        $filename = $this->sanitizeFilename($filename);
        $snippet  = mb_substr($extractedText, 0, 3000);

        $prompt = "Analiza el siguiente documento con nombre '{$filename}' (tipo: {$mimeType}).
Contenido del documento:
---
{$snippet}
---
Genera metadatos en formato JSON con exactamente estas claves:
- title: título descriptivo del documento en español (máximo 60 caracteres)
- description: resumen del contenido en español (máximo 200 caracteres)
- tags: array de 5 etiquetas relevantes en español basadas en el contenido
Responde ÚNICAMENTE con el JSON, sin explicaciones ni formato markdown.";

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])->post($this->apiUrl, [
                'contents' => [['parts' => [['text' => $prompt]]]]
            ]);

            if ($response->failed()) {
                Log::error('Gemini document analysis error', ['status' => $response->status()]);
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
            Log::error('Gemini document analysis exception', ['error' => $e->getMessage()]);
            return $this->analyzeByFilename($filename, $mimeType);
        }
    }

    private function analyzeImageFromUrl(string $filename, string $mimeType, string $imageUrl): array
    {
        try {
            $imageData = Http::get($imageUrl)->body();
            $base64    = base64_encode($imageData);

            $prompt = "Analiza esta imagen y genera metadatos en formato JSON con exactamente estas claves:
        - title: título descriptivo corto en español (máximo 60 caracteres)
        - description: descripción detallada en español de lo que ves en la imagen (máximo 200 caracteres)
        - tags: array de 5 etiquetas relevantes en español basadas en el contenido visual
        Responde ÚNICAMENTE con el JSON, sin explicaciones ni formato markdown.";

            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])->post($this->apiUrl, [
                'contents' => [[
                    'parts' => [
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64]],
                        ['text' => $prompt]
                    ]
                ]]
            ]);

            if ($response->failed()) {
                Log::error('Gemini Vision URL error', ['status' => $response->status()]);
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

            $prompt = "Analiza esta imagen y genera metadatos en formato JSON con exactamente estas claves:
            - title: título descriptivo corto en español (máximo 60 caracteres)
            - description: descripción detallada en español de lo que ves en la imagen (máximo 200 caracteres)
            - tags: array de 5 etiquetas relevantes en español basadas en el contenido visual
            Responde ÚNICAMENTE con el JSON, sin explicaciones ni formato markdown.";

            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])->post($this->apiUrl, [
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
                Log::error('Gemini Vision error', ['status' => $response->status()]);
                return $this->analyzeByFilename($filename, $mimeType);
            }

            $text = $response->json('candidates.0.content.parts.0.text');

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

    private function sanitizeFilename(string $filename): string
    {
        // Strip everything except safe printable characters before injecting into prompts
        $safe = preg_replace('/[^\w\s.\-]/u', '', $filename);
        return mb_substr(trim($safe), 0, 120);
    }

    private function analyzeByFilename(string $filename, string $mimeType): array
    {
        $filename = $this->sanitizeFilename($filename);
        $prompt = "Analiza este archivo con nombre '{$filename}' y tipo '{$mimeType}'.
        Genera metadatos en formato JSON con exactamente estas claves:
        - title: título descriptivo corto en español (máximo 60 caracteres)
        - description: descripción útil en español (máximo 200 caracteres)
        - tags: array de 3 a 5 etiquetas relevantes en español
        Responde ÚNICAMENTE con el JSON, sin explicaciones ni formato markdown.";

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])->post($this->apiUrl, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
                Log::error('Gemini API error', ['status' => $response->status()]);
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

            $prompt = 'Escribe una descripción de texto alternativo concisa para esta imagen en español. '
                    . 'Céntrate en lo que se muestra visualmente. Máximo 125 caracteres. '
                    . 'Devuelve solo la descripción, sin comillas, sin puntuación al final.';

            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])->post($this->apiUrl, [
                'contents' => [[
                    'parts' => [
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64]],
                        ['text' => $prompt],
                    ]
                ]]
            ]);

            if ($response->failed()) {
                Log::error('Gemini generateAltText error', ['status' => $response->status()]);
                return '';
            }

            $text = $response->json('candidates.0.content.parts.0.text');

            return mb_substr(trim((string) $text), 0, 125);

        } catch (\Exception $e) {
            Log::error('Gemini generateAltText exception', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Performs a lightweight models-list call to verify Gemini API connectivity.
     *
     * @return bool True if the API responds with HTTP 2xx.
     */
    public function ping(): bool
    {
        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->timeout(5)
                ->get('https://generativelanguage.googleapis.com/v1beta/models');

            return $response->successful();
        } catch (\Exception) {
            return false;
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