<?php

namespace App\Mail;

use App\Models\CompanyInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CompanyInvitation $invitation,
        public string $plainToken,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Приглашение на регистрацию в Бери-Подбери',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.company-invitation',
            with: [
                'invitation' => $this->invitation,
                'registerUrl' => route('company-invitation.show', $this->plainToken),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
