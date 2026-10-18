<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeAccessChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $employee,
        public string $companyName,
        public string $companyDisplayName,
        public string $action,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->action === 'blocked'
            ? 'Доступ к кабинету '.$this->companyDisplayName.' заблокирован'
            : 'Доступ к кабинету '.$this->companyDisplayName.' восстановлен';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.employee-access-changed',
            with: [
                'employee' => $this->employee,
                'companyName' => $this->companyName,
                'companyDisplayName' => $this->companyDisplayName,
                'action' => $this->action,
            ],
        );
    }
}
