<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Consent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manages GDPR consent records for digital assets.
 * Admin and editor roles may read and manage consents.
 *
 * @package App\Http\Controllers\Api
 */
class ConsentController extends Controller
{
    /**
     * Restrict all consent operations to admin and editor roles.
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $role = auth()->user()?->role;
            if (!in_array($role, ['admin', 'editor'])) {
                return response()->json(['message' => 'Unauthorized. Admin or editor role required.'], 403);
            }
            return $next($request);
        });
    }

    /**
     * List all consent records, optionally filtered by asset_id or status.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Consent::with('asset:id,original_name,cloudinary_url,mime_type')
            ->latest();

        if ($request->has('asset_id')) {
            $query->where('asset_id', $request->integer('asset_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json(['data' => $query->paginate(20)]);
    }

    /**
     * Store a new consent record.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_id'     => 'required|exists:assets,id',
            'person_name'  => 'required|string|max:255',
            'consent_date' => 'required|date',
            'consent_type' => 'required|in:photo,video,audio,general',
            'status'       => 'required|in:obtained,pending,denied',
            'notes'        => 'nullable|string|max:1000',
            'document'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Only admin can manage any asset; editors can only manage their own assets
        $asset = Asset::find($validated['asset_id']);
        if (auth()->user()->role !== 'admin' && $asset->user_id !== auth()->id()) {
            return response()->json(['message' => 'You do not have permission to add consent for this asset.'], 403);
        }

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('consents', 'local');
        }

        $consent = Consent::create([
            ...$validated,
            'document_path' => $documentPath,
        ]);

        return response()->json(['data' => $consent->load('asset:id,original_name')], 201);
    }

    /**
     * Show a single consent record.
     */
    public function show(Consent $consent): JsonResponse
    {
        return response()->json(['data' => $consent->load('asset:id,original_name,cloudinary_url')]);
    }

    /**
     * Update an existing consent record.
     */
    public function update(Request $request, Consent $consent): JsonResponse
    {
        $validated = $request->validate([
            'person_name'  => 'sometimes|string|max:255',
            'consent_date' => 'sometimes|date',
            'consent_type' => 'sometimes|in:photo,video,audio,general',
            'status'       => 'sometimes|in:obtained,pending,denied',
            'notes'        => 'nullable|string|max:1000',
            'document'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('document')) {
            if ($consent->document_path && !Storage::disk('local')->delete($consent->document_path)) {
                \Log::warning('Failed to delete consent document: ' . $consent->document_path);
            }
            $validated['document_path'] = $request->file('document')->store('consents', 'local');
        }

        $consent->update($validated);

        return response()->json(['data' => $consent->fresh()->load('asset:id,original_name')]);
    }

    /**
     * Delete a consent record and its attached document.
     */
    public function destroy(Consent $consent): JsonResponse
    {
        $consent->load('asset');

        // Only admin can delete any consent; editors can only delete consents for their own assets
        if (auth()->user()->role !== 'admin' && $consent->asset->user_id !== auth()->id()) {
            return response()->json(['message' => 'You do not have permission to delete this consent record.'], 403);
        }

        if ($consent->document_path && !Storage::disk('local')->delete($consent->document_path)) {
            \Log::warning('Failed to delete consent document: ' . $consent->document_path);
        }

        $consent->delete();

        return response()->json(['message' => 'Consent record deleted.']);
    }

    /**
     * Export all consent records as a CSV file.
     * Useful for GDPR audits and compliance reports.
     */
    public function exportCsv(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Asset', 'Person', 'Date', 'Type', 'Status', 'Notes', 'Created At']);

            Consent::with('asset:id,original_name')->chunk(500, function ($consents) use ($handle) {
                foreach ($consents as $consent) {
                    fputcsv($handle, [
                        $consent->id,
                        $consent->asset?->original_name ?? 'N/A',
                        $consent->person_name,
                        $consent->consent_date->format('Y-m-d'),
                        $consent->consent_type,
                        $consent->status,
                        $consent->notes ?? '',
                        $consent->created_at->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($handle);
        }, 'consents_' . now()->format('Y-m-d') . '.csv', [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="consents_' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Check whether an asset can be safely published (no denied or pending consents).
     * Returns JSON with canPublish boolean and list of blocking consents.
     */
    public function publicationCheck(Asset $asset): JsonResponse
    {
        $blockingConsents = $asset->consents()
            ->whereIn('status', ['denied', 'pending'])
            ->get(['id', 'person_name', 'status', 'consent_type']);

        return response()->json([
            'can_publish' => $blockingConsents->isEmpty(),
            'blocking_consents' => $blockingConsents,
        ]);
    }
}
