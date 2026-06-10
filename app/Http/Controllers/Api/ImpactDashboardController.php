<?php

// RJC

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImpactDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Serves the live impact and transparency dashboard.
 *
 * Distinct from ReportController (which generates downloadable PDF reports).
 * Results are cached for 10 minutes to reduce DB load from the frontend's
 * auto-refresh interval of 5 minutes.
 *
 * Route (auth:sanctum):
 *   GET /api/reports/impact-dashboard  — admin only
 *
 * @package App\Http\Controllers\Api
 * @author  RJC
 */
class ImpactDashboardController extends Controller
{
    /**
     * @param ImpactDashboardService $dashboard  Aggregates all impact metrics
     */
    public function __construct(
        private readonly ImpactDashboardService $dashboard,
    ) {}

    /**
     * GET /api/reports/impact-dashboard
     *
     * Returns summary KPIs, monthly upload trends, and the top 5 most-viewed
     * assets. Results are cached for 10 minutes (600 s).
     *
     * @return JsonResponse 200 {
     *   data: {
     *     summary:    { total_assets, public_assets, total_downloads,
     *                   consents_granted, alt_texts_generated, hours_saved },
     *     trends:     { assets_last_30_days, consents_last_30_days, assets_by_month },
     *     top_assets: [ { id, title, thumbnail, view_count } ],
     *   }
     * }
     * | 403 if the authenticated user is not an admin
     */
    public function dashboard(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = Cache::remember('impact.dashboard', 600, fn () => $this->dashboard->gather());

        return response()->json(['data' => $data]);
    }
}
