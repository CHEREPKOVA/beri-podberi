<?php

namespace App\Notifications;

use App\Models\PlatformOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public PlatformOrder $order,
        public string $fromStatus,
        public string $toStatus,
        public string $message,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_status_changed',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'message' => $this->message,
            'tracking_number' => $this->order->tracking_number,
        ];
    }
}
