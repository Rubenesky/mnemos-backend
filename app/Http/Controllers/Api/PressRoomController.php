<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\OrganizationSettingsService;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PressRoomController extends Controller
{
    use LogsActivity;

    /**
     * @param  OrganizationSettingsService  $settings  Used to expose org branding in the press room feed
     */
    public function __construct(
        private readonly OrganizationSettingsService $settings,
    ) {}

    // GET /api/public/press-room — no auth required
    public function index(): JsonResponse
    {
        $assets = Asset::with(['metadata'])
            ->where('is_press_kit', true)
            ->where('is_public', true)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'org_name' => $this->settings->get('org_name', 'Mnemos'),
            'org_description' => $this->settings->get('org_description', ''),
            'data' => $assets->map(fn ($asset) => [
                'id' => $asset->id,
                'title' => $asset->metadata?->title ?? $asset->original_name,
                'description' => $asset->metadata?->description,
                'press_kit_description' => $asset->press_kit_description,
                'url' => $asset->cloudinary_url,
                'mime_type' => $asset->mime_type,
                'size' => $asset->size,
                'alt_text' => $asset->alt_text,
                'tags' => $asset->metadata?->tags ?? [],
                'created_at' => $asset->created_at?->toISOString(),
            ]),
        ]);
    }

    // PATCH /api/assets/{asset}/press-kit — auth required, admin/editor only
    public function toggle(Asset $asset, Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->isAdmin() && (! $user->isEditor() || $asset->user_id !== $user->id)) {
            return response()->json(['success' => false, 'message' => trans('messages.forbidden')], 403);
        }

        $validated = $request->validate([
            'is_press_kit' => 'required|boolean',
            'press_kit_description' => 'nullable|string|max:1000',
        ]);

        $asset->update($validated);
        $this->logActivity('press-kit-toggle', $asset, ['is_press_kit' => $asset->is_press_kit]);

        return response()->json([
            'success' => true,
            'is_press_kit' => $asset->is_press_kit,
        ]);
    }
}
