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
 * Notifies admin users when a person responds to a consent request.
 * Contains person name, decision (obtained/denied), asset name, and GDPR panel link.
 *
 * @package App\Mail
 */
class ConsentResponseMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Consent $consent      The consent record after the response is recorded.
     * @param string  $decision     'obtained' or 'denied'.
     * @param string  $gdprPanelUrl URL to the admin GDPR consent panel.
     */
    public function __construct(
        public readonly Consent $consent,
        public readonly string  $decision,
        public readonly string  $gdprPanelUrl,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->decision === 'obtained' ? 'Consent obtained' : 'Consent denied';
        return new Envelope(
            subject: $label . ' — ' . ($this->consent->asset?->metadata?->title ?? $this->consent->asset?->original_name ?? 'asset'),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.consent-response',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
