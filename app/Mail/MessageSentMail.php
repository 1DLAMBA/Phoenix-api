<?php

namespace App\Mail;

use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MessageSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public $message;
    public $sender;
    public $receiver;

    /**
     * Create a new message instance.
     */
    public function __construct(Message $message, User $sender, User $receiver)
    {
        $this->message = $message;
        $this->sender = $sender;
        $this->receiver = $receiver;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Message from ' . $this->sender->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.message-sent',
            with: [
                'message' => $this->message,
                'sender' => $this->sender,
                'receiver' => $this->receiver,
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
        return [];
    }
}

