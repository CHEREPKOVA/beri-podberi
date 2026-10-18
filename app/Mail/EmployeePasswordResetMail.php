<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeePasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $employee,
        public string $token,
        public string $companyName,
        public string $companyDisplayName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Сброс пароля в личном кабинете '.$this->companyDisplayName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.employee-password-reset',
            with: [
                'employee' => $this->employee,
                'resetUrl' => route('password.reset', [
                    'token' => $this->token,
                    'email' => $this->employee->email,
                ]),
                'companyName' => $this->companyName,
                'companyDisplayName' => $this->companyDisplayName,
            ],
        );
    }
}
