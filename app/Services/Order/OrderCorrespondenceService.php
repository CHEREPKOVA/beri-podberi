<?php

namespace App\Services\Order;

use App\Mail\OrderCorrespondenceMail;
use App\Models\PlatformOrder;
use App\Models\PlatformOrderMessage;
use App\Models\PlatformOrderMessageAttachment;
use App\Models\PlatformOrderMessageRead;
use App\Models\PlatformOrderStatusLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\OrderCorrespondenceNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderCorrespondenceService
{
    public const ACTION_MESSAGE = 'correspondence_message';

    public const ACTION_MESSAGE_DELETED = 'correspondence_message_deleted';

    public const ALLOWED_MIMES = 'pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,txt';

    /**
     * @return Collection<int, PlatformOrderMessage>
     */
    public function messagesFor(PlatformOrder $order, bool $withTrashed = false): Collection
    {
        $query = PlatformOrderMessage::query()
            ->where('platform_order_id', $order->id)
            ->with(['author', 'attachments'])
            ->orderBy('id');

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->get();
    }

    public function canAccess(User $user, PlatformOrder $order): bool
    {
        if ($user->hasPermission('orders.manage')) {
            return true;
        }

        $distributorId = $user->currentDistributorProfile()?->id ?? $user->distributorProfile?->id;
        if ($distributorId && (int) $order->distributor_profile_id === (int) $distributorId) {
            return true;
        }

        $manufacturerId = $user->manufacturerProfile?->id;
        if ($manufacturerId && (int) $order->manufacturer_profile_id === (int) $manufacturerId) {
            return true;
        }

        $endCompanyId = $user->endCompanyProfile?->id;
        if ($endCompanyId && (int) $order->end_company_profile_id === (int) $endCompanyId) {
            return true;
        }

        return false;
    }

    public function assertCanAccess(User $user, PlatformOrder $order): void
    {
        if (! $this->canAccess($user, $order)) {
            abort(403);
        }
    }

    /**
     * @param  list<UploadedFile>|array<int, UploadedFile>  $files
     */
    public function postUser(
        PlatformOrder $order,
        User $user,
        string $authorRole,
        string $body,
        array $files = [],
    ): PlatformOrderMessage {
        $this->assertCanAccess($user, $order);
        $this->assertAuthorRole($authorRole);

        $body = trim($body);
        $files = array_values(array_filter($files, fn ($file): bool => $file instanceof UploadedFile));

        if ($body === '' && $files === []) {
            throw ValidationException::withMessages([
                'body' => 'Введите текст сообщения или прикрепите файл.',
            ]);
        }

        if ($body === '') {
            $body = $files === [] ? '' : 'Вложение';
        }

        $message = DB::transaction(function () use ($order, $user, $authorRole, $body, $files): PlatformOrderMessage {
            $message = PlatformOrderMessage::query()->create([
                'platform_order_id' => $order->id,
                'author_user_id' => $user->id,
                'author_role' => $authorRole,
                'type' => PlatformOrderMessage::TYPE_USER,
                'body' => $body,
                'event_code' => null,
                'meta' => null,
            ]);

            foreach ($files as $file) {
                $path = $file->store('orders/'.$order->id.'/correspondence', 'public');
                PlatformOrderMessageAttachment::query()->create([
                    'platform_order_message_id' => $message->id,
                    'uploaded_by_user_id' => $user->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize() ?: 0,
                ]);
            }

            $this->log(
                $order,
                $user,
                self::ACTION_MESSAGE,
                $this->journalPreview($body, count($files)),
                [
                    'message_id' => $message->id,
                    'author_role' => $authorRole,
                    'attachments' => count($files),
                ],
            );

            $this->markRead($order, $user);

            return $message->load(['author', 'attachments']);
        });

        $this->notifyNewUserMessage($order, $message, $user);

        return $message;
    }

    public function postSystem(
        PlatformOrder $order,
        string $eventCode,
        string $body,
        array $meta = [],
    ): PlatformOrderMessage {
        return PlatformOrderMessage::query()->create([
            'platform_order_id' => $order->id,
            'author_user_id' => null,
            'author_role' => PlatformOrderMessage::ROLE_SYSTEM,
            'type' => PlatformOrderMessage::TYPE_SYSTEM,
            'body' => $body,
            'event_code' => $eventCode,
            'meta' => $meta ?: null,
        ]);
    }

    public function postFromWorkflow(
        PlatformOrder $order,
        string $action,
        string $fromStatus,
        string $toStatus,
        ?string $comment = null,
    ): ?PlatformOrderMessage {
        $statusLabel = $order->statusLabel();

        $mapped = match ($action) {
            OrderStatusWorkflowService::ACTION_CREATE => null,
            OrderStatusWorkflowService::ACTION_SEND_FOR_APPROVAL => [
                PlatformOrderMessage::EVENT_SUPPLIER_CHANGES,
                'Поставщик внёс изменения.',
            ],
            OrderStatusWorkflowService::ACTION_APPROVE_CHANGES => [
                PlatformOrderMessage::EVENT_BUYER_APPROVED,
                'Покупатель согласовал корректировки.',
            ],
            OrderStatusWorkflowService::ACTION_MARK_SHIPPED => [
                PlatformOrderMessage::EVENT_TTN_ADDED,
                'Поставщик добавил ТТН'.($order->tracking_number ? ': '.$order->tracking_number : '.'),
            ],
            OrderStatusWorkflowService::ACTION_VIEW,
            OrderStatusWorkflowService::ACTION_SERVICE_COMMENT,
            OrderStatusWorkflowService::ACTION_CONTACT_PARTIES,
            OrderStatusWorkflowService::ACTION_PAUSE,
            OrderStatusWorkflowService::ACTION_RESUME,
            OrderStatusWorkflowService::ACTION_BUYER_EDIT => null,
            default => $fromStatus !== $toStatus
                ? [
                    PlatformOrderMessage::EVENT_STATUS_CHANGED,
                    'Заказ перешёл в статус «'.$statusLabel.'».',
                ]
                : null,
        };

        if ($mapped === null) {
            return null;
        }

        [$event, $body] = $mapped;

        return $this->postSystem($order, $event, $body, [
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'comment' => $comment,
        ]);
    }

    public function postOrderCreated(PlatformOrder $order): PlatformOrderMessage
    {
        return $this->postSystem(
            $order,
            PlatformOrderMessage::EVENT_ORDER_CREATED,
            'Заказ сформирован.',
        );
    }

    public function postTtnDocument(PlatformOrder $order, string $documentName): PlatformOrderMessage
    {
        return $this->postSystem(
            $order,
            PlatformOrderMessage::EVENT_TTN_ADDED,
            'Поставщик добавил ТТН: '.$documentName,
        );
    }

    public function deleteAsAdmin(PlatformOrderMessage $message, User $user): void
    {
        if (! $user->hasPermission('orders.manage')) {
            abort(403);
        }

        if ($message->trashed()) {
            return;
        }

        $order = $message->order;
        $preview = $this->journalPreview($message->body, $message->attachments()->count());

        $message->delete();

        $this->log(
            $order,
            $user,
            self::ACTION_MESSAGE_DELETED,
            'Удалено сообщение из переписки: '.$preview,
            [
                'message_id' => $message->id,
                'moderation' => true,
            ],
        );
    }

    public function unreadCount(PlatformOrder $order, User $user): int
    {
        $lastRead = PlatformOrderMessageRead::query()
            ->where('platform_order_id', $order->id)
            ->where('user_id', $user->id)
            ->value('last_read_at');

        $query = PlatformOrderMessage::query()
            ->where('platform_order_id', $order->id)
            ->where(function ($q) use ($user): void {
                $q->whereNull('author_user_id')
                    ->orWhere('author_user_id', '!=', $user->id);
            });

        if ($lastRead) {
            $query->where('created_at', '>', $lastRead);
        }

        return $query->count();
    }

    public function markRead(PlatformOrder $order, User $user): void
    {
        PlatformOrderMessageRead::query()->updateOrCreate(
            [
                'platform_order_id' => $order->id,
                'user_id' => $user->id,
            ],
            [
                'last_read_at' => now(),
            ],
        );
    }

    public function download(PlatformOrderMessageAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->existsOnDisk(), 404);

        return Storage::disk('public')->download(
            $attachment->file_path,
            $attachment->original_name,
        );
    }

    public function preview(PlatformOrderMessageAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->existsOnDisk(), 404);
        abort_unless($attachment->isPreviewable(), 415);

        return Storage::disk('public')->response(
            $attachment->file_path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_name).'"',
            ],
        );
    }

    /**
     * @return Collection<int, User>
     */
    public function participantUsers(PlatformOrder $order, ?User $except = null): Collection
    {
        $order->loadMissing([
            'endCompanyProfile.user',
            'distributorProfile.user',
            'manufacturerProfile.user',
            'responsibleContact.user',
        ]);

        $users = collect();

        if ($order->isDistributorPurchase()) {
            $users->push($order->distributorProfile?->user);
            $users->push($order->manufacturerProfile?->user);
        } else {
            $users->push($order->endCompanyProfile?->user);
            $users->push($order->distributorProfile?->user);
        }

        $users->push($order->responsibleContact?->user);

        return $users
            ->filter()
            ->unique('id')
            ->reject(fn (User $user): bool => $except !== null && (int) $user->id === (int) $except->id)
            ->values();
    }

    public function notifyNewUserMessage(PlatformOrder $order, PlatformOrderMessage $message, User $author): void
    {
        $preview = $this->journalPreview($message->body, $message->attachments->count());
        $text = 'Новое сообщение по заказу №'.$order->order_number.': '.$preview;

        foreach ($this->participantUsers($order, $author) as $user) {
            try {
                $user->notify(new OrderCorrespondenceNotification($order, $message, $text));
            } catch (\Throwable) {
                // Не блокируем отправку сообщения из-за сбоя уведомлений.
            }

            if ($this->emailNotificationsEnabled() && filled($user->email)) {
                try {
                    Mail::to($user->email)->send(new OrderCorrespondenceMail($order, $text));
                } catch (\Throwable) {
                    // Email опционален.
                }
            }
        }
    }

    private function assertAuthorRole(string $authorRole): void
    {
        if (! in_array($authorRole, [
            PlatformOrderMessage::ROLE_BUYER,
            PlatformOrderMessage::ROLE_SUPPLIER,
            PlatformOrderMessage::ROLE_ADMIN,
        ], true)) {
            abort(403);
        }
    }

    private function journalPreview(string $body, int $attachments): string
    {
        $text = trim($body);
        if (mb_strlen($text) > 180) {
            $text = mb_substr($text, 0, 180).'…';
        }

        if ($attachments > 0) {
            $text .= ($text !== '' ? ' ' : '').'['.$attachments.' влож.]';
        }

        return $text !== '' ? $text : 'Сообщение';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function log(
        PlatformOrder $order,
        User $user,
        string $action,
        string $comment,
        array $meta = [],
    ): void {
        PlatformOrderStatusLog::query()->create([
            'platform_order_id' => $order->id,
            'from_status' => $order->status,
            'to_status' => $order->status,
            'action' => $action,
            'comment' => $comment,
            'performed_by_user_id' => $user->id,
            'meta' => $meta,
        ]);
    }

    private function emailNotificationsEnabled(): bool
    {
        return (bool) SystemSetting::getActiveParsed('notifications.email_enabled', true);
    }
}
