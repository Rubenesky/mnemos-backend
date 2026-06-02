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
        $prompt = "Eres un asistente experto en búsqueda de activos digitales. Tu tarea es convertir búsquedas en lenguaje natural a filtros estructurados.

    FILTROS DISPONIBLES:
    - search: palabra clave para buscar. REGLAS IMPORTANTES:
    * Usa siempre la RAÍZ de la palabra (sin plural, sin sufijos)
    * Ejemplos: 'paisajes'→'paisaj', 'montañas'→'montaña', 'logos'→'logo', 'fotografías'→'fotograf', 'edificios'→'edifici', 'personas'→'person', 'animales'→'animal', 'coches'→'coche', 'árboles'→'árbol'
    * Si hay varias palabras clave elige la MÁS ESPECÍFICA
    * Ignora palabras genéricas como 'archivo', 'imagen', 'foto', 'documento', 'fichero'
    - type: SOLO estos valores exactos:
    * 'image' → cuando el usuario mencione: imagen, foto, fotografía, ilustración, captura, screenshot, png, jpg, jpeg, gif, svg, avif, webp
    * 'application/pdf' → cuando mencione: pdf, documento pdf, archivo pdf
    * 'video' → cuando mencione: vídeo, video, mp4, mov, avi
    * No incluir si no está claro el tipo
    - status: SOLO 'processed' o 'pending'
    * 'processed' → procesado, procesada, con metadatos, analizado
    * 'pending' → pendiente, sin procesar, sin analizar
    * No incluir si no se menciona
    - date_from: fecha de inicio en formato Y-m-d
    * 'hoy' → " . now()->format('Y-m-d') . "
    * 'esta semana' → " . now()->startOfWeek()->format('Y-m-d') . "
    * 'este mes' → " . now()->startOfMonth()->format('Y-m-d') . "
    * 'este año' → " . now()->startOfYear()->format('Y-m-d') . "
    * 'ayer' → " . now()->subDay()->format('Y-m-d') . "
    * 'última semana' → " . now()->subWeek()->format('Y-m-d') . "
    * 'último mes' → " . now()->subMonth()->format('Y-m-d') . "
    - date_to: fecha fin en formato Y-m-d (solo si se especifica un rango)

    EJEMPLOS DE BÚSQUEDAS Y RESULTADOS ESPERADOS:
    - 'fotos de montañas' → {\"type\": \"image\", \"search\": \"montaña\"}
    - 'imágenes de paisajes subidas esta semana' → {\"type\": \"image\", \"search\": \"paisaj\", \"date_from\": \"" . now()->startOfWeek()->format('Y-m-d') . "\"}
    - 'documentos pdf pendientes' → {\"type\": \"application/pdf\", \"status\": \"pending\"}
    - 'logos de empresas procesados' → {\"type\": \"image\", \"search\": \"logo\", \"status\": \"processed\"}
    - 'fotos subidas hoy' → {\"type\": \"image\", \"date_from\": \"" . now()->format('Y-m-d') . "\"}
    - 'archivos de este mes' → {\"date_from\": \"" . now()->startOfMonth()->format('Y-m-d') . "\"}
    - 'imágenes de personas sonriendo' → {\"type\": \"image\", \"search\": \"person\"}
    - 'capturas de pantalla' → {\"type\": \"image\", \"search\": \"captura\"}
    - 'vídeos recientes' → {\"type\": \"video\", \"date_from\": \"" . now()->subWeek()->format('Y-m-d') . "\"}

    REGLAS GENERALES:
    - Responde SOLO con JSON válido, sin explicaciones ni markdown
    - Solo incluye los filtros que apliquen claramente
    - Si la búsqueda es ambigua, usa solo 'search' con la palabra más relevante
    - Nunca inventes filtros que no estén en la lista

    El usuario busca: \"{$userQuery}\"";

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