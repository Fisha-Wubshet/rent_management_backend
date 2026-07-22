<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string       $password  plaintext password (never stored, only in this one email)
     * @param  string|null  $recoveryCode  optional; only shop admin / branch manager get one
     * @param  string       $language  'en' or 'am'
     */
    public function __construct(
        public User $user,
        public string $password,
        public ?string $recoveryCode,
        public string $language = 'en',
        public bool $isReset = false,
    ) {}

    public function envelope(): Envelope
    {
        if ($this->isReset) {
            $subject = $this->language === 'am'
                ? 'የGizeBit ፓስዎርድዎ ተስተካክሏል'
                : 'Your GizeBit password has been reset';
        } else {
            $subject = $this->language === 'am'
                ? 'ወደ GizeBit እንኳን ደህና መጡ — የመግቢያ መረጃዎ'
                : 'Welcome to GizeBit — your login details';
        }
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $view = $this->language === 'am' ? 'emails.welcome-credentials-am' : 'emails.welcome-credentials-en';
        return new Content(
            view: $view,
            with: [
                'firstName'    => $this->user->first_name,
                'phone'        => $this->user->phone_number,
                'password'     => $this->password,
                'recoveryCode' => $this->recoveryCode,
                'hasEmail'     => (bool) $this->user->email,
                'loginUrl'     => config('app.url') . '/login',
                'isReset'      => $this->isReset,
            ],
        );
    }
}
