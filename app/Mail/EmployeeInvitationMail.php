<?php

namespace App\Mail;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public UserInvitation $invitation,
        public string $plainToken,
        public string $companyName,
        public string $companyDisplayName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Приглашение в личный кабинет компании '.$this->companyDisplayName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.employee-invitation',
            with: [
                'invitation' => $this->invitation,
                'registerUrl' => route('employee-invitation.show', $this->plainToken),
                'expiresAt' => $this->invitation->expires_at,
                'companyName' => $this->companyName,
                'companyDisplayName' => $this->companyDisplayName,
            ],
        );
    }
}
