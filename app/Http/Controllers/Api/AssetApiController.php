<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAssetAI;
use App\Mail\AssetUploadNotificationMail;
use App\Models\Asset;
use App\Models\AssetView;
use App\Models\User;
use App\Services\AIVariantsService;
use App\Services\NotificationService;
use App\Traits\LogsActivity;
use App\Services\CloudinaryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


/**
 * REST API controller for managing digital assets including upload, retrieval, update, deletion, and AI variant generation.
 *
 * @package App\Http\Controllers\Api
 * @author  RJC
 */
class AssetApiController extends Controller
{
    use LogsActivity;

    /**
     * Return a paginated, filtered list of assets.
     *
     * Non-admin users only receive their own assets (IDOR protection).
     * Accepts optional query parameters: search, type[], consent_status,
     * date_from, date_to, press_kit, emergency_kit, per_page.
     *
     * @param  Request $request
     * @return JsonResponse
     */
    // GET /api/assets
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user  = auth()->user();
        $query = Asset::with(['user', 'metadata', 'categories']);

        if (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $query = $this->applyFilters($query, $request);

        $perPage = in_array((int) $request->input('per_page'), [12, 24, 48])
            ? (int) $request->input('per_page')
            : 12;

        /** @var \Illuminate\Pagination\LengthAwarePaginator $assets */
        $assets = $query->latest('assets.created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $assets->getCollection()->map(function ($asset) {
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
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,txt,mp4,mov,avi,mp3,wav',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,video/mp4,video/quicktime,video/x-msvideo,audio/mpeg,audio/wav',
            ],
        ]);

        $file = $request->file('file');

        // Exact duplicate detection by hash
        $fileHash      = md5_file($file->getRealPath());
        $existingAsset = Asset::where('file_hash', $fileHash)->first();

        if ($existingAsset) {
            $existingAsset->load(['user', 'metadata', 'categories']);
            return response()->json([
                'success'        => false,
                'message'        => trans('messages.asset_duplicate'),
                'existing_asset' => $this->formatAsset($existingAsset),
            ], 409);
        }

        // Upload to Cloudinary
        $cloudinary       = app(CloudinaryService::class);
        $cloudinaryResult = $cloudinary->upload($file);

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = $file->storeAs('assets', $filename, 'public');

        // Remove the local copy — Cloudinary is the source of truth
        Storage::disk('public')->delete($path);

        $asset = Asset::create([
            'user_id'              => auth()->id(),
            'original_name'        => $file->getClientOriginalName(),
            'filename'             => $filename,
            'mime_type'            => $file->getMimeType(),
            'size'                 => $file->getSize(),
            'path'                 => $cloudinaryResult['url'],
            'file_hash'            => $fileHash,
            'cloudinary_public_id' => $cloudinaryResult['public_id'],
            'cloudinary_url'       => $cloudinaryResult['url'],
            'status'               => 'pending',
        ]);

        // Auto-assign to the first public category so it appears in the public gallery
        $defaultCategory = \App\Models\Category::where('is_public', true)->first();
        if ($defaultCategory) {
            $asset->categories()->attach($defaultCategory->id);
        }

        ProcessAssetAI::dispatch($asset->id);

        if (auth()->user()->role === 'volunteer') {
            app(NotificationService::class)->notifyAdmins('volunteer_upload', [
                'asset_id'      => $asset->id,
                'asset_name'    => $asset->original_name,
                'uploader_name' => auth()->user()->name,
            ]);

            $reviewUrl = rtrim(config('app.frontend_url', config('app.url')), '/') . '/assets';
            $uploader  = auth()->user();
            User::where('role', 'admin')->each(function (User $admin) use ($asset, $uploader, $reviewUrl) {
                Mail::to($admin->email)
                    ->send(new AssetUploadNotificationMail($asset, $uploader, $reviewUrl));
            });
        }

        $this->logActivity('upload', $asset, ['filename' => $asset->original_name]);

        return response()->json([
            'success' => true,
            'data'    => $this->formatAsset($asset->fresh(['user', 'metadata', 'categories'])),
        ], 201);
    }

    // GET /api/assets/{id}
    public function show(Asset $asset): JsonResponse
    {
        if (!$this->canAccess($asset)) {
            return response()->json(['success' => false, 'message' => trans('messages.forbidden')], 403);
        }

        $asset->load(['user', 'metadata', 'categories']);

        // Record view — wrapped in try/catch so a DB hiccup never breaks the response
        try {
            AssetView::create([
                'asset_id' => $asset->id,
                'ip_hash'  => hash('sha256', request()->ip() ?? ''),
            ]);
        } catch (\Throwable) {
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatAsset($asset),
        ]);
    }

    // PATCH /api/assets/{id}
    public function update(Request $request, Asset $asset): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (!$this->canAccess($asset)) {
            return response()->json(['success' => false, 'message' => trans('messages.forbidden')], 403);
        }

        if ($user->role === 'viewer') {
            return response()->json(['success' => false, 'message' => trans('messages.forbidden')], 403);
        }
        // An expired volunteer is treated as a viewer
        if ($user->role === 'volunteer' && $user->expires_at !== null && $user->expires_at->isPast()) {
            return response()->json(['success' => false, 'message' => 'Your volunteer access has expired.'], 403);
        }

        $validated = $request->validate([
            'title'       => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tags'        => ['nullable', 'string', 'max:1000'],
            'is_public'   => ['sometimes', 'boolean'],
            'alt_text'    => ['nullable', 'string', 'max:500'],
        ]);

        if (array_key_exists('alt_text', $validated)) {
            $asset->update(['alt_text' => $validated['alt_text']]);
        }

        if (array_key_exists('is_public', $validated)) {
            if ($validated['is_public'] === true) {
                if (auth()->user()->role === 'volunteer') {
                    return response()->json([
                        'message' => 'Volunteers cannot publish assets.',
                        'error'   => 'insufficient_role',
                    ], 403);
                }

                $blockingConsents = $asset->consents()
                    ->whereIn('status', ['denied', 'pending'])
                    ->count();

                if ($blockingConsents > 0) {
                    return response()->json([
                        'message' => 'Cannot publish this asset. It has ' . $blockingConsents . ' unresolved consent record(s).',
                        'error'   => 'consent_required',
                    ], 422);
                }
            }

            $asset->update(['is_public' => $validated['is_public']]);
        }

        if ($request->hasAny(['title', 'description', 'tags'])) {
            $asset->metadata()->updateOrCreate(
                ['asset_id' => $asset->id],
                [
                    'title'        => $request->title,
                    'description'  => $request->description,
                    'tags'         => $request->tags ? array_map('trim', explode(',', $request->tags)) : null,
                    'ai_generated' => false,
                ]
            );
        }

        $this->logActivity('edit', $asset);

        return response()->json([
            'success' => true,
            'data'    => $this->formatAsset($asset->fresh(['user', 'metadata', 'categories'])),
        ]);
    }

    // DELETE /api/assets/{id}
    public function destroy(Asset $asset): JsonResponse
    {
        if (!$this->canAccess($asset)) {
            return response()->json(['success' => false, 'message' => trans('messages.forbidden')], 403);
        }

        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => trans('messages.cannot_delete_asset'),
            ], 403);
        }

        Storage::disk('public')->delete($asset->path);
        $this->logActivity('delete', null, ['filename' => $asset->original_name]);
        $asset->delete();

        return response()->json([
            'success' => true,
            'message' => trans('messages.asset_deleted'),
        ]);
    }

    // POST /api/assets/{id}/variants
    public function variants(Asset $asset): JsonResponse
    {
        if (!$this->canAccess($asset)) {
            return response()->json(['success' => false, 'message' => trans('messages.forbidden')], 403);
        }

        if (!$asset->metadata) {
            return response()->json([
                'success' => false,
                'message' => 'This asset has no generated metadata yet.',
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
                'message' => 'Could not generate variants. Please try again.',
            ], 503);
        }

        return response()->json([
            'success'  => true,
            'variants' => $variants,
        ]);
    }

    /**
     * Apply optional server-side filters to an asset query.
     *
     * Supported parameters:
     *  - search         (string)  LIKE on title, description, tags, original_name
     *  - type[]         (array)   Mime-type categories: image, video, audio, pdf, word, text
     *  - consent_status (string)  obtained|pending|denied → whereHas; none → whereDoesntHave
     *  - date_from      (date)    assets.created_at >= value
     *  - date_to        (date)    assets.created_at <= value
     *  - press_kit      (0|1)     where is_press_kit
     *  - emergency_kit  (0|1)     where is_emergency_kit
     *
     * @param  Builder $query
     * @param  Request $request
     * @return Builder
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        // --- search -----------------------------------------------------------
        if ($search = $request->input('search')) {
            $query->leftJoin('asset_metadata', 'asset_metadata.asset_id', '=', 'assets.id')
                  ->select('assets.*')
                  ->groupBy('assets.id')
                  ->where(function (Builder $q) use ($search) {
                      $term = '%' . $search . '%';
                      $q->where('asset_metadata.title', 'LIKE', $term)
                        ->orWhere('asset_metadata.description', 'LIKE', $term)
                        ->orWhere('asset_metadata.tags', 'LIKE', $term)
                        ->orWhere('assets.original_name', 'LIKE', $term);
                  });
        }

        // --- type[] -----------------------------------------------------------
        if ($types = $request->input('type')) {
            $types = (array) $types;

            /** Map friendly category names to SQL LIKE patterns / exact values */
            $mimeMap = [
                'image' => [['LIKE', 'image/%']],
                'video' => [['LIKE', 'video/%']],
                'audio' => [['LIKE', 'audio/%']],
                'pdf'   => [['=',    'application/pdf']],
                'word'  => [
                    ['=', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                    ['=', 'application/msword'],
                ],
                'text'  => [['LIKE', 'text/%']],
            ];

            $validPatterns = [];
            foreach ($types as $type) {
                if (isset($mimeMap[$type])) {
                    foreach ($mimeMap[$type] as $pattern) {
                        $validPatterns[] = $pattern;
                    }
                }
            }

            if (empty($validPatterns)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function (Builder $q) use ($validPatterns) {
                    foreach ($validPatterns as [$operator, $value]) {
                        if ($operator === 'LIKE') {
                            $q->orWhere('assets.mime_type', 'LIKE', $value);
                        } else {
                            $q->orWhere('assets.mime_type', '=', $value);
                        }
                    }
                });
            }
        }

        // --- consent_status ---------------------------------------------------
        if ($consentStatus = $request->input('consent_status')) {
            if ($consentStatus === 'none') {
                $query->whereDoesntHave('consents');
            } elseif (in_array($consentStatus, ['obtained', 'pending', 'denied'])) {
                $query->whereHas('consents', function (Builder $q) use ($consentStatus) {
                    $q->where('status', $consentStatus);
                });
            }
        }

        // --- date_from / date_to ----------------------------------------------
        if ($dateFrom = $request->input('date_from')) {
            if (\Carbon\Carbon::hasFormat($dateFrom, 'Y-m-d')) {
                $query->whereDate('assets.created_at', '>=', $dateFrom);
            }
        }

        if ($dateTo = $request->input('date_to')) {
            if (\Carbon\Carbon::hasFormat($dateTo, 'Y-m-d')) {
                $query->whereDate('assets.created_at', '<=', $dateTo);
            }
        }

        // --- press_kit --------------------------------------------------------
        if ($request->filled('press_kit')) {
            $query->where('is_press_kit', (bool) $request->input('press_kit'));
        }

        // --- emergency_kit ----------------------------------------------------
        if ($request->filled('emergency_kit')) {
            $query->where('is_emergency_kit', (bool) $request->input('emergency_kit'));
        }

        return $query;
    }

    private function canAccess(Asset $asset): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user->isAdmin() || $asset->user_id === $user->id;
    }

    // Format an asset for the JSON response
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
            'alt_text'              => $asset->alt_text,
            'is_public'             => (bool) $asset->is_public,
            'is_press_kit'          => (bool) $asset->is_press_kit,
            'press_kit_description' => $asset->press_kit_description,
            'is_emergency_kit'      => (bool) $asset->is_emergency_kit,
            'created_at'            => $asset->created_at->toISOString(),
        ];
    }
}