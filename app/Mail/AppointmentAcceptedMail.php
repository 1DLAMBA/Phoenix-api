<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentAcceptedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $appointment;

    /**
     * Create a new message instance.
     */
    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Appointment Accepted - Confirmation',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Load relationships if not already loaded
        $this->appointment->load('doctor.user', 'otherProfessional.user', 'client.user');
        
        // Determine which professional (doctor or other_professional)
        $professional = $this->appointment->doctor ?? $this->appointment->otherProfessional;
        
        // Ensure professional and user are loaded
        if ($professional && !$professional->relationLoaded('user')) {
            $professional->load('user');
        }
        
        // Ensure client and user are loaded
        $client = $this->appointment->client;
        if ($client && !$client->relationLoaded('user')) {
            $client->load('user');
        }
        
        return new Content(
            view: 'emails.appointment-accepted',
            with: [
                'appointment' => $this->appointment,
                'professional' => $professional,
                'doctor' => $professional, // Keep for backward compatibility with template
                'client' => $client,
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







