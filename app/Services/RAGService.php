<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Answers natural-language questions about the asset library using Retrieval-Augmented Generation via Gemini.
 *
 * @package App\Services
 */
class RAGService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    }

    public function query(string $userQuestion): string
    {
        // Paso 1: Recopilamos contexto real de la base de datos
        $context = Cache::remember('rag_context', 300, fn() => $this->gatherContext());

        // Paso 2: Mandamos el contexto + pregunta a Gemini
        $prompt = "Eres un asistente inteligente de Mnemos, un sistema de gestión de activos digitales.

Tienes acceso a los siguientes datos REALES y ACTUALES de la plataforma:

{$context}

El usuario te hace esta pregunta: \"{$userQuestion}\"

Responde de forma clara, concisa y en español basándote ÚNICAMENTE en los datos proporcionados arriba.
Si la pregunta no se puede responder con los datos disponibles, dilo claramente.
No inventes datos. No uses información que no esté en el contexto.
Responde en 1-3 frases máximo de forma conversacional.";

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
                Log::error('RAG error', ['response' => $response->body()]);
                return 'Lo siento, no puedo responder en este momento. Inténtalo de nuevo.';
            }

            return $response->json('candidates.0.content.parts.0.text') ?? 'No se pudo generar una respuesta.';

        } catch (\Exception $e) {
            Log::error('RAG exception', ['error' => $e->getMessage()]);
            return 'Error al procesar tu pregunta. Inténtalo de nuevo.';
        }
    }

    private function gatherContext(): string
    {
        // Assets
        $totalAssets     = Asset::count();
        $processedAssets = Asset::where('status', 'processed')->count();
        $pendingAssets   = Asset::where('status', 'pending')->count();

        // Assets por tipo
        $imageAssets = Asset::where('mime_type', 'like', 'image/%')->count();
        $pdfAssets   = Asset::where('mime_type', 'like', 'application/pdf%')->count();

        // Assets este mes
        $assetsThisMonth = Asset::whereMonth('created_at', now()->month)
                                ->whereYear('created_at', now()->year)
                                ->count();

        // Assets esta semana
        $assetsThisWeek = Asset::where('created_at', '>=', now()->startOfWeek())->count();

        // Assets hoy
        $assetsToday = Asset::whereDate('created_at', today())->count();

        // Usuario más activo
        $mostActiveUser      = Asset::selectRaw('user_id, count(*) as total')
                                    ->groupBy('user_id')
                                    ->orderByDesc('total')
                                    ->with('user')
                                    ->first();
        $mostActiveUserName  = $mostActiveUser && $mostActiveUser->user ? $mostActiveUser->user->name : 'Ninguno';
        $mostActiveUserTotal = $mostActiveUser ? $mostActiveUser->total : 0;

        // Últimos 5 assets subidos
        $recentAssets = Asset::with(['user', 'metadata'])
                             ->latest()
                             ->take(5)
                             ->get()
                             ->map(function($a) {
                                 $title    = $a->metadata && $a->metadata->title ? $a->metadata->title : $a->original_name;
                                 $name     = $a->user ? $a->user->name : 'Desconocido';
                                 $date     = $a->created_at->format('d/m/Y');
                                 return "'{$title}' subido por {$name} el {$date}";
                             })
                             ->join(', ');

        // Usuarios
        $totalUsers  = User::count();
        $adminUsers  = User::where('role', 'admin')->count();
        $editorUsers = User::where('role', 'editor')->count();
        $viewerUsers = User::where('role', 'viewer')->count();

        // Actividad reciente
        $recentActivity = ActivityLog::with('user')
                                     ->latest('created_at')
                                     ->take(5)
                                     ->get()
                                     ->map(function($log) {
                                         $name = $log->user ? $log->user->name : 'Desconocido';
                                         $date = $log->created_at->format('d/m/Y H:i');
                                         return "{$name} realizó '{$log->action}' el {$date}";
                                     })
                                     ->join(', ');

        // Tamaño total
        $totalSizeKB = Asset::sum('size') / 1024;
        $totalSizeMB = round($totalSizeKB / 1024, 2);

        return "
ESTADÍSTICAS GENERALES:
- Total de assets en la plataforma: {$totalAssets}
- Assets procesados por IA: {$processedAssets}
- Assets pendientes de procesar: {$pendingAssets}
- Assets de tipo imagen: {$imageAssets}
- Assets de tipo PDF: {$pdfAssets}
- Espacio total ocupado: {$totalSizeMB} MB

ACTIVIDAD TEMPORAL:
- Assets subidos hoy: {$assetsToday}
- Assets subidos esta semana: {$assetsThisWeek}
- Assets subidos este mes: {$assetsThisMonth}

USUARIOS:
- Total de usuarios: {$totalUsers}
- Administradores: {$adminUsers}
- Editores: {$editorUsers}
- Viewers: {$viewerUsers}
- Usuario más activo: {$mostActiveUserName} con {$mostActiveUserTotal} assets subidos

ÚLTIMOS ASSETS SUBIDOS:
{$recentAssets}

ACTIVIDAD RECIENTE:
{$recentActivity}

FECHA ACTUAL: " . now()->format('d/m/Y H:i') . "
";
    }
}