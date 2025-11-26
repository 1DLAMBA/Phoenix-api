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
        return new Content(
            view: 'emails.medical-record-created',
            with: [
                'medicalRecord' => $this->medicalRecord,
                'doctor' => $this->medicalRecord->doctor->load('user'),
                'client' => $this->medicalRecord->client->load('user'),
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



