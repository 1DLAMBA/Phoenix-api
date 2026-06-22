<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Phoenix - You\'re All Set!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return new Content(
            view: 'emails.welcome',
            with: [
                'user' => $this->user,
                'appUrl' => $frontendUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $logoPath = public_path('images/phoenix-logo.png');

        if (file_exists($logoPath)) {
            return [
                Attachment::fromPath($logoPath)
                    ->as('phoenix-logo.png')
                    ->withMime('image/png'),
            ];
        }

        return [];
    }
}
