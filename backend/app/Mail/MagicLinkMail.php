<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private string $token,
        private ?string $redirectTo = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Sign in to '.(config('app.name') ?: 'AI Flow'),
        );
    }

    public function content(): Content
    {
        $url = route('auth.magic-link.verify', ['token' => $this->token]);

        if ($this->redirectTo && $this->isSafeRedirect($this->redirectTo)) {
            $url .= '?redirect_to='.urlencode($this->redirectTo);
        }

        return new Content(
            markdown: 'mail.magic-link',
            with: [
                'url' => $url,
                'expiresIn' => '15 minutes',
            ],
        );
    }

    /**
     * Prevent open redirects by only allowing relative paths
     * or same-origin URLs.
     */
    private function isSafeRedirect(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        $appUrl = config('app.url');

        return $appUrl && str_starts_with($path, $appUrl);
    }
}
