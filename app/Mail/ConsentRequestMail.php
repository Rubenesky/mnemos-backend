<?php

// RJC

namespace App\Mail;

use App\Models\Consent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the photographed person when an admin generates a consent request token.
 * Contains the consent URL (valid 7 days), asset name, and organisation name.
 */
class ConsentRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Consent  $consent  The consent record.
     * @param  string  $consentUrl  The public token URL for the person to respond.
     * @param  string  $expiresAt  Human-readable expiry date (e.g. "14 June 2026").
     * @param  string  $orgName  Organisation name from APP_NAME config.
     */
    public function __construct(
        public readonly Consent $consent,
        public readonly string $consentUrl,
        public readonly string $expiresAt,
        public readonly string $orgName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Consent request — '.$this->orgName,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.consent-request',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
