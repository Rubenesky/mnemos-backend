<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * REST API controller for managing collections (categories).
 *
 * Collections group assets for display in the public gallery and for
 * internal organisation. Admin and editor roles only.
 *
 * Endpoints:
 *   GET    /api/collections
 *   POST   /api/collections
 *   GET    /api/collections/{id}
 *   PATCH  /api/collections/{id}
 *   DELETE /api/collections/{id}
 *   PATCH  /api/collections/{id}/visibility
 *   POST   /api/collections/{id}/assets
 *   DELETE /api/collections/{id}/assets/{assetId}
 *
 * @package App\Http\Controllers\Api
 * @author  RJC
 */
class CollectionController extends Controller
{
    /**
     * Restrict all collection operations to admin and editor roles.
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $role = auth()->user()?->role;
            if (! in_array($role, ['admin', 'editor'])) {
                return response()->json(
                    ['message' => 'Unauthorized. Admin or editor role required.'],
                    403
                );
            }

            return $next($request);
        });
    }

    /**
     * GET /api/collections
     *
     * Return all collections ordered by name, each with a total asset count.
     *
     * @return JsonResponse  200 { success: true, data: Collection[] }
     */
    public function index(): JsonResponse
    {
        $collections = Category::withCount('assets')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $collections->map(fn ($c) => $this->formatCollection($c)),
        ]);
    }

    /**
     * POST /api/collections
     *
     * Create a new collection. Slug is auto-generated from the name and
     * guaranteed unique by appending an incrementing suffix when necessary.
     *
     * @param  Request $request  { name: string, description?: string, is_public?: bool }
     * @return JsonResponse      201 { success: true, data: Collection }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'is_public'   => 'boolean',
        ]);

        $validated['slug']      = $this->uniqueSlug($validated['name']);
        $validated['is_public'] = $validated['is_public'] ?? false;
        $validated['parent_id'] = null;

        $collection = Category::create($validated);
        $collection->loadCount('assets');

        return response()->json([
            'success' => true,
            'data'    => $this->formatCollection($collection),
        ], 201);
    }

    /**
     * GET /api/collections/{id}
     *
     * Return a single collection together with a list of its assets.
     *
     * @param  int $id
     * @return JsonResponse  200 { success: true, data: Collection }  |  404
     */
    public function show(int $id): JsonResponse
    {
        $collection = Category::with([
            'assets' => fn ($q) => $q->with('metadata')->latest(),
        ])
            ->withCount('assets')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatCollection($collection, withAssets: true),
        ]);
    }

    /**
     * PATCH /api/collections/{id}
     *
     * Update a collection's name, description, or is_public flag.
     * The slug is regenerated automatically when the name changes.
     *
     * @param  Request $request  { name?: string, description?: string, is_public?: bool }
     * @param  int     $id
     * @return JsonResponse  200 { success: true, data: Collection }  |  404
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $collection = Category::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:120',
            'description' => 'nullable|string|max:500',
            'is_public'   => 'boolean',
        ]);

        if (isset($validated['name']) && $validated['name'] !== $collection->name) {
            $validated['slug'] = $this->uniqueSlug($validated['name'], $collection->id);
        }

        $collection->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $this->formatCollection($collection->fresh()->loadCount('assets')),
        ]);
    }

    /**
     * DELETE /api/collections/{id}
     *
     * Delete a collection. Assets are never deleted — the cascade on the
     * asset_category FK handles pivot cleanup automatically.
     *
     * @param  int $id
     * @return JsonResponse  200 { success: true }  |  404
     */
    public function destroy(int $id): JsonResponse
    {
        $collection = Category::findOrFail($id);
        $collection->delete();

        return response()->json(['success' => true, 'message' => 'Collection deleted.']);
    }

    /**
     * PATCH /api/collections/{id}/visibility
     *
     * Toggle the is_public flag on a collection.
     *
     * @param  int $id
     * @return JsonResponse  200 { success: true, is_public: bool }  |  404
     */
    public function toggleVisibility(int $id): JsonResponse
    {
        $collection = Category::findOrFail($id);
        $collection->update(['is_public' => ! $collection->is_public]);

        return response()->json([
            'success'   => true,
            'is_public' => (bool) $collection->is_public,
        ]);
    }

    /**
     * POST /api/collections/{id}/assets
     *
     * Add an asset to a collection. Idempotent — attaching an already-attached
     * asset does not create a duplicate pivot row.
     *
     * @param  Request $request  { asset_id: int }
     * @param  int     $id
     * @return JsonResponse  200 { success: true, assets_count: int }  |  404 | 422
     */
    public function addAsset(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'asset_id' => 'required|integer|exists:assets,id',
        ]);

        $collection = Category::findOrFail($id);
        $collection->assets()->syncWithoutDetaching([$request->integer('asset_id')]);

        return response()->json([
            'success'      => true,
            'assets_count' => $collection->assets()->count(),
        ]);
    }

    /**
     * DELETE /api/collections/{id}/assets/{assetId}
     *
     * Remove an asset from a collection. The asset itself is not deleted.
     *
     * @param  int $id
     * @param  int $assetId
     * @return JsonResponse  200 { success: true, assets_count: int }  |  404
     */
    public function removeAsset(int $id, int $assetId): JsonResponse
    {
        $collection = Category::findOrFail($id);
        $collection->assets()->detach($assetId);

        return response()->json([
            'success'      => true,
            'assets_count' => $collection->assets()->count(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate a URL-safe slug from a name that is unique in the categories table.
     *
     * If "my-collection" is taken, tries "my-collection-2", "my-collection-3", …
     *
     * @param  string   $name
     * @param  int|null $excludeId  Skip this ID when checking uniqueness (used on updates).
     * @return string
     */
    private function uniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i    = 2;

        while (
            Category::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * Serialize a Category model for API responses.
     *
     * @param  Category $collection
     * @param  bool     $withAssets  When true, includes the full asset list.
     * @return array{id: int, name: string, slug: string, description: string|null, is_public: bool, assets_count: int, created_at: string|null, updated_at: string|null, assets?: array}
     */
    private function formatCollection(Category $collection, bool $withAssets = false): array
    {
        $data = [
            'id'           => $collection->id,
            'name'         => $collection->name,
            'slug'         => $collection->slug,
            'description'  => $collection->description,
            'is_public'    => (bool) $collection->is_public,
            'assets_count' => (int) ($collection->assets_count ?? 0),
            'created_at'   => $collection->created_at?->toISOString(),
            'updated_at'   => $collection->updated_at?->toISOString(),
        ];

        if ($withAssets) {
            $data['assets'] = $collection->assets->map(fn ($a) => [
                'id'             => $a->id,
                'original_name'  => $a->original_name,
                'mime_type'      => $a->mime_type,
                'cloudinary_url' => $a->cloudinary_url,
                'title'          => $a->metadata?->title,
                'status'         => $a->status,
            ])->values();
        }

        return $data;
    }
}
