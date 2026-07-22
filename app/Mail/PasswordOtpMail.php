<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $otp)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'GizeBit password reset code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-otp',
            with: [
                'firstName' => $this->user->first_name,
                'otp'       => $this->otp,
            ],
        );
    }
}
