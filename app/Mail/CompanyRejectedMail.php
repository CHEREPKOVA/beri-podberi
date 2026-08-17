<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $companyName,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Регистрация компании отклонена — Бери-Подбери',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.company-rejected',
            with: [
                'companyName' => $this->companyName,
                'reason' => $this->reason,
            ],
        );
    }
}
