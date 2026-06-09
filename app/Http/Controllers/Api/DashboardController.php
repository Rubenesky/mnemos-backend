<?php

namespace App\Http\Controllers\Api;

use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    /**
     * Return aggregated asset counts for the dashboard stats panel.
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'total'     => Asset::count(),
            'processed' => Asset::where('status', 'processed')->count(),
            'pending'   => Asset::where('status', 'pending')->count(),
        ]);
    }
}
