<?php

namespace App\Http\Controllers\Api;

use App\Mail\ConsentResponseMail;
use App\Models\User;
use App\Services\ConsentTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;

class PublicConsentController extends Controller
{
    /** Returns consent form data for a valid, unexpired, unanswered token. */
    public function show(string $token, ConsentTokenService $service): JsonResponse
    {
        $consent = $service->findByToken($token);

        if (! $consent) {
            return response()->json(['message' => 'Token no válido o expirado.'], 404);
        }

        return response()->json([
            'data' => [
                'person_name' => $consent->person_name,
                'consent_type' => $consent->consent_type,
                'asset_title' => $consent->asset->metadata?->title ?? $consent->asset->original_name,
                'consent_date' => $consent->consent_date->toDateString(),
                'expires_at' => $consent->token_expires_at->toISOString(),
            ],
        ]);
    }

    /** Records the person's consent decision (obtained or denied). */
    public function respond(string $token, Request $request, ConsentTokenService $service): JsonResponse
    {
        $consent = $service->findByToken($token);

        if (! $consent) {
            return response()->json(['message' => 'Token no válido o expirado.'], 404);
        }

        $request->validate([
            'status' => 'required|in:obtained,denied',
            'notes' => 'nullable|string|max:500',
        ]);

        $service->respond($consent, $request->status, $request->notes);

        // Notify all admins of the consent decision via email
        $gdprPanelUrl = rtrim(config('app.frontend_url', config('app.url')), '/').'/consents';
        $consent->loadMissing('asset.metadata');
        User::where('role', 'admin')->each(function (User $admin) use ($consent, $request, $gdprPanelUrl) {
            Mail::to($admin->email)
                ->send(new ConsentResponseMail($consent, $request->status, $gdprPanelUrl));
        });

        $message = $request->status === 'obtained'
            ? 'Consentimiento registrado. Gracias.'
            : 'Decisión registrada. Su respuesta ha sido guardada.';

        return response()->json(['message' => $message]);
    }
}
