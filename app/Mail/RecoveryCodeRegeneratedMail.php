<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecoveryCodeRegeneratedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $code)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your new GizeBit recovery code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recovery-code-regenerated',
            with: [
                'firstName' => $this->user->first_name,
                'when'      => now()->format('M j, Y \a\t g:i A'),
                'code'      => $this->code,
            ],
        );
    }
}
