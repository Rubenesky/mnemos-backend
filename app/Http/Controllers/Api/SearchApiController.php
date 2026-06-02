<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\NaturalLanguageSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API controller for natural-language asset search powered by the Gemini AI service.
 *
 * @package App\Http\Controllers\Api
 */
class SearchApiController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'max:500'],
        ]);

        $userQuery = $request->input('query');

        // Parsear la query en lenguaje natural
        $nlSearch = new NaturalLanguageSearchService();
        $filters  = $nlSearch->parseQuery($userQuery);

        // Si Gemini falló y no devolvió filtros, indicarlo
        if (empty($filters)) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo procesar la búsqueda. Inténtalo de nuevo en unos segundos.',
            ], 503);
        }

        // Construir la consulta con los filtros
        $query = Asset::with(['user', 'metadata', 'categories']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                  ->orWhereHas('metadata', function ($q) use ($search) {
                      $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('tags', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['type'])) {
            $query->where('mime_type', 'like', $filters['type'] . '%');
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $assets = $query->latest()->get();

        return response()->json([
            'success' => true,
            'query'   => $userQuery,
            'filters' => $filters,
            'total'   => $assets->count(),
            'data'    => $assets->map(function ($asset) {
                return [
                    'id'            => $asset->id,
                    'original_name' => $asset->original_name,
                    'mime_type'     => $asset->mime_type,
                    'size_kb'       => round($asset->size / 1024, 2),
                    'status'        => $asset->status,
                    'url' => $asset->path,
                    'uploaded_by'   => $asset->user->name,
                    'metadata'      => $asset->metadata ? [
                        'title'       => $asset->metadata->title,
                        'description' => $asset->metadata->description,
                        'tags'        => $asset->metadata->tags,
                    ] : null,
                    'created_at' => $asset->created_at->toISOString(),
                ];
            }),
        ]);
    }
}