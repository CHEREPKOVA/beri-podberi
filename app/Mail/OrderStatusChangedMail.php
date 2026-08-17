<?php

namespace App\Mail;

use App\Models\PlatformOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PlatformOrder $order,
        public string $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Заказ '.$this->order->order_number.' — '.$this->order->statusLabel(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order-status-changed',
            with: [
                'order' => $this->order,
                'message' => $this->message,
                'statusLabel' => $this->order->statusLabel(),
            ],
        );
    }
}
