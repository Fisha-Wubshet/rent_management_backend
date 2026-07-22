<?php

namespace App\Mail;

use App\Models\PendingRegistration;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to the super admin when a new registration request comes in
 * (after email confirmation, or immediately if no email was provided).
 */
class NewRegistrationRequestMail extends Mailable
{
    public function __construct(
        public PendingRegistration $registration,
        public string $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New GizeBit registration: ' . $this->registration->shop_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-registration-request',
            with: [
                'reg'       => $this->registration,
                'reviewUrl' => $this->reviewUrl,
            ],
        );
    }
}
