<?php

namespace App\Mail;

use App\Models\PlatformOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderCorrespondenceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PlatformOrder $order,
        public string $text,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Новое сообщение по заказу '.$this->order->order_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order-correspondence',
            with: [
                'order' => $this->order,
                'text' => $this->text,
            ],
        );
    }
}
