<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentBookedMail extends Mailable
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
            subject: 'New Appointment Booking Notification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Load relationships if not already loaded
        $this->appointment->load('doctor.user', 'otherProfessional.user', 'nurse.user', 'client.user');
        
        // Determine which professional (doctor, other_professional, or nurse)
        $professional = $this->appointment->doctor ?? $this->appointment->otherProfessional ?? $this->appointment->nurse;
        
        // Ensure professional and user are loaded
        if ($professional && !$professional->relationLoaded('user')) {
            $professional->load('user');
        }
        
        // Ensure client and user are loaded
        $client = $this->appointment->client;
        if ($client && !$client->relationLoaded('user')) {
            $client->load('user');
        }

        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $actionUrl = $frontendUrl . '/panel/doctor-appointment';
        $doctorPanelUrl = $professional ? $frontendUrl . '/panel/doctor-panel/' . $professional->id : $actionUrl;

        return new Content(
            view: 'emails.appointment-booked',
            with: [
                'appointment' => $this->appointment,
                'professional' => $professional,
                'doctor' => $professional, // Keep for backward compatibility with template
                'client' => $client,
                'actionUrl' => $actionUrl,
                'doctorPanelUrl' => $doctorPanelUrl,
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

