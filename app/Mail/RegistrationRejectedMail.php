<?php

namespace App\Mail;

use App\Models\PendingRegistration;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class RegistrationRejectedMail extends Mailable
{
    public function __construct(
        public PendingRegistration $registration,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'About your GizeBit registration');
    }

    public function content(): Content
    {
        $reasonLabels = [
            'SPAM'              => 'The request could not be verified.',
            'INSUFFICIENT_INFO' => "We couldn't confirm the business details you provided.",
            'DUPLICATE'         => 'A registration already exists for this business.',
            'OUT_OF_REGION'     => "We're not able to onboard businesses in your region right now.",
            'OTHER'             => "We aren't able to onboard your business at this time.",
        ];

        return new Content(
            view: 'emails.registration-rejected',
            with: [
                'firstName'      => $this->registration->first_name,
                'shopName'       => $this->registration->shop_name,
                'reasonLabel'    => $reasonLabels[$this->registration->rejection_reason] ?? $reasonLabels['OTHER'],
                'note'           => $this->registration->rejection_note,
            ],
        );
    }
}
