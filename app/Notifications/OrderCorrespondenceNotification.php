<?php

namespace App\Notifications;

use App\Models\PlatformOrder;
use App\Models\PlatformOrderMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderCorrespondenceNotification extends Notification
{
    use Queueable;

    public function __construct(
        public PlatformOrder $order,
        public PlatformOrderMessage $message,
        public string $text,
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
            'type' => 'order_correspondence',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'message_id' => $this->message->id,
            'message' => $this->text,
            'url' => $notifiable instanceof User
                ? $this->order->cabinetShowUrlFor($notifiable)
                : null,
        ];
    }
}
