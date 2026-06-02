<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Answers natural-language questions about the asset library using Retrieval-Augmented Generation via Gemini.
 *
 * @package App\Services
 */
class RAGService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    }

    public function query(string $userQuestion): string
    {
        // Step 1: Gather real data from the database as context
        $context = Cache::remember('rag_context', 300, fn() => $this->gatherContext());

        // Step 2: Send the context + question to Gemini
        $prompt = "You are an intelligent assistant for Mnemos, a digital asset management system.

You have access to the following REAL and CURRENT data from the platform:

{$context}

The user asks you this question: \"{$userQuestion}\"

Respond in English clearly and concisely, basing your answer ONLY on the data provided above.
If the question cannot be answered with the available data, say so clearly.
Do not invent data. Do not use information that is not in the context.
Respond in a maximum of 1-3 sentences in a conversational tone.";

        try {
            $response = Http::post("{$this->apiUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
                Log::error('RAG error', ['response' => $response->body()]);
                return 'Sorry, I cannot respond at this moment. Please try again.';
            }

            return $response->json('candidates.0.content.parts.0.text') ?? 'Could not generate a response.';

        } catch (\Exception $e) {
            Log::error('RAG exception', ['error' => $e->getMessage()]);
            return 'Error processing your question. Please try again.';
        }
    }

    private function gatherContext(): string
    {
        // Assets
        $totalAssets     = Asset::count();
        $processedAssets = Asset::where('status', 'processed')->count();
        $pendingAssets   = Asset::where('status', 'pending')->count();

        // Assets by type
        $imageAssets = Asset::where('mime_type', 'like', 'image/%')->count();
        $pdfAssets   = Asset::where('mime_type', 'like', 'application/pdf%')->count();

        // Assets this month
        $assetsThisMonth = Asset::whereMonth('created_at', now()->month)
                                ->whereYear('created_at', now()->year)
                                ->count();

        // Assets this week
        $assetsThisWeek = Asset::where('created_at', '>=', now()->startOfWeek())->count();

        // Assets today
        $assetsToday = Asset::whereDate('created_at', today())->count();

        // Most active user
        $mostActiveUser      = Asset::selectRaw('user_id, count(*) as total')
                                    ->groupBy('user_id')
                                    ->orderByDesc('total')
                                    ->with('user')
                                    ->first();
        $mostActiveUserName  = $mostActiveUser && $mostActiveUser->user ? $mostActiveUser->user->name : 'None';
        $mostActiveUserTotal = $mostActiveUser ? $mostActiveUser->total : 0;

        // Last 5 uploaded assets
        $recentAssets = Asset::with(['user', 'metadata'])
                             ->latest()
                             ->take(5)
                             ->get()
                             ->map(function($a) {
                                 $title    = $a->metadata && $a->metadata->title ? $a->metadata->title : $a->original_name;
                                 $name     = $a->user ? $a->user->name : 'Unknown';
                                 $date     = $a->created_at->format('Y-m-d');
                                 return "'{$title}' uploaded by {$name} on {$date}";
                             })
                             ->join(', ');

        // Users
        $totalUsers  = User::count();
        $adminUsers  = User::where('role', 'admin')->count();
        $editorUsers = User::where('role', 'editor')->count();
        $viewerUsers = User::where('role', 'viewer')->count();

        // Recent activity
        $recentActivity = ActivityLog::with('user')
                                     ->latest('created_at')
                                     ->take(5)
                                     ->get()
                                     ->map(function($log) {
                                         $name = $log->user ? $log->user->name : 'Unknown';
                                         $date = $log->created_at->format('Y-m-d H:i');
                                         return "{$name} performed '{$log->action}' on {$date}";
                                     })
                                     ->join(', ');

        // Total storage size
        $totalSizeKB = Asset::sum('size') / 1024;
        $totalSizeMB = round($totalSizeKB / 1024, 2);

        return "
GENERAL STATISTICS:
- Total assets in the platform: {$totalAssets}
- Assets processed by AI: {$processedAssets}
- Assets pending processing: {$pendingAssets}
- Image assets: {$imageAssets}
- PDF assets: {$pdfAssets}
- Total storage used: {$totalSizeMB} MB

ACTIVITY OVER TIME:
- Assets uploaded today: {$assetsToday}
- Assets uploaded this week: {$assetsThisWeek}
- Assets uploaded this month: {$assetsThisMonth}

USERS:
- Total users: {$totalUsers}
- Admins: {$adminUsers}
- Editors: {$editorUsers}
- Viewers: {$viewerUsers}
- Most active user: {$mostActiveUserName} with {$mostActiveUserTotal} assets uploaded

RECENTLY UPLOADED ASSETS:
{$recentAssets}

RECENT ACTIVITY:
{$recentActivity}

CURRENT DATE: " . now()->format('Y-m-d H:i') . "
";
    }
}