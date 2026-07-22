<?php

namespace App\Mail;

use App\Models\PendingRegistration;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class RegistrationConfirmMail extends Mailable
{
    public function __construct(
        public PendingRegistration $registration,
        public string $confirmUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirm your GizeBit registration',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-confirm',
            with: [
                'firstName'  => $this->registration->first_name,
                'shopName'   => $this->registration->shop_name,
                'confirmUrl' => $this->confirmUrl,
            ],
        );
    }
}
