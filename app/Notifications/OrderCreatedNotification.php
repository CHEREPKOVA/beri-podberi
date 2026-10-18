<?php

namespace App\Notifications;

use App\Models\PlatformOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public PlatformOrder $order,
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
            'type' => 'order_created',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'message' => $this->message,
            'url' => $notifiable instanceof User
                ? $this->order->cabinetShowUrlFor($notifiable, false)
                : null,
        ];
    }
}
