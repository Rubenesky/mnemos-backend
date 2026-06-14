<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAssetAI;
use App\Models\Asset;
use App\Services\CloudinaryService;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles bulk asset uploads — accepts up to 20 files per request.
 *
 * Each file is validated and processed independently: a failure on one
 * file does not abort the remaining uploads. The response always returns
 * HTTP 200 with a per-file result array so the caller can handle partial
 * successes without inspecting the status code.
 *
 * Accessible to admin and editor roles only.
 *
 * @author  RJC
 */
class BulkUploadController extends Controller
{
    use LogsActivity;

    /** Maximum number of files accepted in a single bulk request. */
    private const MAX_FILES = 20;

    /** Maximum file size in bytes (10 MB — mirrors single-upload limit). */
    private const MAX_BYTES = 10 * 1024 * 1024;

    /** Allowed MIME types — mirrors AssetApiController::store() validation. */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
        'audio/mpeg',
        'audio/wav',
    ];

    /**
     * Restrict bulk upload to admin and editor roles.
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
     * POST /api/assets/bulk
     *
     * Upload up to 20 files in a single multipart/form-data request.
     * Each file is validated, uploaded to Cloudinary, persisted, and queued
     * for AI processing independently.
     *
     * Request body:
     *   files[]  — array of UploadedFile objects (1–20 items)
     *
     * @return JsonResponse 200 {
     *                      results: Array<{
     *                      filename: string,
     *                      status:   'success'|'error',
     *                      asset_id: int|null,
     *                      error:    string|null
     *                      }>
     *                      }  |  422 if array validation fails (e.g. > 20 files)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
            'files.*' => ['file', 'max:51200'],
        ]);

        $results = [];

        foreach ($request->file('files') as $file) {
            $results[] = $this->processOne($file);
        }

        return response()->json(['results' => $results]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Validate, upload and persist a single file from the bulk payload.
     *
     * All exceptions are caught so that one failing file never prevents the
     * remaining files from being processed.
     *
     * @return array{filename: string, status: string, asset_id: int|null, error: string|null}
     */
    private function processOne(UploadedFile $file): array
    {
        $originalName = $file->getClientOriginalName();

        // Per-file size check
        if ($file->getSize() > self::MAX_BYTES) {
            return $this->errorResult($originalName, 'File exceeds the 10 MB limit.');
        }

        // Per-file MIME check
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES)) {
            return $this->errorResult($originalName, 'File type not allowed.');
        }

        try {
            // Exact-duplicate detection by hash
            $fileHash = md5_file($file->getRealPath());
            if (Asset::where('file_hash', $fileHash)->exists()) {
                return $this->errorResult($originalName, 'Duplicate file — already uploaded.');
            }

            $cloudinaryResult = app(CloudinaryService::class)->upload($file);

            // Store a local copy temporarily, then remove it
            $storedName = Str::uuid().'.'.$file->getClientOriginalExtension();
            $localPath = $file->storeAs('assets', $storedName, 'public');
            Storage::disk('public')->delete($localPath);

            $asset = Asset::create([
                'user_id' => auth()->id(),
                'original_name' => $originalName,
                'filename' => $storedName,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'path' => $cloudinaryResult['url'],
                'file_hash' => $fileHash,
                'cloudinary_public_id' => $cloudinaryResult['public_id'],
                'cloudinary_url' => $cloudinaryResult['url'],
                'status' => 'pending',
            ]);

            ProcessAssetAI::dispatch($asset->id);

            $this->logActivity('bulk_upload', $asset, ['filename' => $originalName]);

            return [
                'filename' => $originalName,
                'status' => 'success',
                'asset_id' => $asset->id,
                'error' => null,
            ];

        } catch (\Throwable $e) {
            return $this->errorResult($originalName, 'Upload failed: '.$e->getMessage());
        }
    }

    /**
     * Build a standardised error result for a single file.
     *
     * @return array{filename: string, status: string, asset_id: null, error: string}
     */
    private function errorResult(string $filename, string $error): array
    {
        return [
            'filename' => $filename,
            'status' => 'error',
            'asset_id' => null,
            'error' => $error,
        ];
    }
}
