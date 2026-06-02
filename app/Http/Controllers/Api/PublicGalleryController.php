<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves public-facing gallery endpoints — no authentication required.
 *
 * @package App\Http\Controllers\Api
 */
class PublicGalleryController extends Controller
{
    /**
     * List all public collections (categories with is_public = true).
     * Returns: id, name, slug, description, public asset count.
     */
    public function collections(): JsonResponse
    {
        $collections = Category::where('is_public', true)
            ->withCount(['assets' => fn($q) => $q->where('is_public', true)])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        return response()->json(['data' => $collections]);
    }

    /**
     * List public assets within a specific collection (by slug).
     * Returns paginated assets (20 per page) with metadata.
     */
    public function collection(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

        $assets = $category->assets()
            ->where('is_public', true)
            ->where('status', 'processed')
            ->with('metadata')
            ->paginate(20);

        return response()->json([
            'collection' => [
                'id'          => $category->id,
                'name'        => $category->name,
                'slug'        => $category->slug,
                'description' => $category->description,
            ],
            'assets' => $assets,
        ]);
    }

    /**
     * Show a single public asset by ID.
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
     * Format an asset for public API response.
     * Excludes internal fields (path, file_hash, user_id).
     */
    private function formatAsset(Asset $asset): array
    {
        return [
            'id'             => $asset->id,
            'original_name'  => $asset->original_name,
            'mime_type'      => $asset->mime_type,
            'cloudinary_url' => $asset->cloudinary_url,
            'title'          => $asset->metadata?->title,
            'description'    => $asset->metadata?->description,
            'tags'           => $asset->metadata?->tags ?? [],
            'categories'     => $asset->categories->map(fn($c) => [
                'id'   => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
            ]),
        ];
    }
}
