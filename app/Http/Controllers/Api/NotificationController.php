<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;

/**
 * Manages internal notifications for the authenticated user.
 */
class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     * Returns the last 20 notifications for the current user, newest first.
     * Also includes the total unread count.
     */
    public function index(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $notifications = AppNotification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn (AppNotification $n) => $this->formatNotification($n));

        $unreadCount = AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * POST /api/notifications/{notification}/read
     * Marks a single notification as read. Returns 403 if it belongs to another user.
     */
    public function markRead(AppNotification $notification): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($notification->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (is_null($notification->read_at)) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['data' => $this->formatNotification($notification->fresh())]);
    }

    /**
     * POST /api/notifications/read-all
     * Marks all unread notifications for the current user as read.
     */
    public function markAllRead(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    private function formatNotification(AppNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at->toISOString(),
        ];
    }
}
