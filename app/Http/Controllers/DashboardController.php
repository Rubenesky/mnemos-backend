<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Serves the main dashboard view with aggregated asset statistics and recent activity.
 *
 * @package App\Http\Controllers
 */
class DashboardController extends Controller
{
    public function index()
    {
        // General statistics
        $totalAssets    = Asset::count();
        $totalCategories = Category::count();
        $recentActivity = ActivityLog::with('user')
                                     ->latest('created_at')
                                     ->take(5)
                                     ->get();

        // Assets by file type
        $assetsByType = Asset::select('mime_type', DB::raw('count(*) as total'))
                             ->groupBy('mime_type')
                             ->get()
                             ->map(function ($item) {
                                 return [
                                     'label' => $item->mime_type,
                                     'total' => $item->total,
                                 ];
                             });

        // Assets uploaded per day (last 7 days)
        $assetsByDay = Asset::select(
                                DB::raw('DATE(created_at) as date'),
                                DB::raw('count(*) as total')
                            )
                            ->where('created_at', '>=', now()->subDays(7))
                            ->groupBy('date')
                            ->orderBy('date')
                            ->get()
                            ->map(function ($item) {
                                return [
                                    'date'  => $item->date,
                                    'total' => $item->total,
                                ];
                            });

        return view('dashboard', compact(
            'totalAssets',
            'totalCategories',
            'recentActivity',
            'assetsByType',
            'assetsByDay'
        ));
    }
}