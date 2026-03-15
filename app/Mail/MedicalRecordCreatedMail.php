<?php

namespace App\Mail;

use App\Models\MedicalRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MedicalRecordCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $medicalRecord;

    /**
     * Create a new message instance.
     */
    public function __construct(MedicalRecord $medicalRecord)
    {
        $this->medicalRecord = $medicalRecord;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Medical Record Created',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Load relationships - handle both doctor and other_professional
        $this->medicalRecord->load('doctor.user', 'otherProfessional.user', 'client.user');
        
        // Determine which professional (doctor or other_professional)
        $professional = $this->medicalRecord->doctor ?? $this->medicalRecord->otherProfessional;

        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $actionUrl = $frontendUrl . '/panel/client-panel';

        return new Content(
            view: 'emails.medical-record-created',
            with: [
                'medicalRecord' => $this->medicalRecord,
                'professional' => $professional,
                'doctor' => $this->medicalRecord->doctor, // Keep for backward compatibility
                'client' => $this->medicalRecord->client,
                'actionUrl' => $actionUrl,
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











