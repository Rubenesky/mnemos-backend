<?php
// RJC
namespace App\Mail;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies all admin users when a volunteer uploads a new asset.
 * Contains uploader name, asset name, thumbnail (if image), and a link to the review panel.
 *
 * @package App\Mail
 */
class AssetUploadNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Asset  $asset       The newly uploaded asset.
     * @param User   $uploader    The volunteer who uploaded it.
     * @param string $reviewUrl   URL to the admin assets panel.
     */
    public function __construct(
        public readonly Asset  $asset,
        public readonly User   $uploader,
        public readonly string $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New asset uploaded — ' . $this->asset->original_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.asset-upload',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
