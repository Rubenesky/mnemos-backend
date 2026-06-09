<?php

// RJC

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\CloudinaryService;
use App\Services\OrganizationSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin endpoints for reading and updating organization-level settings.
 *
 * All routes require auth:sanctum + admin middleware.
 *
 * @package App\Http\Controllers\Api\Admin
 */
class OrganizationSettingsController extends Controller
{
    /**
     * @param OrganizationSettingsService $settings   Cached settings service
     * @param CloudinaryService           $cloudinary Upload service for logo
     */
    public function __construct(
        private readonly OrganizationSettingsService $settings,
        private readonly CloudinaryService $cloudinary,
    ) {}

    /**
     * GET /api/admin/settings
     *
     * Returns all organization settings as a flat key/value JSON object.
     *
     * @return JsonResponse  200 { org_name: string, org_locale: string, ... }
     */
    public function index(): JsonResponse
    {
        return response()->json($this->settings->getAll());
    }

    /**
     * PATCH /api/admin/settings
     *
     * Updates one or more settings from a validated key/value payload.
     * Returns the full updated settings object.
     *
     * @param  Request $request
     * @return JsonResponse  200 { org_name: string, ... }
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'org_name'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'org_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'org_website'     => ['sometimes', 'nullable', 'url', 'max:255'],
            'org_email'       => ['sometimes', 'nullable', 'email', 'max:255'],
            'org_locale'      => ['sometimes', 'in:en,es'],
        ]);

        foreach ($data as $key => $value) {
            $this->settings->set($key, (string) $value);
        }

        return response()->json($this->settings->getAll());
    }

    /**
     * POST /api/admin/settings/logo
     *
     * Uploads a logo image to Cloudinary and saves the resulting URL
     * in the org_logo_url setting.
     *
     * @param  Request $request  Must contain multipart field "logo" (image, max 2 MB)
     * @return JsonResponse  200 { org_logo_url: string }
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'max:2048'],
        ]);

        $result = $this->cloudinary->upload($request->file('logo'));
        $url    = $result['url'];

        $this->settings->set('org_logo_url', $url);

        return response()->json(['org_logo_url' => $url]);
    }
}
