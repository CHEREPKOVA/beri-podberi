<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $companyName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Компания активирована — Бери-Подбери',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.company-approved',
            with: [
                'companyName' => $this->companyName,
                'loginUrl' => route('login'),
            ],
        );
    }
}
