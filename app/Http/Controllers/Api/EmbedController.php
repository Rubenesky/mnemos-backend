<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\OrganizationSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves the embeddable gallery widget for public collections.
 *
 * Two endpoints:
 *  - gallery()   — renders a standalone Blade page suitable as an iframe src
 *  - embedCode() — returns a ready-to-paste HTML snippet and preview URL
 *
 * Both endpoints are unauthenticated (public).
 *
 * @author  RJC
 */
class EmbedController extends Controller
{
    /** Allowed values for each query parameter. */
    private const THEMES = ['light', 'dark'];

    private const COLS = [2, 3, 4];

    private const LIMITS = [6, 12, 24];

    /**
     * @param  OrganizationSettingsService  $settings  Provides org_name for the embedded gallery title
     */
    public function __construct(
        private readonly OrganizationSettingsService $settings,
    ) {}

    /**
     * Render the standalone embeddable gallery page for a collection.
     *
     * Query parameters:
     *   theme  — light|dark  (default: light)
     *   cols   — 2|3|4       (default: 3)
     *   limit  — 6|12|24     (default: 12)
     *
     * @param  string  $slug  Public collection slug
     * @return Response Rendered Blade HTML page
     */
    public function gallery(string $slug, Request $request): Response
    {
        $theme = $this->resolveTheme($request);
        $cols = $this->resolveCols($request);
        $limit = $this->resolveLimit($request);

        $collection = Category::where('slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

        $assets = $collection->assets()
            ->where('is_public', true)
            ->where('status', 'processed')
            ->with('metadata')
            ->latest()
            ->take($limit)
            ->get();

        $orgName = $this->settings->get('org_name', 'Mnemos');

        return response()->view('embed.gallery', compact('collection', 'assets', 'theme', 'cols', 'limit', 'orgName'));
    }

    /**
     * Return a ready-to-paste iframe snippet and the preview URL for a collection.
     *
     * Query parameters are forwarded into the snippet src URL (theme, cols, limit).
     *
     * @param  string  $slug  Public collection slug
     * @return JsonResponse { snippet: string, preview_url: string }
     */
    public function embedCode(string $slug, Request $request): JsonResponse
    {
        $collection = Category::where('slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

        $theme = $this->resolveTheme($request);
        $cols = $this->resolveCols($request);
        $limit = $this->resolveLimit($request);

        $base = rtrim(config('app.url'), '/');
        $previewUrl = "{$base}/api/public/embed/{$slug}?"
                    .http_build_query(['theme' => $theme, 'cols' => $cols, 'limit' => $limit]);

        $snippet = '<iframe'
                 .' src="'.$previewUrl.'"'
                 .' width="100%"'
                 .' height="600"'
                 .' frameborder="0"'
                 .' loading="lazy"'
                 .' title="'.e($collection->name).'"'
                 .'></iframe>';

        return response()->json([
            'snippet' => $snippet,
            'preview_url' => $previewUrl,
        ]);
    }

    /**
     * Resolve and validate the theme query parameter.
     *
     * @return string light|dark
     */
    private function resolveTheme(Request $request): string
    {
        $value = $request->query('theme', 'light');

        return in_array($value, self::THEMES, true) ? $value : 'light';
    }

    /**
     * Resolve and validate the cols query parameter.
     *
     * @return int 2|3|4
     */
    private function resolveCols(Request $request): int
    {
        $value = (int) $request->query('cols', 3);

        return in_array($value, self::COLS, true) ? $value : 3;
    }

    /**
     * Resolve and validate the limit query parameter.
     *
     * @return int 6|12|24
     */
    private function resolveLimit(Request $request): int
    {
        $value = (int) $request->query('limit', 12);

        return in_array($value, self::LIMITS, true) ? $value : 12;
    }
}
