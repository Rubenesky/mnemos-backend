<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Consent;
use App\Models\User;

/**
 * Collects aggregate statistics for the impact report.
 * Includes org branding fields sourced from OrganizationSettingsService.
 */
class ImpactReportService
{
    /**
     * @param OrganizationSettingsService $settings  Provides org_name and org_logo_url for report header
     */
    public function __construct(
        private readonly OrganizationSettingsService $settings,
    ) {}

    public function gather(string $period = 'all'): array
    {
        $startDate = match ($period) {
            'month'   => now()->startOfMonth(),
            'quarter' => now()->startOfQuarter(),
            'year'    => now()->startOfYear(),
            default   => null,
        };

        $q = fn() => $startDate
            ? Asset::where('created_at', '>=', $startDate)
            : Asset::query();

        $totalAssets = $q()->count();
        $byStatus    = $q()->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $images    = $q()->where('mime_type', 'like', 'image/%')->count();
        $videos    = $q()->where('mime_type', 'like', 'video/%')->count();
        $documents = $q()->where('mime_type', 'like', 'application/%')->count();
        $audio     = $q()->where('mime_type', 'like', 'audio/%')->count();
        $other     = $totalAssets - $images - $videos - $documents - $audio;

        $consentStats = Consent::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $userStats = User::selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->toArray();

        return [
            'generated_at'  => now()->format('d/m/Y H:i'),
            'period'        => $period,
            'org_name'      => $this->settings->get('org_name', 'Mnemos'),
            'org_logo_url'  => $this->settings->get('org_logo_url', ''),
            'assets'        => [
                'total'         => $totalAssets,
                'processed'     => $byStatus['processed'] ?? 0,
                'pending'       => $byStatus['pending']   ?? 0,
                'failed'        => $byStatus['failed']    ?? 0,
                'press_kit'     => Asset::where('is_press_kit', true)->count(),
                'emergency_kit' => Asset::where('is_emergency_kit', true)->count(),
                'public'        => Asset::where('is_public', true)->count(),
                'by_type'       => compact('images', 'videos', 'documents', 'audio', 'other'),
            ],
            'consents'      => [
                'total'    => array_sum($consentStats),
                'obtained' => $consentStats['obtained'] ?? 0,
                'pending'  => $consentStats['pending']  ?? 0,
                'denied'   => $consentStats['denied']   ?? 0,
            ],
            'users'         => [
                'total'      => array_sum($userStats),
                'admins'     => $userStats['admin']     ?? 0,
                'editors'    => $userStats['editor']    ?? 0,
                'viewers'    => $userStats['viewer']    ?? 0,
                'volunteers' => $userStats['volunteer'] ?? 0,
            ],
        ];
    }
}
