<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * REST API controller for Sanctum token-based authentication (login, logout, admin promotion).
 *
 * @package App\Http\Controllers\Api
 */
class AuthApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        // Borramos tokens anteriores y creamos uno nuevo
        $user->tokens()->delete();
        $token = $user->createToken('api-token', ['*'], now()->addDays(7))->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ]
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    public function makeAdmin(): JsonResponse
    {
        $adminEmail = config('app.admin_email');

        if (!$adminEmail) {
            return response()->json(['message' => 'Not configured'], 404);
        }

        $user = \App\Models\User::where('email', $adminEmail)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->update(['role' => 'admin']);

        return response()->json([
            'success' => true,
            'message' => "User {$user->email} is now admin",
        ]);
    }
}