<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\NaturalLanguageSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * REST API controller for natural-language asset search powered by the Gemini AI service.
 */
class SearchApiController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'max:500'],
        ]);

        $userQuery = $request->input('query');

        // Parse the natural-language query into structured filters
        $nlSearch = app(NaturalLanguageSearchService::class);
        $filters = $nlSearch->parseQuery($userQuery);

        // If Gemini failed and returned no filters, report it
        if (empty($filters)) {
            return response()->json([
                'success' => false,
                'message' => 'Could not process the search. Please try again in a few seconds.',
            ], 503);
        }

        // Build the query using the parsed filters
        $query = Asset::with(['user', 'metadata', 'categories']);

        // PostgreSQL LIKE is case-sensitive; ILIKE is the case-insensitive equivalent.
        $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        // IDOR protection: non-admin users can only see their own assets
        /** @var \App\Models\User $user */
        $user = auth()->user();
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('original_name', $likeOp, "%{$search}%")
                    ->orWhereHas('metadata', function ($q) use ($search, $likeOp) {
                        $q->where('title', $likeOp, "%{$search}%")
                            ->orWhere('description', $likeOp, "%{$search}%")
                            ->orWhere('tags', $likeOp, "%{$search}%");
                    });
            });
        }

        if (! empty($filters['type'])) {
            $query->where('mime_type', $likeOp, $filters['type'].'%');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $assets = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'query' => $userQuery,
            'filters' => $filters,
            'data' => $assets->getCollection()->map(function ($asset) {
                return [
                    'id' => $asset->id,
                    'original_name' => $asset->original_name,
                    'mime_type' => $asset->mime_type,
                    'size_kb' => round($asset->size / 1024, 2),
                    'status' => $asset->status,
                    'url' => $asset->cloudinary_url
                                        ?: (str_starts_with($asset->path, 'http')
                                            ? $asset->path
                                            : asset('storage/'.$asset->path)),
                    'uploaded_by' => $asset->user->name,
                    'metadata' => $asset->metadata ? [
                        'title' => $asset->metadata->title,
                        'description' => $asset->metadata->description,
                        'tags' => $asset->metadata->tags,
                    ] : null,
                    'created_at' => $asset->created_at->toISOString(),
                ];
            }),
            'meta' => [
                'total' => $assets->total(),
                'per_page' => $assets->perPage(),
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
            ],
        ]);
    }
}
