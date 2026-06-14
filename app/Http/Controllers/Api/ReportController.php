<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImpactReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    // GET /api/reports/impact — admin only
    public function impact(Request $request, ImpactReportService $service): \Illuminate\Http\Response
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->isAdmin()) {
            abort(403, trans('messages.forbidden'));
        }

        $period = in_array($request->input('period'), ['month', 'quarter', 'year', 'all'])
            ? $request->input('period', 'all')
            : 'all';

        $locale = in_array($request->input('locale'), ['es', 'en'])
            ? $request->input('locale')
            : 'es';

        \App::setLocale($locale);

        $data = $service->gather($period);
        $pdf = Pdf::loadView('reports.impact', array_merge($data, [
            'labels' => $this->labels($locale, $period),
        ]));

        return $pdf->download('mnemos-impact-report-'.now()->format('Y-m-d').'.pdf');
    }

    private function labels(string $locale, string $period): array
    {
        $es = [
            'title' => 'Informe de Impacto',
            'subtitle' => 'Archivo Digital',
            'generated' => 'Generado',
            'period_label' => match ($period) {
                'month' => 'Este mes',
                'quarter' => 'Este trimestre',
                'year' => 'Este año',
                default => 'Todo el período',
            },
            'overview' => 'Resumen del Archivo',
            'total' => 'Total',
            'processed' => 'Procesados',
            'pending' => 'Pendientes',
            'press_kit' => 'Press Kit',
            'emergency_kit' => 'Kit de Emergencia',
            'by_type' => 'Por Tipo de Recurso',
            'type' => 'Tipo',
            'count' => 'Cantidad',
            'images' => 'Imágenes',
            'videos' => 'Vídeos',
            'documents' => 'Documentos (PDF)',
            'audio' => 'Audio',
            'other' => 'Otros',
            'consents' => 'Estado del Consentimiento RGPD',
            'status' => 'Estado',
            'obtained' => 'Obtenido',
            'consent_pending' => 'Pendiente',
            'denied' => 'Denegado',
            'team' => 'Equipo',
            'role' => 'Rol',
            'admins' => 'Administradores',
            'editors' => 'Editores',
            'viewers' => 'Visualizadores',
            'volunteers' => 'Voluntarios',
            'footer' => 'Mnemos — Memoria abierta para las organizaciones que importan',
        ];

        $en = [
            'title' => 'Impact Report',
            'subtitle' => 'Digital Archive',
            'generated' => 'Generated',
            'period_label' => match ($period) {
                'month' => 'This month',
                'quarter' => 'This quarter',
                'year' => 'This year',
                default => 'All time',
            },
            'overview' => 'Archive Overview',
            'total' => 'Total Assets',
            'processed' => 'Processed',
            'pending' => 'Pending',
            'press_kit' => 'Press Kit',
            'emergency_kit' => 'Emergency Kit',
            'by_type' => 'Assets by Type',
            'type' => 'Type',
            'count' => 'Count',
            'images' => 'Images',
            'videos' => 'Videos',
            'documents' => 'Documents (PDF)',
            'audio' => 'Audio',
            'other' => 'Other',
            'consents' => 'GDPR Consent Status',
            'status' => 'Status',
            'obtained' => 'Obtained',
            'consent_pending' => 'Pending',
            'denied' => 'Denied',
            'team' => 'Team',
            'role' => 'Role',
            'admins' => 'Admins',
            'editors' => 'Editors',
            'viewers' => 'Viewers',
            'volunteers' => 'Volunteers',
            'footer' => 'Mnemos — Open memory for organizations that matter',
        ];

        return $locale === 'es' ? $es : $en;
    }
}
