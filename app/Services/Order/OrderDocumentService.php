<?php

namespace App\Services\Order;

use App\Models\PlatformOrder;
use App\Models\PlatformOrderDocument;
use App\Models\PlatformOrderStatusLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderDocumentService
{
    public const ACTION_UPLOAD_DOCUMENT = 'upload_document';

    public const ACTION_REPLACE_DOCUMENT = 'replace_document';

    public const ACTION_DELETE_DOCUMENT = 'delete_document';

    /**
     * @param  array{name: string, type: string, notes?: ?string}  $data
     */
    public function store(
        PlatformOrder $order,
        User $user,
        string $uploaderRole,
        UploadedFile $file,
        array $data,
    ): PlatformOrderDocument {
        $this->assertCanMutate($order, $uploaderRole);
        $this->assertTypeAllowed($uploaderRole, $data['type']);

        return DB::transaction(function () use ($order, $user, $uploaderRole, $file, $data): PlatformOrderDocument {
            $path = $file->store('orders/'.$order->id.'/documents', 'public');

            $document = PlatformOrderDocument::query()->create([
                'platform_order_id' => $order->id,
                'uploaded_by_user_id' => $user->id,
                'uploader_role' => $uploaderRole,
                'name' => trim($data['name']),
                'type' => $data['type'],
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
            ]);

            $this->syncClaimFlag($order);
            $this->log(
                $order,
                $user,
                self::ACTION_UPLOAD_DOCUMENT,
                'Загружен документ: '.$document->name.' ('.$document->typeLabel().')',
                [
                    'document_id' => $document->id,
                    'type' => $document->type,
                    'uploader_role' => $uploaderRole,
                ],
            );

            return $document;
        });
    }

    /**
     * @param  array{name?: string, type?: string, notes?: ?string}  $data
     */
    public function replace(
        PlatformOrderDocument $document,
        User $user,
        string $uploaderRole,
        ?UploadedFile $file = null,
        array $data = [],
    ): PlatformOrderDocument {
        $order = $document->order;
        $this->assertOwnsDocument($document, $uploaderRole);
        $this->assertCanMutate($order, $uploaderRole);

        $type = $data['type'] ?? $document->type;
        $this->assertTypeAllowed($uploaderRole, $type);

        return DB::transaction(function () use ($document, $order, $user, $uploaderRole, $file, $data, $type): PlatformOrderDocument {
            if ($file !== null) {
                Storage::disk('public')->delete($document->file_path);
                $document->file_path = $file->store('orders/'.$order->id.'/documents', 'public');
                $document->original_name = $file->getClientOriginalName();
                $document->mime_type = $file->getMimeType();
                $document->file_size = $file->getSize();
            }

            if (array_key_exists('name', $data) && filled($data['name'])) {
                $document->name = trim((string) $data['name']);
            }
            $document->type = $type;
            if (array_key_exists('notes', $data)) {
                $document->notes = filled($data['notes']) ? trim((string) $data['notes']) : null;
            }
            $document->uploaded_by_user_id = $user->id;
            $document->save();

            $this->syncClaimFlag($order);
            $this->log(
                $order,
                $user,
                self::ACTION_REPLACE_DOCUMENT,
                'Обновлён документ: '.$document->name.' ('.$document->typeLabel().')',
                [
                    'document_id' => $document->id,
                    'type' => $document->type,
                    'uploader_role' => $uploaderRole,
                ],
            );

            return $document->fresh(['uploadedBy']);
        });
    }

    public function delete(PlatformOrderDocument $document, User $user, string $uploaderRole): void
    {
        $order = $document->order;
        $this->assertOwnsDocument($document, $uploaderRole);
        $this->assertCanMutate($order, $uploaderRole);

        DB::transaction(function () use ($document, $order, $user, $uploaderRole): void {
            $name = $document->name;
            $typeLabel = $document->typeLabel();
            $meta = [
                'document_id' => $document->id,
                'type' => $document->type,
                'uploader_role' => $uploaderRole,
            ];

            Storage::disk('public')->delete($document->file_path);
            $document->delete();
            $this->syncClaimFlag($order);

            $this->log(
                $order,
                $user,
                self::ACTION_DELETE_DOCUMENT,
                'Удалён документ: '.$name.' ('.$typeLabel.')',
                $meta,
            );
        });
    }

    public function download(PlatformOrderDocument $document): StreamedResponse
    {
        abort_unless(Storage::disk('public')->exists($document->file_path), 404);

        return Storage::disk('public')->download(
            $document->file_path,
            $document->original_name ?: $document->name,
        );
    }

    public function preview(PlatformOrderDocument $document): StreamedResponse
    {
        abort_unless(Storage::disk('public')->exists($document->file_path), 404);
        abort_unless($document->isPreviewable(), 415);

        return Storage::disk('public')->response(
            $document->file_path,
            $document->original_name ?: $document->name,
            [
                'Content-Type' => $document->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes($document->original_name ?: $document->name).'"',
            ],
        );
    }

    public function canMutate(PlatformOrder $order, string $uploaderRole): bool
    {
        if ($order->isPaused()) {
            return false;
        }

        // Поставщик обновляет документы до статуса «Завершён» (не после отклонения).
        if ($uploaderRole === PlatformOrderDocument::ROLE_SUPPLIER) {
            return ! in_array($order->status, [
                PlatformOrder::STATUS_COMPLETED,
                PlatformOrder::STATUS_REJECTED,
            ], true);
        }

        // Покупатель — пока заказ не завершён (в т.ч. претензии / акты после отгрузки или отклонения).
        return $order->status !== PlatformOrder::STATUS_COMPLETED;
    }

    /**
     * @return array<string, string>
     */
    public function availableTypesFor(string $uploaderRole): array
    {
        return $uploaderRole === PlatformOrderDocument::ROLE_SUPPLIER
            ? PlatformOrderDocument::supplierTypeLabels()
            : PlatformOrderDocument::buyerTypeLabels();
    }

    private function assertCanMutate(PlatformOrder $order, string $uploaderRole): void
    {
        if ($this->canMutate($order, $uploaderRole)) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => 'Документы нельзя изменять после завершения заказа или во время приостановки.',
        ]);
    }

    private function assertOwnsDocument(PlatformOrderDocument $document, string $uploaderRole): void
    {
        if ($document->uploader_role === $uploaderRole) {
            return;
        }

        throw ValidationException::withMessages([
            'document' => 'Можно изменять только свои документы.',
        ]);
    }

    private function assertTypeAllowed(string $uploaderRole, string $type): void
    {
        $allowed = $uploaderRole === PlatformOrderDocument::ROLE_SUPPLIER
            ? PlatformOrderDocument::supplierTypes()
            : PlatformOrderDocument::buyerTypes();

        if (in_array($type, $allowed, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'type' => 'Недопустимый тип документа для вашей роли.',
        ]);
    }

    private function syncClaimFlag(PlatformOrder $order): void
    {
        $hasClaim = PlatformOrderDocument::query()
            ->where('platform_order_id', $order->id)
            ->whereIn('type', [
                PlatformOrderDocument::TYPE_CLAIM,
                PlatformOrderDocument::TYPE_RETURN_REQUEST,
            ])
            ->exists();

        if ((bool) $order->has_active_claim !== $hasClaim) {
            $order->forceFill(['has_active_claim' => $hasClaim])->save();
        }
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
}
