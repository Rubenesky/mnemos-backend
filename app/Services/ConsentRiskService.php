<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Consent;

/**
 * Calculates GDPR consent risk level and surfaces actionable alerts.
 *
 * Risk levels (evaluated highest → lowest; first match wins):
 *   high   — blocked assets > 20%  OR  pending consents > 50
 *              OR  rejection rate > 10% of total consents
 *   medium — blocked assets 5–20%  OR  pending consents 10–50
 *   low    — blocked assets < 5%  AND  pending consents < 10
 *
 * An asset is considered "blocked" when it has at least one consent
 * with status 'pending' or 'denied'.
 *
 * @author  RJC
 */
class ConsentRiskService
{
    /**
     * Compute current GDPR risk metrics and actionable alerts.
     *
     * @return array{
     *   pending_consents:   int,
     *   accepted_consents:  int,
     *   rejected_consents:  int,
     *   blocked_assets:     int,
     *   total_assets:       int,
     *   blocked_percentage: float,
     *   risk_level:         'low'|'medium'|'high',
     *   alerts:             array<int, array{key: string, count?: int}>,
     * }
     */
    public function calculateRisk(): array
    {
        $totalAssets = Asset::count();
        $pendingConsents = Consent::where('status', 'pending')->count();
        $acceptedConsents = Consent::where('status', 'obtained')->count();
        $rejectedConsents = Consent::where('status', 'denied')->count();
        $totalConsents = $pendingConsents + $acceptedConsents + $rejectedConsents;

        // An asset is blocked when it has ≥1 consent that is pending or denied
        $blockedAssets = Asset::whereHas(
            'consents',
            fn ($q) => $q->whereIn('status', ['pending', 'denied'])
        )->count();

        $blockedPct = $totalAssets > 0
            ? round(($blockedAssets / $totalAssets) * 100, 2)
            : 0.0;

        return [
            'pending_consents' => $pendingConsents,
            'accepted_consents' => $acceptedConsents,
            'rejected_consents' => $rejectedConsents,
            'blocked_assets' => $blockedAssets,
            'total_assets' => $totalAssets,
            'blocked_percentage' => $blockedPct,
            'risk_level' => $this->resolveRiskLevel($blockedPct, $pendingConsents, $rejectedConsents, $totalConsents),
            'alerts' => $this->buildAlerts($blockedAssets, $pendingConsents, $rejectedConsents, $totalConsents),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve the risk level from the computed metrics.
     *
     * Evaluated from highest to lowest severity so HIGH always takes precedence.
     *
     * @param  float  $blockedPct  Percentage of assets that are blocked (0–100)
     * @param  int  $pending  Number of pending consent records
     * @param  int  $rejected  Number of denied consent records
     * @param  int  $total  Total consent records (pending + accepted + rejected)
     * @return 'low'|'medium'|'high'
     */
    private function resolveRiskLevel(float $blockedPct, int $pending, int $rejected, int $total): string
    {
        $rejectionRate = $total > 0 ? $rejected / $total : 0.0;

        if ($blockedPct > 20 || $pending > 50 || $rejectionRate > 0.10) {
            return 'high';
        }

        if ($blockedPct >= 5 || $pending >= 10) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Build a list of structured alert descriptors for frontend translation.
     *
     * Each item carries a 'key' (i18n key suffix under admin.gdpr.alerts)
     * and an optional 'count' for placeholder interpolation. The frontend
     * translates via t('admin.gdpr.alerts.' + alert.key, { count: alert.count }).
     *
     * Returns an empty array when everything is within safe thresholds.
     *
     * @param  int  $blocked  Number of assets that cannot be published
     * @param  int  $pending  Number of pending consent records
     * @param  int  $rejected  Number of denied consent records
     * @param  int  $total  Total consent records
     * @return array<int, array{key: string, count?: int}>
     */
    private function buildAlerts(int $blocked, int $pending, int $rejected, int $total): array
    {
        $alerts = [];

        if ($blocked > 0) {
            $alerts[] = ['key' => 'assetsBlocked', 'count' => $blocked];
        }

        if ($pending > 50) {
            $alerts[] = ['key' => 'pendingHigh'];
        } elseif ($pending >= 10) {
            $alerts[] = ['key' => 'pendingMedium', 'count' => $pending];
        }

        if ($total > 0 && ($rejected / $total) > 0.10) {
            $alerts[] = ['key' => 'rejectionRateExceeded'];
        }

        return $alerts;
    }
}
