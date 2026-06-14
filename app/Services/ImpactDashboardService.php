<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetView;
use App\Models\Consent;

/**
 * Aggregates impact and transparency metrics for the dashboard endpoint.
 *
 * Uses PHP-side date grouping for assets_by_month so queries remain
 * compatible with both SQLite (tests) and PostgreSQL (production).
 *
 * @author  RJC
 */
class ImpactDashboardService
{
    /** Minutes of manual alt-text writing saved per AI-generated description. */
    private const HOURS_PER_ALT_TEXT = 0.083;

    /**
     * Gather all impact dashboard metrics.
     *
     * @return array{
     *   summary: array{
     *     total_assets:        int,
     *     public_assets:       int,
     *     total_downloads:     int,
     *     consents_granted:    int,
     *     alt_texts_generated: int,
     *     hours_saved:         float,
     *   },
     *   trends: array{
     *     assets_last_30_days:   int,
     *     consents_last_30_days: int,
     *     assets_by_month:       list<array{month: string, count: int}>,
     *   },
     *   top_assets: list<array{id: int, title: string, thumbnail: string|null, view_count: int}>,
     * }
     */
    public function gather(): array
    {
        $altTextsGenerated = Asset::whereNotNull('alt_text')->count();

        return [
            'summary' => [
                'total_assets' => Asset::count(),
                'public_assets' => Asset::where('is_public', true)->count(),
                'total_downloads' => AssetView::count(),
                'consents_granted' => Consent::where('status', 'obtained')->count(),
                'alt_texts_generated' => $altTextsGenerated,
                'hours_saved' => round($altTextsGenerated * self::HOURS_PER_ALT_TEXT, 2),
            ],
            'trends' => [
                'assets_last_30_days' => Asset::where('created_at', '>=', now()->subDays(30))->count(),
                'consents_last_30_days' => Consent::where('created_at', '>=', now()->subDays(30))->count(),
                'assets_by_month' => $this->assetsByMonth(),
                'consents_by_month' => $this->consentsByMonth(),
            ],
            'top_assets' => $this->topAssets(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return asset upload counts grouped by calendar month for the last 6 months.
     *
     * Groups in PHP rather than SQL to avoid database-specific date functions.
     * Months with zero uploads are included so the frontend chart has a fixed
     * 6-point x-axis.
     *
     * @return list<array{month: string, count: int}>
     */
    private function assetsByMonth(): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[now()->subMonths($i)->format('Y-m')] = 0;
        }

        Asset::where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->pluck('created_at')
            ->each(function ($date) use (&$months) {
                $key = $date->format('Y-m');
                if (array_key_exists($key, $months)) {
                    $months[$key]++;
                }
            });

        return collect($months)
            ->map(fn ($count, $month) => ['month' => $month, 'count' => $count])
            ->values()
            ->toArray();
    }

    /**
     * Return consent creation counts grouped by calendar month for the last 6 months.
     *
     * Same PHP-side grouping strategy as assetsByMonth() for cross-DB compatibility.
     *
     * @return list<array{month: string, count: int}>
     */
    private function consentsByMonth(): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[now()->subMonths($i)->format('Y-m')] = 0;
        }

        Consent::where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->pluck('created_at')
            ->each(function ($date) use (&$months) {
                $key = $date->format('Y-m');
                if (array_key_exists($key, $months)) {
                    $months[$key]++;
                }
            });

        return collect($months)
            ->map(fn ($count, $month) => ['month' => $month, 'count' => $count])
            ->values()
            ->toArray();
    }

    /**
     * Return the top 5 most-viewed assets with view counts.
     *
     * @return list<array{id: int, title: string, thumbnail: string|null, view_count: int}>
     */
    private function topAssets(): array
    {
        return Asset::with('metadata:asset_id,title')
            ->withCount('assetViews')
            ->orderByDesc('asset_views_count')
            ->limit(5)
            ->get()
            ->map(fn ($asset) => [
                'id' => $asset->id,
                'title' => $asset->metadata?->title ?? $asset->original_name,
                'thumbnail' => $asset->cloudinary_url,
                'view_count' => $asset->asset_views_count,
            ])
            ->toArray();
    }
}
