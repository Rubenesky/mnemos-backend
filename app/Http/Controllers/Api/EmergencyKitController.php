<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\EmergencyKitService;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmergencyKitController extends Controller
{
    use LogsActivity;

    // PATCH /api/assets/{asset}/emergency-kit — admin/editor (own asset), toggle
    public function toggle(Asset $asset, Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (!$user->isAdmin() && (!$user->isEditor() || $asset->user_id !== $user->id)) {
            return response()->json(['success' => false, 'message' => trans('messages.forbidden')], 403);
        }

        $validated = $request->validate([
            'is_emergency_kit' => 'required|boolean',
        ]);

        $asset->update($validated);
        $this->logActivity('emergency-kit-toggle', $asset, ['is_emergency_kit' => $asset->is_emergency_kit]);

        return response()->json([
            'success'          => true,
            'is_emergency_kit' => $asset->is_emergency_kit,
        ]);
    }

    // GET /api/emergency-kit/download — admin only, returns ZIP
    public function download(EmergencyKitService $service): StreamedResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (!$user->isAdmin()) {
            abort(403, trans('messages.forbidden'));
        }

        // Log one audit entry per asset in the kit so each asset's audit trail records this download
        Asset::where('is_emergency_kit', true)->get(['id', 'user_id'])->each(
            fn($kitAsset) => $this->logActivity('emergency-kit-download', $kitAsset)
        );

        $tmpPath  = $service->build();
        $filename = 'mnemos-emergency-kit-' . now()->format('Y-m-d') . '.zip';

        return response()->streamDownload(function () use ($tmpPath) {
            $stream = fopen($tmpPath, 'rb');
            if ($stream === false) {
                throw new \RuntimeException('Could not open emergency kit file for streaming');
            }
            fpassthru($stream);
            fclose($stream);
            @unlink($tmpPath);
        }, $filename, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
