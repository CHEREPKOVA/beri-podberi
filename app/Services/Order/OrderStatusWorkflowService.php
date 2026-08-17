<?php

namespace App\Services\Order;

use App\Mail\OrderStatusChangedMail;
use App\Models\DistributorProduct;
use App\Models\ManufacturerProfile;
use App\Models\PlatformOrder;
use App\Models\PlatformOrderItem;
use App\Models\PlatformOrderStatusLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class OrderStatusWorkflowService
{
    public const ACTOR_BUYER = 'buyer';

    public const ACTOR_SUPPLIER = 'supplier';

    public const ACTOR_ADMIN = 'admin';

    public const ACTION_OPEN = 'open';

    public const ACTION_CONFIRM = 'confirm';

    public const ACTION_REJECT = 'reject';

    public const ACTION_SEND_FOR_APPROVAL = 'send_for_approval';

    public const ACTION_APPROVE_CHANGES = 'approve_changes';

    public const ACTION_REJECT_CHANGES = 'reject_changes';

    public const ACTION_CANCEL = 'cancel';

    public const ACTION_MARK_READY = 'mark_ready';

    public const ACTION_MARK_IN_WORK = 'mark_in_work';

    public const ACTION_MARK_SHIPPED = 'mark_shipped';

    public const ACTION_COMPLETE = 'complete';

    public const ACTION_CONFIRM_RECEIPT = 'confirm_receipt';

    public const ACTION_ADMIN_STATUS_CHANGE = 'admin_status_change';

    public const ACTION_SERVICE_COMMENT = 'service_comment';

    public const ACTION_PAUSE = 'pause';

    public const ACTION_RESUME = 'resume';

    public const ACTION_CONTACT_PARTIES = 'contact_parties';

    public const ACTION_VIEW = 'view';

    public const ACTION_CREATE = 'create';

    public const ACTION_SUBMIT = 'submit_to_supplier';

    public const ACTION_BUYER_EDIT = 'buyer_edit';

    /**
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return [
            self::ACTION_CREATE => 'Создание заказа',
            self::ACTION_SUBMIT => 'Отправка поставщику',
            self::ACTION_BUYER_EDIT => 'Редактирование покупателем',
            self::ACTION_VIEW => 'Просмотр поставщиком',
            self::ACTION_OPEN => 'Ознакомление',
            self::ACTION_CONFIRM => 'Подтверждение',
            self::ACTION_REJECT => 'Отклонение',
            self::ACTION_SEND_FOR_APPROVAL => 'Изменения поставщика',
            self::ACTION_APPROVE_CHANGES => 'Согласование покупателя',
            self::ACTION_REJECT_CHANGES => 'Отклонение изменений покупателем',
            self::ACTION_CANCEL => 'Отмена покупателем',
            self::ACTION_MARK_IN_WORK => 'Передан в работу',
            'assign_responsible' => 'Назначение ответственного',
            'create_claim' => 'Регистрация претензии',
            self::ACTION_MARK_READY => 'Готов к отгрузке',
            self::ACTION_MARK_SHIPPED => 'Отгрузка',
            self::ACTION_COMPLETE => 'Завершение',
            self::ACTION_CONFIRM_RECEIPT => 'Подтверждение получения',
            self::ACTION_ADMIN_STATUS_CHANGE => 'Смена статуса',
            self::ACTION_SERVICE_COMMENT => 'Служебный комментарий',
            self::ACTION_CONTACT_PARTIES => 'Контакт со сторонами',
            self::ACTION_PAUSE => 'Приостановка',
            self::ACTION_RESUME => 'Снятие приостановки',
            OrderDocumentService::ACTION_UPLOAD_DOCUMENT => 'Загрузка документа',
            OrderDocumentService::ACTION_REPLACE_DOCUMENT => 'Обновление документа',
            OrderDocumentService::ACTION_DELETE_DOCUMENT => 'Удаление документа',
        ];
    }

    /**
     * @return list<string>
     */
    public function availableActions(PlatformOrder $order, string $actor): array
    {
        if ($actor === self::ACTOR_ADMIN) {
            $actions = [
                self::ACTION_SERVICE_COMMENT,
                self::ACTION_CONTACT_PARTIES,
                self::ACTION_ADMIN_STATUS_CHANGE,
            ];

            if ($order->isPaused()) {
                $actions[] = self::ACTION_RESUME;
            } elseif (! $order->isTerminal()) {
                $actions[] = self::ACTION_PAUSE;
            }

            return $actions;
        }

        if ($order->isPaused()) {
            return [];
        }

        $status = $order->status;

        if ($actor === self::ACTOR_SUPPLIER) {
            return match ($status) {
                PlatformOrder::STATUS_NEW => [self::ACTION_OPEN, self::ACTION_CONFIRM, self::ACTION_REJECT, self::ACTION_SEND_FOR_APPROVAL],
                PlatformOrder::STATUS_AWAITING_CONFIRMATION => [self::ACTION_CONFIRM, self::ACTION_REJECT, self::ACTION_SEND_FOR_APPROVAL],
                PlatformOrder::STATUS_CONFIRMED => [self::ACTION_MARK_IN_WORK, self::ACTION_MARK_READY, self::ACTION_SEND_FOR_APPROVAL],
                PlatformOrder::STATUS_IN_WORK => [self::ACTION_MARK_READY],
                PlatformOrder::STATUS_READY_TO_SHIP => [self::ACTION_MARK_SHIPPED],
                default => [],
            };
        }

        if ($actor === self::ACTOR_BUYER) {
            $actions = match ($status) {
                PlatformOrder::STATUS_NEW, PlatformOrder::STATUS_AWAITING_CONFIRMATION => [self::ACTION_CANCEL],
                PlatformOrder::STATUS_NEEDS_APPROVAL => [self::ACTION_APPROVE_CHANGES, self::ACTION_REJECT_CHANGES],
                PlatformOrder::STATUS_SHIPPED => [self::ACTION_CONFIRM_RECEIPT],
                default => [],
            };

            if (
                $status === PlatformOrder::STATUS_NEW
                && $order->isDistributorPurchase()
            ) {
                array_unshift($actions, self::ACTION_SUBMIT);
            }

            return $actions;
        }

        return [];
    }

    public function can(PlatformOrder $order, string $actor, string $action): bool
    {
        return in_array($action, $this->availableActions($order, $actor), true);
    }

    /**
     * Статусы, в которых от пользователя ожидается реакция
     * (не опциональная отмена, а обязательное действие).
     *
     * @return list<string>
     */
    public function statusesRequiringAttention(string $actor): array
    {
        return match ($actor) {
            self::ACTOR_SUPPLIER => [
                PlatformOrder::STATUS_NEW,
                PlatformOrder::STATUS_AWAITING_CONFIRMATION,
            ],
            self::ACTOR_BUYER => [
                PlatformOrder::STATUS_NEEDS_APPROVAL,
                PlatformOrder::STATUS_SHIPPED,
            ],
            default => [],
        };
    }

    public function countRequiringAttentionForDistributor(int $distributorProfileId): int
    {
        return PlatformOrder::query()
            ->where('distributor_profile_id', $distributorProfileId)
            ->where(function (Builder $channel): void {
                $channel->where('order_channel', PlatformOrder::CHANNEL_END_COMPANY_DISTRIBUTOR)
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('order_channel')
                            ->whereNotNull('end_company_profile_id');
                    });
            })
            ->whereIn('status', $this->statusesRequiringAttention(self::ACTOR_SUPPLIER))
            ->count();
    }

    public function countRequiringAttentionForDistributorPurchases(int $distributorProfileId): int
    {
        return PlatformOrder::query()
            ->where('distributor_profile_id', $distributorProfileId)
            ->where('order_channel', PlatformOrder::CHANNEL_DISTRIBUTOR_MANUFACTURER)
            ->whereIn('status', [
                PlatformOrder::STATUS_NEW,
                PlatformOrder::STATUS_NEEDS_APPROVAL,
                PlatformOrder::STATUS_SHIPPED,
            ])
            ->count();
    }

    public function countRequiringAttentionForManufacturer(int $manufacturerProfileId): int
    {
        $profile = new ManufacturerProfile;
        $profile->id = $manufacturerProfileId;

        return app(ManufacturerOrderListService::class)
            ->baseQuery($profile)
            ->whereIn('status', $this->statusesRequiringAttention(self::ACTOR_SUPPLIER))
            ->count();
    }

    public function countRequiringAttentionForBuyer(int $endCompanyProfileId): int
    {
        return PlatformOrder::query()
            ->where('end_company_profile_id', $endCompanyProfileId)
            ->whereIn('status', $this->statusesRequiringAttention(self::ACTOR_BUYER))
            ->count();
    }

    public function open(PlatformOrder $order, User $user): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_SUPPLIER, self::ACTION_OPEN);

        return $this->transition(
            $order,
            PlatformOrder::STATUS_AWAITING_CONFIRMATION,
            $user,
            self::ACTION_OPEN,
            'Поставщик ознакомился с заказом',
        );
    }

    public function confirm(PlatformOrder $order, User $user): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_SUPPLIER, self::ACTION_CONFIRM);

        return $this->transition(
            $order,
            PlatformOrder::STATUS_CONFIRMED,
            $user,
            self::ACTION_CONFIRM,
            'Поставщик подтвердил заказ',
        );
    }

    public function reject(PlatformOrder $order, User $user, string $reason): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_SUPPLIER, self::ACTION_REJECT);
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Укажите причину отклонения заказа.',
            ]);
        }

        $order->rejection_reason = $reason;

        return $this->transition(
            $order,
            PlatformOrder::STATUS_REJECTED,
            $user,
            self::ACTION_REJECT,
            $reason,
        );
    }

    /**
     * @param  list<array{
     *     id?: ?int,
     *     distributor_product_id?: ?int,
     *     quantity?: int,
     *     unit_price?: float|int|string,
     *     distributor_warehouse_id?: ?int,
     *     reason?: ?string,
     *     delete?: bool|int|string
     * }>  $items
     */
    public function sendForApproval(PlatformOrder $order, User $user, array $items, ?string $comment = null): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_SUPPLIER, self::ACTION_SEND_FOR_APPROVAL);

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Укажите изменения по позициям заказа.',
            ]);
        }

        $previousTotal = round((float) $order->total_amount, 2);
        $changes = [];
        $newTotal = 0.0;

        DB::transaction(function () use ($order, $items, &$changes, &$newTotal): void {
            $remainingIds = [];

            foreach ($items as $row) {
                $delete = filter_var($row['delete'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $itemId = isset($row['id']) ? (int) $row['id'] : 0;

                if ($itemId > 0) {
                    $item = $order->items()->whereKey($itemId)->first();
                    if ($item === null) {
                        throw ValidationException::withMessages([
                            'items' => 'Позиция заказа не найдена.',
                        ]);
                    }

                    if ($delete) {
                        $changes[] = [
                            'id' => $item->id,
                            'name' => $item->name,
                            'sku' => $item->sku,
                            'old_quantity' => (int) $item->quantity,
                            'new_quantity' => 0,
                            'old_unit_price' => round((float) $item->unit_price, 2),
                            'new_unit_price' => round((float) $item->unit_price, 2),
                            'old_line_total' => round((float) $item->line_total, 2),
                            'new_line_total' => 0.0,
                            'reason' => filled($row['reason'] ?? null) ? trim((string) $row['reason']) : 'Позиция удалена',
                            'action' => 'deleted',
                        ];
                        $item->delete();

                        continue;
                    }

                    $oldQty = (int) $item->quantity;
                    $oldPrice = round((float) $item->unit_price, 2);
                    $oldWarehouse = $item->distributor_warehouse_id;
                    $qty = (int) ($row['quantity'] ?? $oldQty);
                    $price = round((float) ($row['unit_price'] ?? $oldPrice), 2);
                    $warehouseId = array_key_exists('distributor_warehouse_id', $row)
                        ? ($row['distributor_warehouse_id'] !== null && $row['distributor_warehouse_id'] !== ''
                            ? (int) $row['distributor_warehouse_id']
                            : null)
                        : $oldWarehouse;

                    if ($qty < 1) {
                        throw ValidationException::withMessages([
                            'items' => 'Количество должно быть не меньше 1.',
                        ]);
                    }
                    if ($price < 0) {
                        throw ValidationException::withMessages([
                            'items' => 'Цена не может быть отрицательной.',
                        ]);
                    }

                    $lineTotal = round($qty * $price, 2);
                    $item->update([
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'line_total' => $lineTotal,
                        'distributor_warehouse_id' => $warehouseId,
                    ]);
                    $newTotal += $lineTotal;
                    $remainingIds[] = $item->id;

                    if ($oldQty !== $qty || abs($oldPrice - $price) > 0.009 || (int) $oldWarehouse !== (int) $warehouseId) {
                        $changes[] = [
                            'id' => $item->id,
                            'name' => $item->name,
                            'sku' => $item->sku,
                            'old_quantity' => $oldQty,
                            'new_quantity' => $qty,
                            'old_unit_price' => $oldPrice,
                            'new_unit_price' => $price,
                            'old_line_total' => round($oldQty * $oldPrice, 2),
                            'new_line_total' => $lineTotal,
                            'reason' => filled($row['reason'] ?? null) ? trim((string) $row['reason']) : null,
                            'action' => 'updated',
                        ];
                    }

                    continue;
                }

                // Новая позиция
                $offerId = (int) ($row['distributor_product_id'] ?? 0);
                if ($offerId < 1) {
                    throw ValidationException::withMessages([
                        'items' => 'Для новой позиции укажите товар.',
                    ]);
                }

                $offer = DistributorProduct::query()
                    ->whereKey($offerId)
                    ->where('distributor_profile_id', $order->distributor_profile_id)
                    ->first();

                if ($offer === null) {
                    throw ValidationException::withMessages([
                        'items' => 'Товар не найден в каталоге поставщика.',
                    ]);
                }

                $qty = (int) ($row['quantity'] ?? 1);
                $price = round((float) ($row['unit_price'] ?? $offer->retail_price ?? 0), 2);
                if ($qty < 1 || $price < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Некорректные количество или цена новой позиции.',
                    ]);
                }

                $lineTotal = round($qty * $price, 2);
                $created = PlatformOrderItem::query()->create([
                    'platform_order_id' => $order->id,
                    'distributor_product_id' => $offer->id,
                    'product_id' => $offer->source_product_id,
                    'distributor_warehouse_id' => filled($row['distributor_warehouse_id'] ?? null)
                        ? (int) $row['distributor_warehouse_id']
                        : null,
                    'name' => $offer->name,
                    'sku' => $offer->manufacturer_sku ?: $offer->internal_sku,
                    'manufacturer_name' => $offer->manufacturerName(),
                    'quantity' => $qty,
                    'pack_quantity' => $offer->pack_quantity,
                    'min_order_quantity' => $offer->min_order_quantity,
                    'unit_price' => $price,
                    'list_unit_price' => null,
                    'line_total' => $lineTotal,
                ]);

                $newTotal += $lineTotal;
                $remainingIds[] = $created->id;
                $changes[] = [
                    'id' => $created->id,
                    'name' => $created->name,
                    'sku' => $created->sku,
                    'old_quantity' => 0,
                    'new_quantity' => $qty,
                    'old_unit_price' => 0,
                    'new_unit_price' => $price,
                    'old_line_total' => 0,
                    'new_line_total' => $lineTotal,
                    'reason' => filled($row['reason'] ?? null) ? trim((string) $row['reason']) : 'Добавлена позиция',
                    'action' => 'added',
                ];
            }

            if ($order->items()->count() < 1) {
                throw ValidationException::withMessages([
                    'items' => 'В заказе должна остаться хотя бы одна позиция.',
                ]);
            }

            // Пересчёт итого на случай, если в форму не попали все строки
            $newTotal = round((float) $order->items()->sum('line_total'), 2);
            $order->total_amount = $newTotal;
            $order->save();
        });

        $order->refresh();
        $supplierComment = filled($comment) ? trim((string) $comment) : null;

        return $this->transition(
            $order,
            PlatformOrder::STATUS_NEEDS_APPROVAL,
            $user,
            self::ACTION_SEND_FOR_APPROVAL,
            $supplierComment ?: 'Поставщик отправил изменения на согласование',
            [
                'changes' => $changes,
                'previous_total' => $previousTotal,
                'new_total' => round((float) $order->total_amount, 2),
                'supplier_comment' => $supplierComment,
            ],
        );
    }

    /**
     * Последнее предложение изменений от поставщика (для экрана согласования).
     *
     * @return array{
     *     comment: ?string,
     *     supplier_comment: ?string,
     *     changes: list<array<string, mixed>>,
     *     previous_total: ?float,
     *     new_total: ?float,
     *     created_at: ?\Illuminate\Support\Carbon
     * }|null
     */
    public function latestApprovalProposal(PlatformOrder $order): ?array
    {
        $log = $order->relationLoaded('statusLogs')
            ? $order->statusLogs->first(
                fn ($entry): bool => $entry->action === self::ACTION_SEND_FOR_APPROVAL
            )
            : $order->statusLogs()
                ->where('action', self::ACTION_SEND_FOR_APPROVAL)
                ->orderByDesc('id')
                ->first();

        if ($log === null) {
            return null;
        }

        $meta = is_array($log->meta) ? $log->meta : [];
        $supplierComment = $meta['supplier_comment'] ?? null;
        if (! filled($supplierComment) && filled($log->comment)
            && $log->comment !== 'Поставщик отправил изменения на согласование') {
            $supplierComment = $log->comment;
        }

        return [
            'comment' => $log->comment,
            'supplier_comment' => $supplierComment ? (string) $supplierComment : null,
            'changes' => array_values($meta['changes'] ?? []),
            'previous_total' => isset($meta['previous_total']) ? (float) $meta['previous_total'] : null,
            'new_total' => isset($meta['new_total']) ? (float) $meta['new_total'] : null,
            'created_at' => $log->created_at,
        ];
    }

    public function approveChanges(PlatformOrder $order, User $user): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_BUYER, self::ACTION_APPROVE_CHANGES);

        return $this->transition(
            $order,
            PlatformOrder::STATUS_CONFIRMED,
            $user,
            self::ACTION_APPROVE_CHANGES,
            'Покупатель согласовал изменения',
        );
    }

    public function rejectChanges(PlatformOrder $order, User $user, ?string $reason = null): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_BUYER, self::ACTION_REJECT_CHANGES);
        $reason = trim((string) $reason);
        $comment = $reason !== ''
            ? $reason
            : 'Покупатель отклонил предложенные изменения';

        // Не завершаем заказ: возвращаем поставщику на доработку / повторное согласование.
        return $this->transition(
            $order,
            PlatformOrder::STATUS_AWAITING_CONFIRMATION,
            $user,
            self::ACTION_REJECT_CHANGES,
            $comment,
            [
                'type' => 'buyer_rejected_changes',
                'buyer_rejection_reason' => $reason !== '' ? $reason : null,
            ],
        );
    }

    /**
     * Последнее отклонение изменений покупателем (для экрана поставщика).
     *
     * @return array{reason: ?string, comment: ?string, created_at: ?\Illuminate\Support\Carbon}|null
     */
    public function latestBuyerChangesRejection(PlatformOrder $order): ?array
    {
        if ($order->status !== PlatformOrder::STATUS_AWAITING_CONFIRMATION
            && $order->status !== PlatformOrder::STATUS_NEW) {
            return null;
        }

        $log = $order->relationLoaded('statusLogs')
            ? $order->statusLogs->first(
                fn ($entry): bool => $entry->action === self::ACTION_REJECT_CHANGES
            )
            : $order->statusLogs()
                ->where('action', self::ACTION_REJECT_CHANGES)
                ->orderByDesc('id')
                ->first();

        if ($log === null) {
            return null;
        }

        // Показываем только если это последнее значимое действие по согласованию.
        $laterSupplierAction = $order->relationLoaded('statusLogs')
            ? $order->statusLogs->first(
                fn ($entry): bool => $entry->id > $log->id
                    && in_array($entry->action, [
                        self::ACTION_SEND_FOR_APPROVAL,
                        self::ACTION_CONFIRM,
                        self::ACTION_REJECT,
                    ], true)
            )
            : $order->statusLogs()
                ->where('id', '>', $log->id)
                ->whereIn('action', [
                    self::ACTION_SEND_FOR_APPROVAL,
                    self::ACTION_CONFIRM,
                    self::ACTION_REJECT,
                ])
                ->exists();

        if ($laterSupplierAction) {
            return null;
        }

        $meta = is_array($log->meta) ? $log->meta : [];

        return [
            'reason' => $meta['buyer_rejection_reason'] ?? null,
            'comment' => $log->comment,
            'created_at' => $log->created_at,
        ];
    }

    public function cancelByBuyer(PlatformOrder $order, User $user, ?string $reason = null): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_BUYER, self::ACTION_CANCEL);
        $order->rejection_reason = trim((string) $reason) ?: 'Заказ отменён покупателем';

        return $this->transition(
            $order,
            PlatformOrder::STATUS_REJECTED,
            $user,
            self::ACTION_CANCEL,
            $order->rejection_reason,
        );
    }

    public function submitToSupplier(PlatformOrder $order, User $user): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_BUYER, self::ACTION_SUBMIT);

        return $this->transition(
            $order,
            PlatformOrder::STATUS_AWAITING_CONFIRMATION,
            $user,
            self::ACTION_SUBMIT,
            'Заказ отправлен производителю на подтверждение',
        );
    }

    public function markReady(PlatformOrder $order, User $user): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_SUPPLIER, self::ACTION_MARK_READY);

        return $this->transition(
            $order,
            PlatformOrder::STATUS_READY_TO_SHIP,
            $user,
            self::ACTION_MARK_READY,
            'Заказ готов к отгрузке',
        );
    }

    public function markInWork(PlatformOrder $order, User $user): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_SUPPLIER, self::ACTION_MARK_IN_WORK);

        return $this->transition(
            $order,
            PlatformOrder::STATUS_IN_WORK,
            $user,
            self::ACTION_MARK_IN_WORK,
            'Заказ передан на комплектование',
        );
    }

    /**
     * @param  array{tracking_number: string, shipped_at?: ?string, shipping_from_warehouse?: ?string, transport_company_id?: ?int}  $data
     */
    public function markShipped(PlatformOrder $order, User $user, array $data): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_SUPPLIER, self::ACTION_MARK_SHIPPED);

        $tracking = trim((string) ($data['tracking_number'] ?? ''));
        if ($tracking === '') {
            throw ValidationException::withMessages([
                'tracking_number' => 'Укажите номер ТТН / трек-номер.',
            ]);
        }

        $order->tracking_number = $tracking;
        $order->shipped_at = filled($data['shipped_at'] ?? null)
            ? $data['shipped_at']
            : now();
        $order->shipping_from_warehouse = filled($data['shipping_from_warehouse'] ?? null)
            ? trim((string) $data['shipping_from_warehouse'])
            : null;

        if (isset($data['transport_company_id']) && $data['transport_company_id']) {
            $order->transport_company_id = (int) $data['transport_company_id'];
        }

        return $this->transition(
            $order,
            PlatformOrder::STATUS_SHIPPED,
            $user,
            self::ACTION_MARK_SHIPPED,
            'Заказ отгружен. ТТН: '.$tracking,
            [
                'tracking_number' => $tracking,
            ],
        );
    }

    public function complete(PlatformOrder $order, User $user, string $actor, ?string $notes = null): PlatformOrder
    {
        // По ТЗ завершает заказ только покупатель (подтверждение получения).
        $this->assertCan($order, $actor, self::ACTION_CONFIRM_RECEIPT);

        $order->received_at = $order->received_at ?? now();
        if (filled($notes)) {
            $order->completion_notes = trim($notes);
        }

        return $this->transition(
            $order,
            PlatformOrder::STATUS_COMPLETED,
            $user,
            self::ACTION_CONFIRM_RECEIPT,
            'Покупатель подтвердил получение',
            filled($notes) ? ['completion_notes' => trim($notes)] : [],
        );
    }

    /**
     * Индекс текущего шага в прогресс-линии (0-based), либо null для боковых статусов.
     */
    public function progressIndex(PlatformOrder $order): ?int
    {
        $progress = PlatformOrder::progressStatuses();

        // Согласование изменений покупателем = ожидание подтверждения (шаг 2), не «Подтверждён».
        if ($order->status === PlatformOrder::STATUS_NEEDS_APPROVAL) {
            $idx = array_search(PlatformOrder::STATUS_AWAITING_CONFIRMATION, $progress, true);

            return $idx === false ? null : (int) $idx;
        }

        if ($order->status === PlatformOrder::STATUS_REJECTED) {
            return null;
        }

        $idx = array_search($order->status, $progress, true);

        return $idx === false ? null : (int) $idx;
    }

    /**
     * Принудительная смена статуса администратором (вне обычного workflow).
     */
    public function adminChangeStatus(PlatformOrder $order, User $user, string $toStatus, string $comment): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_ADMIN, self::ACTION_ADMIN_STATUS_CHANGE);

        $allowed = array_keys(PlatformOrder::fallbackStatusLabels());
        if (! in_array($toStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'Неизвестный статус заказа.',
            ]);
        }

        $comment = trim($comment);
        if ($comment === '') {
            throw ValidationException::withMessages([
                'comment' => 'Укажите служебный комментарий к смене статуса.',
            ]);
        }

        if ($toStatus === $order->status) {
            throw ValidationException::withMessages([
                'status' => 'Выберите статус, отличный от текущего.',
            ]);
        }

        if ($toStatus === PlatformOrder::STATUS_REJECTED && ! filled($order->rejection_reason)) {
            $order->rejection_reason = $comment;
        }

        // Смена статуса снимает приостановку — исполнение продолжается в новом статусе.
        if ($order->isPaused()) {
            $order->paused_at = null;
            $order->paused_by_user_id = null;
            $order->pause_reason = null;
        }

        return $this->transition(
            $order,
            $toStatus,
            $user,
            self::ACTION_ADMIN_STATUS_CHANGE,
            $comment,
            ['actor' => self::ACTOR_ADMIN, 'admin_intervention' => true],
        );
    }

    /**
     * Служебный комментарий без смены статуса (виден в полной истории).
     */
    public function addServiceComment(
        PlatformOrder $order,
        User $user,
        string $comment,
        bool $notifyParties = false,
    ): PlatformOrder {
        $action = $notifyParties ? self::ACTION_CONTACT_PARTIES : self::ACTION_SERVICE_COMMENT;
        $this->assertCan($order, self::ACTOR_ADMIN, $action);

        $comment = trim($comment);
        if ($comment === '') {
            throw ValidationException::withMessages([
                'comment' => 'Введите текст служебного комментария.',
            ]);
        }

        $from = $order->status;

        PlatformOrderStatusLog::query()->create([
            'platform_order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $from,
            'action' => $action,
            'comment' => $comment,
            'performed_by_user_id' => $user->id,
            'meta' => [
                'actor' => self::ACTOR_ADMIN,
                'service' => true,
                'notify_parties' => $notifyParties,
            ],
        ]);

        if ($notifyParties) {
            $this->notifyParties(
                $order->fresh([
                    'distributorProfile.user',
                    'endCompanyProfile.user',
                    'transportCompany',
                ]),
                $from,
                $from,
                'Сообщение модератора: '.$comment,
            );
        }

        return $order->fresh(['items', 'statusLogs.performedBy', 'transportCompany', 'distributorProfile', 'endCompanyProfile']);
    }

    public function pause(PlatformOrder $order, User $user, string $reason): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_ADMIN, self::ACTION_PAUSE);

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'pause_reason' => 'Укажите причину приостановки заказа.',
            ]);
        }

        if ($order->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => 'Нельзя приостановить завершённый или отклонённый заказ.',
            ]);
        }

        $from = $order->status;
        $order->paused_at = now();
        $order->paused_by_user_id = $user->id;
        $order->pause_reason = $reason;
        $order->save();

        PlatformOrderStatusLog::query()->create([
            'platform_order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $from,
            'action' => self::ACTION_PAUSE,
            'comment' => $reason,
            'performed_by_user_id' => $user->id,
            'meta' => ['actor' => self::ACTOR_ADMIN, 'paused' => true],
        ]);

        $this->notifyParties(
            $order->fresh([
                'distributorProfile.user',
                'endCompanyProfile.user',
                'transportCompany',
            ]),
            $from,
            $from,
            'Заказ временно приостановлен модератором: '.$reason,
        );

        return $order->fresh(['items', 'statusLogs.performedBy', 'transportCompany', 'distributorProfile', 'endCompanyProfile']);
    }

    public function resume(PlatformOrder $order, User $user, ?string $comment = null): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_ADMIN, self::ACTION_RESUME);

        if (! $order->isPaused()) {
            throw ValidationException::withMessages([
                'status' => 'Заказ не приостановлен.',
            ]);
        }

        $from = $order->status;
        $note = trim((string) $comment) ?: 'Приостановка снята модератором';

        $order->paused_at = null;
        $order->paused_by_user_id = null;
        $order->pause_reason = null;
        $order->save();

        PlatformOrderStatusLog::query()->create([
            'platform_order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $from,
            'action' => self::ACTION_RESUME,
            'comment' => $note,
            'performed_by_user_id' => $user->id,
            'meta' => ['actor' => self::ACTOR_ADMIN, 'paused' => false],
        ]);

        $this->notifyParties(
            $order->fresh([
                'distributorProfile.user',
                'endCompanyProfile.user',
                'transportCompany',
            ]),
            $from,
            $from,
            'Приостановка заказа снята. '.$note,
        );

        return $order->fresh(['items', 'statusLogs.performedBy', 'transportCompany', 'distributorProfile', 'endCompanyProfile']);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function transition(
        PlatformOrder $order,
        string $toStatus,
        User $user,
        string $action,
        ?string $comment = null,
        array $meta = [],
    ): PlatformOrder {
        $from = $order->status;

        if ($from === $toStatus && $action !== self::ACTION_SEND_FOR_APPROVAL) {
            return $order;
        }

        $order->status = $toStatus;
        $order->status_changed_at = now();
        $order->status_changed_by_user_id = $user->id;
        $order->save();

        PlatformOrderStatusLog::query()->create([
            'platform_order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $toStatus,
            'action' => $action,
            'comment' => $comment,
            'performed_by_user_id' => $user->id,
            'meta' => $meta ?: null,
        ]);

        $this->notifyParties($order->fresh([
            'distributorProfile.user',
            'endCompanyProfile.user',
            'transportCompany',
        ]), $from, $toStatus, $comment);

        return $order->fresh(['items', 'statusLogs', 'transportCompany', 'distributorProfile', 'endCompanyProfile']);
    }

    private function assertCan(PlatformOrder $order, string $actor, string $action): void
    {
        if (! $this->can($order, $actor, $action)) {
            throw ValidationException::withMessages([
                'status' => 'Действие недоступно для текущего статуса заказа.',
            ]);
        }
    }

    private function notifyParties(
        PlatformOrder $order,
        string $from,
        string $to,
        ?string $comment,
    ): void {
        $message = $this->notificationMessage($order, $to, $comment);
        $recipients = collect();

        $buyerUser = $order->endCompanyProfile?->user;
        $supplierUser = $order->distributorProfile?->user;

        if ($buyerUser) {
            $recipients->push($buyerUser);
        }
        if ($supplierUser && (! $buyerUser || $supplierUser->id !== $buyerUser->id)) {
            $recipients->push($supplierUser);
        }

        foreach ($recipients->unique('id') as $user) {
            try {
                $user->notify(new OrderStatusChangedNotification($order, $from, $to, $message));
            } catch (\Throwable) {
                // Не блокируем смену статуса из-за сбоя уведомлений.
            }

            if ($this->emailNotificationsEnabled() && filled($user->email)) {
                try {
                    Mail::to($user->email)->send(new OrderStatusChangedMail($order, $message));
                } catch (\Throwable) {
                    // Email опционален.
                }
            }
        }
    }

    private function notificationMessage(PlatformOrder $order, string $to, ?string $comment): string
    {
        $number = $order->order_number;

        return match ($to) {
            PlatformOrder::STATUS_CONFIRMED => "Поставщик подтвердил заказ №{$number}",
            PlatformOrder::STATUS_NEEDS_APPROVAL => "Заказ №{$number} ожидает подтверждения покупателя",
            PlatformOrder::STATUS_REJECTED => "Заказ №{$number} отклонён".($order->rejection_reason ? ': '.$order->rejection_reason : ''),
            PlatformOrder::STATUS_READY_TO_SHIP => "Заказ №{$number} готов к отгрузке",
            PlatformOrder::STATUS_SHIPPED => "Ваш заказ №{$number} был отгружен".($order->tracking_number ? '. ТТН: '.$order->tracking_number : ''),
            PlatformOrder::STATUS_COMPLETED => "Заказ №{$number} завершён",
            PlatformOrder::STATUS_AWAITING_CONFIRMATION => $comment
                ? "Заказ №{$number}: {$comment}"
                : "Заказ №{$number} ожидает подтверждения поставщика",
            default => $comment ?: "Статус заказа №{$number} изменён",
        };
    }

    private function emailNotificationsEnabled(): bool
    {
        return (bool) SystemSetting::getActiveParsed('notifications.email_enabled', true);
    }
}
