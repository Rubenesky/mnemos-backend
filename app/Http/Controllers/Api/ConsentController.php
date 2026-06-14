<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ConsentRequestMail;
use App\Models\Asset;
use App\Models\Consent;
use App\Services\ConsentTokenService;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manages GDPR consent records for digital assets.
 * Admin and editor roles may read and manage consents.
 */
class ConsentController extends Controller
{
    use LogsActivity;

    /**
     * Restrict all consent operations to admin and editor roles.
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $role = auth()->user()?->role;
            if (! in_array($role, ['admin', 'editor'])) {
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
        $query = Consent::with([
            'asset:id,original_name,cloudinary_url,mime_type',
            'asset.metadata:asset_id,title',
        ])
            ->latest();

        if (! auth()->user()->isAdmin()) {
            $query->whereHas('asset', fn ($q) => $q->where('user_id', auth()->id()));
        }

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
            'asset_id' => 'required|exists:assets,id',
            'person_name' => 'required|string|max:255',
            'person_email' => 'nullable|email|max:255',
            'consent_date' => 'required|date',
            'consent_type' => 'required|in:photo,video,audio,general',
            'status' => 'required|in:obtained,pending,denied',
            'notes' => 'nullable|string|max:1000',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
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

        $this->logActivity('consent-create', $asset, [
            'person_name' => $validated['person_name'],
            'consent_type' => $validated['consent_type'],
            'status' => $validated['status'],
        ]);

        return response()->json(['data' => $consent->load('asset:id,original_name')], 201);
    }

    /**
     * Show a single consent record.
     */
    public function show(Consent $consent): JsonResponse
    {
        if (! auth()->user()->isAdmin()) {
            $consent->loadMissing('asset');
            if ($consent->asset?->user_id !== auth()->id()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        return response()->json(['data' => $consent->load('asset:id,original_name,cloudinary_url')]);
    }

    /**
     * Update an existing consent record.
     */
    public function update(Request $request, Consent $consent): JsonResponse
    {
        if (! auth()->user()->isAdmin()) {
            $consent->loadMissing('asset');
            if ($consent->asset?->user_id !== auth()->id()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        $validated = $request->validate([
            'person_name' => 'sometimes|string|max:255',
            'person_email' => 'sometimes|nullable|email|max:255',
            'consent_date' => 'sometimes|date',
            'consent_type' => 'sometimes|in:photo,video,audio,general',
            'status' => 'sometimes|in:obtained,pending,denied',
            'notes' => 'nullable|string|max:1000',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('document')) {
            if ($consent->document_path && ! Storage::disk('local')->delete($consent->document_path)) {
                \Log::warning('Failed to delete consent document', ['consent_id' => $consent->id]);
            }
            $validated['document_path'] = $request->file('document')->store('consents', 'local');
        }

        $consent->update($validated);

        $consent->loadMissing('asset');
        $this->logActivity('consent-update', $consent->asset, [
            'person_name' => $consent->person_name,
            'status' => $consent->status,
        ]);

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

        if ($consent->document_path && ! Storage::disk('local')->delete($consent->document_path)) {
            \Log::warning('Failed to delete consent document', ['consent_id' => $consent->id]);
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
        }, 'consents_'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="consents_'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    /**
     * Check whether an asset can be safely published (no denied or pending consents).
     * Returns JSON with canPublish boolean and list of blocking consents.
     */
    public function publicationCheck(Asset $asset): JsonResponse
    {
        if (! auth()->user()->isAdmin() && $asset->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $blockingConsents = $asset->consents()
            ->whereIn('status', ['denied', 'pending'])
            ->get(['id', 'person_name', 'status', 'consent_type']);

        return response()->json([
            'can_publish' => $blockingConsents->isEmpty(),
            'blocking_consents' => $blockingConsents,
        ]);
    }

    /**
     * Generate a public consent request token and return the shareable URL.
     * The link is valid for 7 days and can be sent to the person whose consent is required.
     */
    public function sendRequest(Consent $consent, ConsentTokenService $service): JsonResponse
    {
        $token = $service->generateToken($consent);

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $url = $frontendUrl.'/consent/'.$token;

        $consent->loadMissing('asset.metadata');

        // Send consent request email if the person's email is known
        if ($consent->person_email) {
            $expiresAt = now()->addDays(7)->format('d F Y');
            $orgName = config('app.name', 'Mnemos');
            Mail::to($consent->person_email)
                ->send(new ConsentRequestMail($consent, $url, $expiresAt, $orgName));
        }

        return response()->json([
            'data' => [
                'token' => $token,
                'url' => $url,
                'expires_at' => now()->addDays(7)->toISOString(),
            ],
        ]);
    }
}
