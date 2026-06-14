<?php

namespace App\Services;

use App\Models\Consent;
use Illuminate\Support\Str;

class ConsentTokenService
{
    public function __construct(
        private ?NotificationService $notifications = null
    ) {
        $this->notifications ??= app(NotificationService::class);
    }

    /** Generates a 64-char token, stores it on the consent, and returns it. */
    public function generateToken(Consent $consent): string
    {
        $token = Str::random(64);

        $consent->update([
            'token' => $token,
            'token_expires_at' => now()->addDays(7),
            'responded_at' => null,
        ]);

        return $token;
    }

    /**
     * Returns the Consent (with asset.metadata) if the token is valid,
     * not expired, and not yet answered. Returns null otherwise.
     */
    public function findByToken(string $token): ?Consent
    {
        return Consent::with('asset.metadata')
            ->where('token', $token)
            ->where('token_expires_at', '>', now())
            ->whereNull('responded_at')
            ->first();
    }

    /**
     * Records the person's response and marks the consent as answered.
     *
     * @throws \LogicException if the consent has already been answered
     */
    public function respond(Consent $consent, string $status, ?string $notes = null): void
    {
        if ($consent->responded_at !== null) {
            throw new \LogicException("Consent #{$consent->id} has already been responded to.");
        }

        $consent->update([
            'status' => $status,
            'responded_at' => now(),
            'notes' => $notes ?? $consent->notes,
        ]);

        $this->notifications->notifyAdmins('consent_responded', [
            'consent_id' => $consent->id,
            'person_name' => $consent->person_name,
            'status' => $status,
            'asset_id' => $consent->asset_id,
        ]);
    }
}
