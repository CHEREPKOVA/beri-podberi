<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\StoreOrderMessageRequest;
use App\Models\PlatformOrder;
use App\Models\PlatformOrderMessage;
use App\Models\PlatformOrderMessageAttachment;
use App\Services\Order\OrderCorrespondenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ManagesOrderCorrespondence
{
    protected function correspondence(): OrderCorrespondenceService
    {
        return app(OrderCorrespondenceService::class);
    }

    /**
     * @return array{
     *     order_messages: Collection<int, PlatformOrderMessage>,
     *     correspondence_unread: int,
     *     correspondence_author_role: string,
     *     correspondence_store_route: string,
     *     correspondence_download_route: string,
     *     correspondence_preview_route: ?string,
     *     correspondence_destroy_route: ?string,
     *     correspondence_can_post: bool,
     *     correspondence_can_moderate: bool
     * }
     */
    protected function correspondenceUiContext(
        Request $request,
        PlatformOrder $order,
        string $authorRole,
        string $storeRouteName,
        string $downloadRouteName,
        ?string $previewRouteName,
        ?string $destroyRouteName = null,
    ): array {
        $user = $request->user();
        $withTrashed = $authorRole === PlatformOrderMessage::ROLE_ADMIN;
        $unread = $user ? $this->correspondence()->unreadCount($order, $user) : 0;

        if ($user) {
            $this->correspondence()->markRead($order, $user);
        }

        return [
            'order_messages' => $this->correspondence()->messagesFor($order, $withTrashed),
            'correspondence_unread' => $unread,
            'correspondence_author_role' => $authorRole,
            'correspondence_store_route' => route($storeRouteName, $order),
            'correspondence_download_route' => $downloadRouteName,
            'correspondence_preview_route' => $previewRouteName,
            'correspondence_destroy_route' => $destroyRouteName,
            'correspondence_can_post' => true,
            'correspondence_can_moderate' => $destroyRouteName !== null,
        ];
    }

    protected function storeCorrespondenceMessage(
        StoreOrderMessageRequest $request,
        PlatformOrder $order,
        string $authorRole,
        string $showRoute,
    ): RedirectResponse {
        try {
            $this->correspondence()->postUser(
                $order,
                $request->user(),
                $authorRole,
                (string) $request->input('body', ''),
                $request->file('attachments', []) ?: [],
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route($showRoute, $order)
            ->withFragment('order-correspondence')
            ->with('success', 'Сообщение отправлено.');
    }

    protected function downloadCorrespondenceAttachment(
        PlatformOrder $order,
        PlatformOrderMessageAttachment $attachment,
    ): StreamedResponse {
        $this->assertAttachmentBelongsToOrder($order, $attachment);

        return $this->correspondence()->download($attachment);
    }

    protected function previewCorrespondenceAttachment(
        PlatformOrder $order,
        PlatformOrderMessageAttachment $attachment,
    ): StreamedResponse {
        $this->assertAttachmentBelongsToOrder($order, $attachment);

        return $this->correspondence()->preview($attachment);
    }

    protected function destroyCorrespondenceMessage(
        Request $request,
        PlatformOrder $order,
        PlatformOrderMessage $message,
        string $showRoute,
    ): RedirectResponse {
        abort_unless((int) $message->platform_order_id === (int) $order->id, 404);

        $this->correspondence()->deleteAsAdmin($message, $request->user());

        return redirect()
            ->route($showRoute, $order)
            ->withFragment('order-correspondence')
            ->with('success', 'Сообщение удалено.');
    }

    protected function assertAttachmentBelongsToOrder(
        PlatformOrder $order,
        PlatformOrderMessageAttachment $attachment,
    ): void {
        $message = PlatformOrderMessage::withTrashed()->find($attachment->platform_order_message_id);
        abort_unless($message && (int) $message->platform_order_id === (int) $order->id, 404);

        if ($message->trashed() && ! request()->user()?->hasPermission('orders.manage')) {
            abort(404);
        }
    }
}
