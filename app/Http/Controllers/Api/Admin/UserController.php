<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Admin User Management API controller.
 *
 * Handles listing, creating, updating, role changes, activation/deactivation,
 * and deletion of user accounts. All routes are protected by the `admin`
 * middleware, so every method can assume the authenticated user is an admin.
 */
class UserController extends Controller
{
    /**
     * Return a list of all users sorted by creation date descending.
     *
     * GET /api/admin/users
     *
     * @return JsonResponse 200 — array of user objects
     */
    public function index(): JsonResponse
    {
        $users = User::withCount('assets')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(
            $users->map(fn (User $u) => $this->userResponse($u))->values()
        );
    }

    /**
     * Create a new user account.
     *
     * POST /api/admin/users
     *
     * Validation rules:
     *  - name:       required, string, max 255
     *  - email:      required, valid email, unique in users table
     *  - password:   required, min 8 characters
     *  - role:       required, one of: admin, editor, volunteer, viewer
     *  - expires_at: required when role is volunteer; nullable date after today
     *
     * @return JsonResponse 201 — created user object
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'editor', 'volunteer', 'viewer'])],
            'expires_at' => ['required_if:role,volunteer', 'nullable', 'date', 'after:today'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => true,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        $user->loadCount('assets');

        return response()->json($this->userResponse($user), 201);
    }

    /**
     * Update editable fields of an existing user.
     *
     * PATCH /api/admin/users/{user}
     *
     * Validation rules (all optional, validated only when present):
     *  - name:       string, max 255
     *  - email:      valid email, unique in users table (ignoring this user's own row)
     *  - role:       one of: admin, editor, volunteer, viewer
     *  - expires_at: required when role changes to volunteer; nullable date
     *
     * @return JsonResponse 200 — updated user object
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if ($user->is_protected && $request->has('role') && $request->input('role') !== 'admin') {
            return response()->json(['message' => 'Esta cuenta está protegida y su rol no puede cambiarse.'], 403);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', Rule::in(['admin', 'editor', 'volunteer', 'viewer'])],
            'expires_at' => ['required_if:role,volunteer', 'nullable', 'date'],
        ]);

        $user->fill($data)->save();
        $user->loadCount('assets');

        return response()->json($this->userResponse($user));
    }

    /**
     * Change the role of a user.
     *
     * PATCH /api/admin/users/{user}/role
     *
     * An admin cannot change their own role (returns 403).
     *
     * Validation rules:
     *  - role:       required, one of: admin, editor, volunteer, viewer
     *  - expires_at: required when role is volunteer; nullable date after today
     *
     * @return JsonResponse 200 — updated user object | 403 — self-change guard
     */
    public function changeRole(Request $request, User $user): JsonResponse
    {
        if (auth()->id() === $user->id) {
            return response()->json(['message' => 'You cannot change your own role.'], 403);
        }

        if ($user->is_protected && ($request->input('role') !== 'admin')) {
            return response()->json(['message' => 'Esta cuenta está protegida y su rol no puede cambiarse.'], 403);
        }

        $data = $request->validate([
            'role' => ['required', Rule::in(['admin', 'editor', 'volunteer', 'viewer'])],
            'expires_at' => ['required_if:role,volunteer', 'nullable', 'date', 'after:today'],
        ]);

        $user->role = $data['role'];

        if ($data['role'] === 'volunteer') {
            $user->expires_at = $data['expires_at'] ?? null;
        }

        $user->save();
        $user->loadCount('assets');

        return response()->json($this->userResponse($user));
    }

    /**
     * Deactivate a user account and revoke all their API tokens.
     *
     * PATCH /api/admin/users/{user}/deactivate
     *
     * An admin cannot deactivate their own account (returns 403).
     *
     * @return JsonResponse 200 — updated user object | 403 — self-deactivation guard
     */
    public function deactivate(User $user): JsonResponse
    {
        if (auth()->id() === $user->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 403);
        }

        if ($user->role === 'admin') {
            $otherActiveAdmins = User::where('role', 'admin')
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->exists();

            if (! $otherActiveAdmins) {
                return response()->json(
                    ['message' => 'Cannot deactivate the last active admin.'],
                    409
                );
            }
        }

        $user->is_active = false;
        $user->save();

        // Revoke all Sanctum personal access tokens so the user is immediately signed out.
        $user->tokens()->delete();

        $user->loadCount('assets');

        return response()->json($this->userResponse($user));
    }

    /**
     * Re-activate a previously deactivated user account.
     *
     * PATCH /api/admin/users/{user}/activate
     *
     * @return JsonResponse 200 — updated user object
     */
    public function activate(User $user): JsonResponse
    {
        $user->is_active = true;
        $user->save();

        $user->loadCount('assets');

        return response()->json($this->userResponse($user));
    }

    /**
     * Permanently delete a user account.
     *
     * DELETE /api/admin/users/{user}
     *
     * Deletion is blocked when the user still owns assets (returns 409).
     * The caller must reassign or delete those assets first.
     *
     * @return JsonResponse 204 — no content | 409 — user has assets
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->is_protected) {
            return response()->json(['message' => 'Esta cuenta está protegida y no puede eliminarse.'], 403);
        }

        if ($user->assets()->count() > 0) {
            return response()->json(
                ['message' => 'Cannot delete user with associated assets. Reassign or delete their assets first.'],
                409
            );
        }

        if ($user->role === 'admin') {
            $otherActiveAdmins = User::where('role', 'admin')
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->exists();

            if (! $otherActiveAdmins) {
                return response()->json(
                    ['message' => 'Cannot delete the last active admin.'],
                    409
                );
            }
        }

        $user->delete();

        return response()->json(null, 204);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Serialize a User model into the standard admin response array.
     *
     * The `assets_count` attribute is populated either by a prior `withCount`
     * / `loadCount` call or falls back to a live query when absent.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     role: string,
     *     is_active: bool,
     *     created_at: string|null,
     *     last_login_at: string|null,
     *     expires_at: string|null,
     *     assets_count: int
     * }
     */
    private function userResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'is_protected' => (bool) $user->is_protected,
            'created_at' => $user->created_at?->toISOString(),
            'last_login_at' => $user->last_login_at?->toISOString(),
            'expires_at' => $user->expires_at?->toISOString(),
            'assets_count' => $user->assets_count ?? $user->assets()->count(),
        ];
    }
}
