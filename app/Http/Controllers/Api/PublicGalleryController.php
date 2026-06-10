<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use App\Services\OrganizationSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves public-facing gallery endpoints — no authentication required.
 *
 * @package App\Http\Controllers\Api
 * @author  RJC
 */
class PublicGalleryController extends Controller
{
    /**
     * @param OrganizationSettingsService $settings  Used to expose org name in all public responses
     */
    public function __construct(
        private readonly OrganizationSettingsService $settings,
    ) {}

    /**
     * GET /api/public/assets
     *
     * Returns all public processed assets, paginated. Does not require a collection.
     *
     * Accepts two optional filter parameters — only one is applied at a time,
     * with collection_id taking precedence over collection:
     *
     *   ?collection_id={int}  Filter by category ID. Returns 404 if the category
     *                         does not exist, 403 if it exists but is not public.
     *   ?collection={slug}    Filter by category slug. Silently ignored if the
     *                         slug belongs to a private category (backwards-compat).
     *
     * @param  Request  $request
     * @return JsonResponse  200 { org_name, data, current_page, last_page, total }
     *                     | 403 if collection_id points to a private category
     *                     | 404 if collection_id does not exist
     */
    public function assets(Request $request): JsonResponse
    {
        $query = Asset::where('is_public', true)
            ->where('status', 'processed')
            ->with('metadata', 'categories')
            ->latest();

        // collection_id takes precedence — strict: 404 if missing, 403 if private
        if ($request->filled('collection_id')) {
            $category = Category::find($request->integer('collection_id'));

            if (! $category) {
                return response()->json(['message' => 'Collection not found.'], 404);
            }

            if (! $category->is_public) {
                return response()->json(['message' => 'This collection is not public.'], 403);
            }

            $query->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id));

        } elseif ($request->filled('collection')) {
            // Legacy slug-based filter — silently ignores private/missing collections
            $category = Category::where('slug', $request->input('collection'))
                ->where('is_public', true)
                ->first();

            if ($category) {
                $query->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id));
            }
        }

        $paginated = $query->paginate(12);

        return response()->json([
            'org_name'     => $this->settings->get('org_name', 'Mnemos'),
            'data'         => $paginated->map(fn ($a) => $this->formatAsset($a))->values(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
        ]);
    }

    /**
     * GET /api/public/collections
     *
     * Lists all public categories with their public asset counts.
     * Used as optional filter tabs in the gallery.
     *
     * @return JsonResponse  200 { org_name: string, data: Category[] }
     */
    public function collections(): JsonResponse
    {
        $collections = Category::where('is_public', true)
            ->withCount(['assets' => fn ($q) => $q->where('is_public', true)->where('status', 'processed')])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        return response()->json([
            'org_name' => $this->settings->get('org_name', 'Mnemos'),
            'data'     => $collections,
        ]);
    }

    /**
     * GET /api/public/collections/{slug}
     *
     * Lists public assets within a specific collection (by slug).
     * Kept for backwards-compatible shareable URLs.
     *
     * @param  string  $slug
     * @return JsonResponse  200 { collection: {...}, assets: { data, current_page, last_page, total } }
     */
    public function collection(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

        $assets = $category->assets()
            ->where('is_public', true)
            ->where('status', 'processed')
            ->with('metadata', 'categories')
            ->paginate(20);

        return response()->json([
            'collection' => [
                'id'          => $category->id,
                'name'        => $category->name,
                'slug'        => $category->slug,
                'description' => $category->description,
            ],
            'assets' => [
                'data'         => $assets->map(fn ($a) => $this->formatAsset($a))->values(),
                'current_page' => $assets->currentPage(),
                'last_page'    => $assets->lastPage(),
                'total'        => $assets->total(),
            ],
        ]);
    }

    /**
     * GET /api/public/assets/{id}
     *
     * Returns a single public processed asset by ID.
     *
     * @param  int  $id
     * @return JsonResponse  200 { data: Asset } | 404
     */
    public function asset(int $id): JsonResponse
    {
        $asset = Asset::where('id', $id)
            ->where('is_public', true)
            ->where('status', 'processed')
            ->with('metadata', 'categories')
            ->firstOrFail();

        return response()->json(['data' => $this->formatAsset($asset)]);
    }

    /**
     * Format an asset for all public API responses.
     * Excludes internal fields (path, file_hash, user_id).
     *
     * @param  Asset  $asset
     * @return array{id: int, original_name: string, mime_type: string, cloudinary_url: string|null, created_at: string|null, metadata: array|null, categories: array}
     */
    private function formatAsset(Asset $asset): array
    {
        return [
            'id'             => $asset->id,
            'original_name'  => $asset->original_name,
            'mime_type'      => $asset->mime_type,
            'cloudinary_url' => $asset->cloudinary_url,
            'created_at'     => $asset->created_at?->toISOString(),
            'metadata'       => $asset->metadata ? [
                'title'        => $asset->metadata->title,
                'description'  => $asset->metadata->description,
                'tags'         => $asset->metadata->tags ?? [],
                'ai_generated' => $asset->metadata->ai_generated,
            ] : null,
            'categories'     => $asset->categories->map(fn ($c) => [
                'id'   => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
            ]),
        ];
    }
}
