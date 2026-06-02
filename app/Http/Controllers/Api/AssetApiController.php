<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAssetAI;
use App\Models\Asset;
use App\Services\AIVariantsService;
use App\Traits\LogsActivity;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


/**
 * REST API controller for managing digital assets including upload, retrieval, update, deletion, and AI variant generation.
 *
 * @package App\Http\Controllers\Api
 */
class AssetApiController extends Controller
{
    use LogsActivity;

    // GET /api/assets
    public function index(): JsonResponse
    {
        $assets = Asset::with(['user', 'metadata', 'categories'])
                       ->latest()
                       ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $assets->map(function ($asset) {
                return $this->formatAsset($asset);
            }),
            'meta' => [
                'total'        => $assets->total(),
                'per_page'     => $assets->perPage(),
                'current_page' => $assets->currentPage(),
                'last_page'    => $assets->lastPage(),
            ]
        ]);
    }

    // POST /api/assets
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,mp4,mov,avi,mp3,wav',
            ],
        ]);

        $file = $request->file('file');

        // Detección de duplicado exacto por hash
        $fileHash      = md5_file($file->getRealPath());
        $existingAsset = Asset::where('file_hash', $fileHash)->first();

        if ($existingAsset) {
            $existingAsset->load(['user', 'metadata', 'categories']);
            return response()->json([
                'success'        => false,
                'message'        => 'Este archivo ya existe en la plataforma.',
                'existing_asset' => $this->formatAsset($existingAsset),
            ], 409);
        }

        // Subir a Cloudinary
        $cloudinary       = app(CloudinaryService::class);
        $cloudinaryResult = $cloudinary->upload($file);

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = $file->storeAs('assets', $filename, 'public');

        $asset = Asset::create([
            'user_id'              => auth()->id(),
            'original_name'        => $file->getClientOriginalName(),
            'filename'             => $filename,
            'mime_type'            => $file->getMimeType(),
            'size'                 => $file->getSize(),
            'path'                 => $path,
            'file_hash'            => $fileHash,
            'cloudinary_public_id' => $cloudinaryResult['public_id'],
            'cloudinary_url'       => $cloudinaryResult['url'],
            'status'               => 'pending',
        ]);

        ProcessAssetAI::dispatch($asset->id);
        $this->logActivity('upload', $asset, ['filename' => $asset->original_name]);

        return response()->json([
            'success' => true,
            'data'    => $this->formatAsset($asset->fresh(['user', 'metadata', 'categories'])),
        ], 201);
    }

    // GET /api/assets/{id}
    public function show(Asset $asset): JsonResponse
    {
        $asset->load(['user', 'metadata', 'categories']);

        return response()->json([
            'success' => true,
            'data'    => $this->formatAsset($asset),
        ]);
    }

    // PATCH /api/assets/{id}
    public function update(Request $request, Asset $asset): JsonResponse
    {
        if (auth()->user()->role === 'viewer') {
            return response()->json(['success' => false, 'message' => 'Sin permiso.'], 403);
        }

        $request->validate([
            'title'       => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tags'        => ['nullable', 'string', 'max:1000'],
        ]);

        $asset->metadata()->updateOrCreate(
            ['asset_id' => $asset->id],
            [
                'title'        => $request->title,
                'description'  => $request->description,
                'tags'         => $request->tags ? array_map('trim', explode(',', $request->tags)) : null,
                'ai_generated' => false,
            ]
        );

        $this->logActivity('edit', $asset);

        return response()->json([
            'success' => true,
            'data'    => $this->formatAsset($asset->fresh(['user', 'metadata', 'categories'])),
        ]);
    }

    // DELETE /api/assets/{id}
    public function destroy(Asset $asset): JsonResponse
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para eliminar assets.',
            ], 403);
        }

        Storage::disk('public')->delete($asset->path);
        $this->logActivity('delete', null, ['filename' => $asset->original_name]);
        $asset->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asset eliminado correctamente.',
        ]);
    }

    // POST /api/assets/{id}/variants
    public function variants(Asset $asset): JsonResponse
    {
        if (!$asset->metadata) {
            return response()->json([
                'success' => false,
                'message' => 'Este asset no tiene metadatos generados todavía.',
            ], 422);
        }

        $variantsService = new AIVariantsService();
        $variants        = $variantsService->generateVariants(
            $asset->metadata->title ?? '',
            $asset->metadata->description ?? '',
            $asset->metadata->tags ?? []
        );

        if (empty($variants)) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudieron generar variantes. Inténtalo de nuevo.',
            ], 503);
        }

        return response()->json([
            'success'  => true,
            'variants' => $variants,
        ]);
    }

    // Formatea un asset para la respuesta JSON
    private function formatAsset(Asset $asset): array
    {
        return [
            'id'            => $asset->id,
            'original_name' => $asset->original_name,
            'mime_type'     => $asset->mime_type,
            'size_kb'       => round($asset->size / 1024, 2),
            'status'        => $asset->status,
            'url' => $asset->cloudinary_url
                    ?: (str_starts_with($asset->path, 'http')
                        ? $asset->path
                        : asset('storage/' . $asset->path)),
            'uploaded_by'   => $asset->user->name,
            'metadata'      => $asset->metadata ? [
                'title'        => $asset->metadata->title,
                'description'  => $asset->metadata->description,
                'tags'         => $asset->metadata->tags,
                'ai_generated' => $asset->metadata->ai_generated,
            ] : null,
            'categories' => $asset->categories->map(fn($c) => [
                'id'   => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
            ]),
            'created_at' => $asset->created_at->toISOString(),
        ];
    }
}