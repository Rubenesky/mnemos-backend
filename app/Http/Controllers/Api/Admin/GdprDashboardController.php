<?php

// RJC

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Services\ConsentRiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin-only endpoints for the GDPR Intelligence Panel.
 *
 * All routes sit inside the `admin` middleware group and therefore
 * require auth:sanctum + AdminOnly middleware.
 *
 * Routes:
 *   GET /api/admin/gdpr/dashboard     — risk summary, KPIs, alerts (cached 5 min)
 *   GET /api/admin/gdpr/audit/export  — full CSV export of all consent records
 *
 * @package App\Http\Controllers\Api\Admin
 * @author  RJC
 */
class GdprDashboardController extends Controller
{
    /**
     * @param ConsentRiskService $risk  Computes consent risk metrics and alerts
     */
    public function __construct(
        private readonly ConsentRiskService $risk,
    ) {}

    /**
     * GET /api/admin/gdpr/dashboard
     *
     * Returns a risk snapshot for the GDPR Intelligence Panel.
     * Results are cached for 5 minutes (300 s) to avoid hammering the database
     * on every auto-refresh tick from the frontend.
     *
     * @return JsonResponse 200 {
     *   data: {
     *     pending_consents:   int,
     *     accepted_consents:  int,
     *     rejected_consents:  int,
     *     blocked_assets:     int,
     *     total_assets:       int,
     *     blocked_percentage: float,
     *     risk_level:         'low'|'medium'|'high',
     *     alerts:             string[],
     *   }
     * }
     */
    public function dashboard(): JsonResponse
    {
        $data = Cache::remember('gdpr.dashboard', 300, fn () => $this->risk->calculateRisk());

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/admin/gdpr/audit/export
     *
     * Streams a UTF-8 CSV of every consent record in the system.
     * Uses chunked processing (200 records at a time) so the export
     * does not load the entire table into memory at once.
     *
     * CSV columns:
     *   asset_title, person_name, person_email, status, consent_type,
     *   created_at, responded_at, token_expires_at
     *
     * @return StreamedResponse
     */
    public function exportCsv(): StreamedResponse
    {
        $filename = 'gdpr_audit_' . now()->format('Y-m-d') . '.csv';

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens the file without encoding issues
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'asset_title',
                'person_name',
                'person_email',
                'status',
                'consent_type',
                'created_at',
                'responded_at',
                'token_expires_at',
            ]);

            Consent::with('asset.metadata')
                ->orderBy('created_at')
                ->chunk(200, function ($consents) use ($handle) {
                    foreach ($consents as $consent) {
                        // Prefer AI-generated title; fall back to original filename
                        $title = $consent->asset?->metadata?->title
                            ?? $consent->asset?->original_name
                            ?? '';

                        fputcsv($handle, [
                            $title,
                            $consent->person_name,
                            $consent->person_email ?? '',
                            $consent->status,
                            $consent->consent_type,
                            $consent->created_at?->format('Y-m-d H:i:s') ?? '',
                            $consent->responded_at?->format('Y-m-d H:i:s') ?? '',
                            $consent->token_expires_at?->format('Y-m-d H:i:s') ?? '',
                        ]);
                    }
                });

            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
