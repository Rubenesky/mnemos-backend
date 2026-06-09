<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;

/**
 * Returns the immutable audit trail for a single asset.
 *
 * Access is restricted to admins (any asset) and editors (own assets only).
 * The trail is built from the activity_log table, presenting each entry as a
 * structured event object: { event, user, timestamp, detail }.
 *
 * @package App\Http\Controllers\Api
 * @author  RJC
 */
class AssetAuditController extends Controller
{
    /**
     * Return the chronological audit trail for the given asset.
     *
     * @param  Asset  $asset  Route-model-bound asset instance
     * @return JsonResponse   Array of audit event objects ordered oldest-first
     */
    public function index(Asset $asset): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (!$user->isAdmin() && (!$user->isEditor() || $asset->user_id !== $user->id)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $entries = ActivityLog::where('entity_type', 'Asset')
            ->where('entity_id', $asset->id)
            ->with('user:id,name')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn(ActivityLog $log) => [
                'event'     => $log->action,
                'user'      => $log->user?->name ?? 'System',
                'timestamp' => $log->created_at->toISOString(),
                'detail'    => $this->resolveDetail($log),
            ]);

        return response()->json(['data' => $entries]);
    }

    /**
     * Derive a human-readable detail string from a log entry's metadata.
     *
     * @param  ActivityLog  $log
     * @return string|null
     */
    private function resolveDetail(ActivityLog $log): ?string
    {
        $meta = $log->metadata ?? [];

        return match ($log->action) {
            'upload'                 => $meta['filename'] ?? null,
            'delete'                 => $meta['filename'] ?? null,
            'press-kit-toggle'       => isset($meta['is_press_kit'])
                                            ? ($meta['is_press_kit'] ? 'Added to press kit' : 'Removed from press kit')
                                            : null,
            'emergency-kit-toggle'   => isset($meta['is_emergency_kit'])
                                            ? ($meta['is_emergency_kit'] ? 'Added to emergency kit' : 'Removed from emergency kit')
                                            : null,
            'emergency-kit-download' => null,
            'consent-create'         => isset($meta['person_name'], $meta['consent_type'])
                                            ? $meta['person_name'] . ' · ' . $meta['consent_type']
                                            : ($meta['person_name'] ?? null),
            'consent-update'         => isset($meta['person_name'], $meta['status'])
                                            ? $meta['person_name'] . ' → ' . $meta['status']
                                            : ($meta['person_name'] ?? null),
            default                  => null,
        };
    }
}
