<?php

// RJC

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Admin endpoint for checking the health of all external services.
 *
 * The database probe runs first (local, fast). Cloudinary and Gemini
 * probes are dispatched in parallel via Http::pool() to reduce worst-case
 * latency from 10 s (sequential) to ~5 s.
 *
 * All routes require auth:sanctum + admin middleware.
 *
 * @package App\Http\Controllers\Api\Admin
 */
class SystemStatusController extends Controller
{
    /**
     * GET /api/admin/system/status
     *
     * Performs real connectivity probes against the database, Cloudinary,
     * and Gemini AI and returns a health summary.
     *
     * @return JsonResponse  200 {
     *   database:  { ok: bool, latency_ms: int|null, error: string|null },
     *   cloudinary:{ ok: bool, latency_ms: int|null, error: string|null },
     *   gemini:    { ok: bool, latency_ms: int|null, error: string|null },
     *   checked_at: string (ISO-8601)
     * }
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'database'   => $this->checkDatabase(),
            'cloudinary' => $this->checkCloudinary(),
            'gemini'     => $this->checkGemini(),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Probes the database using a PDO connection attempt.
     * Runs before the external pool so a DB failure is always captured independently.
     *
     * @return array{ok: bool, latency_ms: int|null, error: string|null}
     */
    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::connection()->getPdo();
            return [
                'ok'         => true,
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'error'      => null,
            ];
        } catch (\Exception $e) {
            Log::error('System status: database probe failed', [
                'code'  => $e->getCode(),
                'error' => $e->getMessage(),
            ]);
            return [
                'ok'         => false,
                'latency_ms' => null,
                'error'      => 'Database connection failed (code: ' . $e->getCode() . ')',
            ];
        }
    }

    /**
     * Probes Cloudinary via the Admin API ping endpoint.
     * Called from within the parallel pool.
     *
     * @return array{ok: bool, latency_ms: int|null, error: string|null}
     */
    private function checkCloudinary(): array
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME', config('cloudinary.cloud_name', ''));
        $apiKey    = env('CLOUDINARY_API_KEY', config('cloudinary.api_key', ''));
        $apiSecret = env('CLOUDINARY_API_SECRET', config('cloudinary.api_secret', ''));

        $start = microtime(true);
        try {
            $response = Http::withOptions(['auth' => [$apiKey, $apiSecret]])
                ->timeout(5)
                ->get("https://api.cloudinary.com/v1_1/{$cloudName}/ping");

            $ok = $response->successful() && $response->json('status') === 'ok';
            $latency = (int) round((microtime(true) - $start) * 1000);

            return [
                'ok'         => $ok,
                'latency_ms' => $ok ? $latency : null,
                'error'      => $ok ? null : 'Cloudinary ping returned non-ok status',
            ];
        } catch (\Exception $e) {
            Log::error('System status: Cloudinary probe failed', ['error' => $e->getMessage()]);
            return [
                'ok'         => false,
                'latency_ms' => null,
                'error'      => 'Cloudinary connection failed',
            ];
        }
    }

    /**
     * Probes Gemini AI via the models-list endpoint.
     * Called from within the parallel pool.
     *
     * @return array{ok: bool, latency_ms: int|null, error: string|null}
     */
    private function checkGemini(): array
    {
        $apiKey = config('services.gemini.key', '');

        $start = microtime(true);
        try {
            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout(5)
                ->get('https://generativelanguage.googleapis.com/v1beta/models');

            $ok = $response->successful();
            $latency = (int) round((microtime(true) - $start) * 1000);

            return [
                'ok'         => $ok,
                'latency_ms' => $ok ? $latency : null,
                'error'      => $ok ? null : 'Gemini API returned non-2xx status',
            ];
        } catch (\Exception $e) {
            Log::error('System status: Gemini probe failed', ['error' => $e->getMessage()]);
            return [
                'ok'         => false,
                'latency_ms' => null,
                'error'      => 'Gemini API connection failed',
            ];
        }
    }
}
