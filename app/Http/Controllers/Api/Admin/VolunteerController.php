<?php

// RJC

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Admin endpoints for managing volunteer accounts and their access expiry.
 *
 * All routes require auth:sanctum + admin middleware.
 */
class VolunteerController extends Controller
{
    /**
     * GET /api/admin/volunteers
     *
     * Returns all users with the volunteer role, sorted by expires_at ascending
     * so the soonest-to-expire volunteers appear first.
     *
     * @return JsonResponse 200 — array of volunteer objects
     */
    public function index(): JsonResponse
    {
        $volunteers = User::withCount('assets')
            ->where('role', 'volunteer')
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END, expires_at ASC')
            ->get();

        return response()->json(
            $volunteers->map(fn (User $u) => $this->volunteerResponse($u))->values()
        );
    }

    /**
     * PATCH /api/admin/volunteers/{user}/extend
     *
     * Extends the access expiry of a volunteer account by the given number of days.
     *
     * - If expires_at is null or already in the past, the new expiry is today + days.
     * - If expires_at is in the future, the given days are added to the existing expiry.
     *
     * Returns 422 when the resolved user is not a volunteer.
     *
     * Validation rules:
     *  - days: required, integer, between 1 and 365
     *
     * @return JsonResponse 200 — updated volunteer object | 422 — not a volunteer
     */
    public function extend(Request $request, User $user): JsonResponse
    {
        if ($user->role !== 'volunteer') {
            return response()->json(['message' => 'User is not a volunteer.'], 422);
        }

        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $base = ($user->expires_at === null || $user->expires_at->isPast())
            ? Carbon::today()
            : $user->expires_at->copy();

        $user->expires_at = $base->addDays($data['days']);
        $user->save();

        $user->loadCount('assets');

        return response()->json($this->volunteerResponse($user));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Serialize a volunteer User into the standard admin response array,
     * including a computed days_remaining value.
     *
     * Color convention for the frontend:
     *  - null           → gray  (no expiry set)
     *  - > 14           → green
     *  - 1 – 14         → yellow
     *  - <= 0           → red   (expired)
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     is_active: bool,
     *     created_at: string|null,
     *     last_login_at: string|null,
     *     expires_at: string|null,
     *     days_remaining: int|null,
     *     assets_count: int
     * }
     */
    private function volunteerResponse(User $user): array
    {
        $daysRemaining = null;
        if ($user->expires_at !== null) {
            $daysRemaining = (int) Carbon::today()->diffInDays($user->expires_at, false);
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at?->toISOString(),
            'last_login_at' => $user->last_login_at?->toISOString(),
            'expires_at' => $user->expires_at?->toISOString(),
            'days_remaining' => $daysRemaining,
            'assets_count' => $user->assets_count ?? $user->assets()->count(),
        ];
    }
}
