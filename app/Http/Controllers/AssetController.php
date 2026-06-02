<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetMetadata;
use App\Models\Category;
use App\Services\CloudinaryService;
use App\Services\DuplicateDetectionService;
use App\Services\GeminiService;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles web UI CRUD operations for digital assets including upload, display, edit, and deletion.
 *
 * @package App\Http\Controllers
 */
class AssetController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $query = Asset::with(['user', 'metadata', 'categories']);

        if ($request->filled('search')) {
            $query->where('original_name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('type')) {
            $query->where('mime_type', 'like', $request->type . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category);
            });
        }

        $assets     = $query->latest()->paginate(12)->withQueryString();
        $categories = Category::whereNull('parent_id')->with('children')->get();

        return view('assets.index', compact('assets', 'categories'));
    }

    public function create()
    {
        $categories = Category::whereNull('parent_id')->with('children')->get();
        return view('assets.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file'         => ['required', 'file', 'max:10240'],
            'categories'   => ['nullable', 'array'],
            'categories.*' => ['exists:categories,id'],
        ]);

        $file = $request->file('file');

        // Detección de duplicado exacto por hash
        $fileHash      = md5_file($file->getRealPath());
        $existingAsset = Asset::where('file_hash', $fileHash)->first();

        if ($existingAsset) {
            return redirect()->route('assets.show', $existingAsset)
                             ->with('warning', '⚠️ Este archivo ya existe en la plataforma.');
        }

        // Subir a Cloudinary
        $cloudinary       = new CloudinaryService();
        $cloudinaryResult = $cloudinary->upload($file);

        // También guardar localmente como backup
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

        if ($request->has('categories')) {
            $asset->categories()->sync($request->categories);
        }

        $gemini   = new GeminiService();
        $metadata = $gemini->generateAssetMetadata(
            $file->getClientOriginalName(),
            $file->getMimeType(),
            $path
        );

        AssetMetadata::create([
            'asset_id'     => $asset->id,
            'title'        => $metadata['title'],
            'description'  => $metadata['description'],
            'tags'         => $metadata['tags'],
            'ai_generated' => true,
        ]);

        $asset->update(['status' => 'processed']);
        $this->logActivity('upload', $asset, ['filename' => $asset->original_name]);

        $duplicateDetector = new DuplicateDetectionService();
        $similarAssets     = $duplicateDetector->findSimilar(
            $asset->id,
            $metadata['description'] ?? '',
            $metadata['tags'] ?? []
        );

        if (!empty($similarAssets)) {
            $similarIds = collect($similarAssets)->pluck('id')->join(', #');
            return redirect()->route('assets.show', $asset)
                             ->with('warning', "Asset subido. ⚠️ Assets similares detectados: #{$similarIds}");
        }

        return redirect()->route('assets.show', $asset)
                         ->with('success', 'Asset subido y metadatos generados por IA.');
    }

    public function show(Asset $asset)
    {
        $asset->load(['user', 'metadata', 'categories']);
        return view('assets.show', compact('asset'));
    }

    public function edit(Asset $asset)
    {
        $categories = Category::whereNull('parent_id')->with('children')->get();
        return view('assets.edit', compact('asset', 'categories'));
    }

    public function update(Request $request, Asset $asset)
    {
        $request->validate([
            'categories'   => ['nullable', 'array'],
            'categories.*' => ['exists:categories,id'],
        ]);

        if ($request->has('categories')) {
            $asset->categories()->sync($request->categories);
        }

        $asset->metadata()->updateOrCreate(
            ['asset_id' => $asset->id],
            [
                'title'        => $request->title,
                'description'  => $request->description,
                'tags'         => $request->tags ? explode(',', $request->tags) : null,
                'ai_generated' => false,
            ]
        );

        $this->logActivity('edit', $asset);

        return redirect()->route('assets.show', $asset)
                         ->with('success', 'Asset actualizado correctamente.');
    }

    public function destroy(Asset $asset)
    {
        // Borrar de Cloudinary si existe
        if ($asset->cloudinary_public_id) {
            $cloudinary = new CloudinaryService();
            $cloudinary->delete($asset->cloudinary_public_id);
        }

        Storage::disk('public')->delete($asset->path);
        $this->logActivity('delete', null, ['filename' => $asset->original_name]);
        $asset->delete();

        return redirect()->route('assets.index')
                         ->with('success', 'Asset eliminado correctamente.');
    }
}