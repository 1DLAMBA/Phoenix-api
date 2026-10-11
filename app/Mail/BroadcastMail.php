<?php

namespace App\Mail;

use App\Models\User;
use App\Support\NewsletterFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BroadcastMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $subjectLine;
    public $bodyMessage;
    /** @var array{headline?:?string,banner_url?:?string,cta_label?:?string,cta_url?:?string} */
    public array $options;

    public function __construct(User $user, string $subject, string $message, array $options = [])
    {
        $this->user = $user;
        $this->subjectLine = $subject;
        $this->bodyMessage = $message;
        $this->options = $options;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.broadcast',
            with: $this->viewData(),
        );
    }

    public function viewData(): array
    {
        return [
            'user' => $this->user,
            'subjectLine' => $this->subjectLine,
            'bodyHtml' => NewsletterFormatter::toHtml($this->bodyMessage),
            'headline' => $this->options['headline'] ?? null,
            'bannerUrl' => $this->options['banner_url'] ?? null,
            'ctaLabel' => $this->options['cta_label'] ?? null,
            'ctaUrl' => $this->options['cta_url'] ?? null,
        ];
    }
}
