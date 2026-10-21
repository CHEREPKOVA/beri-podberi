<?php

namespace App\Services\Order;

use App\Mail\OrderStatusChangedMail;
use App\Models\DeliveryMethod;
use App\Models\DistributorProduct;
use App\Models\DistributorWarehouse;
use App\Models\EndCompanyActionLog;
use App\Models\EndCompanyProfile;
use App\Models\ManufacturerProfile;
use App\Models\PlatformOrder;
use App\Models\PlatformOrderItem;
use App\Models\PlatformOrderStatusLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\OrderStatusChangedNotification;
use App\Services\EndCompanyActionLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
            OrderCorrespondenceService::ACTION_MESSAGE => 'Сообщение в переписке',
            OrderCorrespondenceService::ACTION_MESSAGE_DELETED => 'Удаление сообщения',
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

        app(OrderStockReservationService::class)->reserve($order);

        return $this->transition(
            $order,
            PlatformOrder::STATUS_CONFIRMED,
            $user,
            self::ACTION_CONFIRM,
            'Поставщик подтвердил заказ',
        );
    }

    public function reject(PlatformOrder $order, User $user, string $code, string $comment): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_SUPPLIER, self::ACTION_REJECT);

        $reasons = PlatformOrder::supplierRejectionReasons();
        if (! isset($reasons[$code])) {
            throw ValidationException::withMessages([
                'rejection_code' => 'Выберите причину отклонения.',
            ]);
        }

        $comment = trim($comment);
        if ($comment === '') {
            throw ValidationException::withMessages([
                'rejection_comment' => 'Укажите комментарий к отклонению.',
            ]);
        }

        app(OrderStockReservationService::class)->release($order);

        $order->rejection_reason = $reasons[$code].'. '.$comment;

        return $this->transition(
            $order,
            PlatformOrder::STATUS_REJECTED,
            $user,
            self::ACTION_REJECT,
            $order->rejection_reason,
            [
                'rejection_code' => $code,
                'rejection_comment' => $comment,
            ],
        );
    }

    /**
     * @param  list<array{
     *     id?: ?int,
     *     distributor_product_id?: ?int,
     *     replace_with_distributor_product_id?: ?int,
     *     quantity?: int,
     *     unit_price?: float|int|string,
     *     distributor_warehouse_id?: ?int,
     *     reason?: ?string,
     *     delete?: bool|int|string
     * }>  $items
     * @param  array{
     *     delivery_method_id?: int|string|null,
     *     delivery_date?: string|null,
     *     delivery_time_from?: string|null,
     *     delivery_time_to?: string|null,
     *     distributor_warehouse_id?: int|string|null
     * }  $logistics
     */
    public function sendForApproval(
        PlatformOrder $order,
        User $user,
        array $items,
        ?string $comment = null,
        bool $canChangePrice = true,
        array $logistics = [],
    ): PlatformOrder {
        $this->assertCan($order, self::ACTOR_SUPPLIER, self::ACTION_SEND_FOR_APPROVAL);

        $hasLogisticsInput = $this->logisticsPayloadProvided($logistics);
        if ($items === [] && ! $hasLogisticsInput) {
            throw ValidationException::withMessages([
                'items' => 'Укажите изменения по позициям или логистике заказа.',
            ]);
        }

        // Пока заказ на согласовании, резерв не держим.
        app(OrderStockReservationService::class)->release($order);

        $previousTotal = round((float) $order->total_amount, 2);
        $order->load(['items', 'deliveryMethod', 'distributorWarehouse']);
        $snapshot = $this->snapshotItems($order);
        $logisticsSnapshot = $this->snapshotLogistics($order);
        $analogOptions = $this->analogOptionsByItem($order);
        $changes = [];
        $logisticsChanges = [];
        $newTotal = 0.0;

        DB::transaction(function () use (
            $order,
            $items,
            $canChangePrice,
            $analogOptions,
            $logistics,
            $hasLogisticsInput,
            &$changes,
            &$logisticsChanges,
            &$newTotal,
        ): void {
            if ($hasLogisticsInput) {
                $logisticsChanges = $this->applyLogisticsChanges($order, $logistics);
            }

            if ($items === []) {
                $newTotal = round((float) $order->items()->sum('line_total'), 2);

                return;
            }

            $warehouseNames = $this->warehouseNamesForOrder($order);

            foreach ($items as $row) {
                $delete = filter_var($row['delete'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $itemId = isset($row['id']) ? (int) $row['id'] : 0;
                $replaceOfferId = (int) ($row['replace_with_distributor_product_id'] ?? 0);

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

                    if ($replaceOfferId > 0) {
                        $allowedAnalogIds = array_map(
                            static fn (array $option): int => (int) $option['id'],
                            $analogOptions[(int) $item->id] ?? [],
                        );
                        if (! in_array($replaceOfferId, $allowedAnalogIds, true)) {
                            throw ValidationException::withMessages([
                                'items' => 'Выбранный аналог недоступен для этой позиции.',
                            ]);
                        }
                        $changes[] = $this->replaceItemWithAnalog($order, $item, $row, $replaceOfferId, $canChangePrice, $warehouseNames);

                        continue;
                    }

                    $oldQty = (int) $item->quantity;
                    $oldPrice = round((float) $item->unit_price, 2);
                    $oldWarehouse = $item->distributor_warehouse_id;
                    $qty = (int) ($row['quantity'] ?? $oldQty);
                    $price = $canChangePrice && array_key_exists('unit_price', $row) && $row['unit_price'] !== null && $row['unit_price'] !== ''
                        ? round((float) $row['unit_price'], 2)
                        : $oldPrice;
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
                            'old_warehouse_id' => $oldWarehouse,
                            'new_warehouse_id' => $warehouseId,
                            'old_warehouse_name' => $oldWarehouse ? ($warehouseNames[(int) $oldWarehouse] ?? null) : null,
                            'new_warehouse_name' => $warehouseId ? ($warehouseNames[$warehouseId] ?? null) : null,
                            'reason' => filled($row['reason'] ?? null) ? trim((string) $row['reason']) : null,
                            'action' => 'updated',
                        ];
                    }

                    continue;
                }

                $offerId = (int) ($row['distributor_product_id'] ?? 0);
                if ($offerId < 1) {
                    throw ValidationException::withMessages([
                        'items' => 'Для новой позиции укажите товар.',
                    ]);
                }

                $offer = $this->supplierOffer($order, $offerId);
                $qty = (int) ($row['quantity'] ?? 1);
                $price = $canChangePrice && array_key_exists('unit_price', $row) && $row['unit_price'] !== null && $row['unit_price'] !== ''
                    ? round((float) $row['unit_price'], 2)
                    : round((float) ($offer->retail_price ?? 0), 2);
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

            $newTotal = round((float) $order->items()->sum('line_total'), 2);
            $order->total_amount = $newTotal;
            $order->save();
        });

        if ($changes === [] && $logisticsChanges === []) {
            throw ValidationException::withMessages([
                'items' => 'Нет фактических изменений для отправки на согласование.',
            ]);
        }

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
                'logistics_changes' => $logisticsChanges,
                'items_snapshot' => $snapshot,
                'logistics_snapshot' => $logisticsSnapshot,
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
     *     logistics_changes: list<array<string, mixed>>,
     *     previous_total: ?float,
     *     new_total: ?float,
     *     created_at: ?Carbon
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
            'logistics_changes' => array_values($meta['logistics_changes'] ?? []),
            'previous_total' => isset($meta['previous_total']) ? (float) $meta['previous_total'] : null,
            'new_total' => isset($meta['new_total']) ? (float) $meta['new_total'] : null,
            'created_at' => $log->created_at,
        ];
    }

    public function approveChanges(PlatformOrder $order, User $user): PlatformOrder
    {
        $this->assertCan($order, self::ACTOR_BUYER, self::ACTION_APPROVE_CHANGES);

        app(OrderStockReservationService::class)->reserve($order);

        $order = $this->transition(
            $order,
            PlatformOrder::STATUS_CONFIRMED,
            $user,
            self::ACTION_APPROVE_CHANGES,
            'Покупатель согласовал изменения',
        );

        $this->logEndCompanyAction(
            $order,
            $user,
            EndCompanyActionLog::ACTION_ORDER_CHANGES_APPROVED,
            'Утверждены изменения по заказу '.$order->order_number,
        );

        return $order;
    }

    public const REJECT_CHANGES_RENEGOTIATE = 'renegotiate';

    public const REJECT_CHANGES_CANCEL = 'cancel';

    public function rejectChanges(
        PlatformOrder $order,
        User $user,
        ?string $reason = null,
        string $outcome = self::REJECT_CHANGES_RENEGOTIATE,
    ): PlatformOrder {
        $this->assertCan($order, self::ACTOR_BUYER, self::ACTION_REJECT_CHANGES);

        if (! in_array($outcome, [self::REJECT_CHANGES_RENEGOTIATE, self::REJECT_CHANGES_CANCEL], true)) {
            throw ValidationException::withMessages([
                'outcome' => 'Выберите действие: вернуть поставщику или отменить заказ.',
            ]);
        }

        $reason = trim((string) $reason);
        $comment = $reason !== ''
            ? $reason
            : 'Покупатель отклонил предложенные изменения';

        $proposalLog = $order->statusLogs()
            ->where('action', self::ACTION_SEND_FOR_APPROVAL)
            ->orderByDesc('id')
            ->first();
        $meta = is_array($proposalLog?->meta) ? $proposalLog->meta : [];
        $snapshot = $meta['items_snapshot'] ?? null;
        $previousTotal = $meta['previous_total'] ?? null;
        $logisticsSnapshot = $meta['logistics_snapshot'] ?? null;

        if (is_array($snapshot) && $snapshot !== []) {
            $this->restoreItems($order, $snapshot, $previousTotal);
        }
        if (is_array($logisticsSnapshot) && $logisticsSnapshot !== []) {
            $this->restoreLogistics($order, $logisticsSnapshot);
        }

        if ($outcome === self::REJECT_CHANGES_CANCEL) {
            $order->rejection_reason = $comment;
            $order = $this->transition(
                $order,
                PlatformOrder::STATUS_REJECTED,
                $user,
                self::ACTION_REJECT_CHANGES,
                $comment,
                [
                    'type' => 'buyer_rejected_changes_cancelled',
                    'buyer_rejection_reason' => $reason !== '' ? $reason : null,
                    'outcome' => $outcome,
                ],
            );
        } else {
            // Возвращаем поставщику на доработку / повторное согласование.
            $order = $this->transition(
                $order,
                PlatformOrder::STATUS_AWAITING_CONFIRMATION,
                $user,
                self::ACTION_REJECT_CHANGES,
                $comment,
                [
                    'type' => 'buyer_rejected_changes',
                    'buyer_rejection_reason' => $reason !== '' ? $reason : null,
                    'outcome' => $outcome,
                ],
            );
        }

        $this->logEndCompanyAction(
            $order,
            $user,
            EndCompanyActionLog::ACTION_ORDER_CHANGES_REJECTED,
            'Отклонены изменения по заказу '.$order->order_number,
            ['reason' => $reason !== '' ? $reason : null, 'outcome' => $outcome],
        );

        return $order;
    }

    /**
     * Последнее отклонение изменений покупателем (для экрана поставщика).
     *
     * @return array{reason: ?string, comment: ?string, created_at: ?Carbon}|null
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
        app(OrderStockReservationService::class)->release($order);
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

        $updated = $this->transition(
            $order,
            PlatformOrder::STATUS_AWAITING_CONFIRMATION,
            $user,
            self::ACTION_SUBMIT,
            'Заказ отправлен производителю на подтверждение',
        );

        app(OrderCreatedNotifier::class)->notifySupplier($updated, $user);

        return $updated;
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
     * @param  array{
     *     tracking_number: string,
     *     shipped_at?: ?string,
     *     shipping_from_warehouse?: ?string,
     *     transport_company_id?: ?int,
     *     shipping_comment?: ?string
     * }  $data
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

        $shippingComment = filled($data['shipping_comment'] ?? null)
            ? trim((string) $data['shipping_comment'])
            : null;

        $order->tracking_number = $tracking;
        $order->shipped_at = filled($data['shipped_at'] ?? null)
            ? $data['shipped_at']
            : now();
        $order->shipping_from_warehouse = filled($data['shipping_from_warehouse'] ?? null)
            ? trim((string) $data['shipping_from_warehouse'])
            : null;
        $order->shipping_comment = $shippingComment;

        if (isset($data['transport_company_id']) && $data['transport_company_id']) {
            $order->transport_company_id = (int) $data['transport_company_id'];
        }

        app(OrderStockReservationService::class)->fulfill($order);

        $logComment = 'Заказ отгружен. ТТН: '.$tracking;
        if ($shippingComment !== null) {
            $logComment .= '. '.$shippingComment;
        }

        return $this->transition(
            $order,
            PlatformOrder::STATUS_SHIPPED,
            $user,
            self::ACTION_MARK_SHIPPED,
            $logComment,
            array_filter([
                'tracking_number' => $tracking,
                'shipping_comment' => $shippingComment,
            ], static fn ($value) => $value !== null && $value !== ''),
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
     * @return array<int, list<array{id: int, name: string, sku: string, price: float}>>
     */
    public function analogOptionsByItem(PlatformOrder $order): array
    {
        $order->loadMissing('items');

        $productIds = $order->items
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            return [];
        }

        $forward = DB::table('product_analogs')
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'analog_product_id']);
        $reverse = DB::table('product_analogs')
            ->whereIn('analog_product_id', $productIds)
            ->get(['product_id', 'analog_product_id']);

        $analogIdsByProduct = [];
        $allAnalogIds = [];
        foreach ($forward as $row) {
            $pid = (int) $row->product_id;
            $aid = (int) $row->analog_product_id;
            $analogIdsByProduct[$pid][] = $aid;
            $allAnalogIds[] = $aid;
        }
        foreach ($reverse as $row) {
            $pid = (int) $row->analog_product_id;
            $aid = (int) $row->product_id;
            $analogIdsByProduct[$pid][] = $aid;
            $allAnalogIds[] = $aid;
        }

        $allAnalogIds = array_values(array_unique($allAnalogIds));
        if ($allAnalogIds === []) {
            return [];
        }

        $offersQuery = DistributorProduct::query()
            ->where('distributor_profile_id', $order->distributor_profile_id)
            ->where('status', DistributorProduct::STATUS_ACTIVE)
            ->whereIn('source_product_id', $allAnalogIds);

        if ($order->isDistributorPurchase() && $order->manufacturer_profile_id) {
            $offersQuery->where('manufacturer_profile_id', $order->manufacturer_profile_id);
        }

        $offersBySource = $offersQuery->get()->groupBy('source_product_id');
        $result = [];

        foreach ($order->items as $item) {
            $options = [];
            foreach (array_unique($analogIdsByProduct[(int) $item->product_id] ?? []) as $analogProductId) {
                foreach ($offersBySource->get($analogProductId, collect()) as $offer) {
                    if ((int) $offer->id === (int) $item->distributor_product_id) {
                        continue;
                    }
                    $options[] = [
                        'id' => (int) $offer->id,
                        'name' => $offer->name,
                        'sku' => (string) ($offer->manufacturer_sku ?: $offer->internal_sku ?: ''),
                        'price' => round((float) $offer->retail_price, 2),
                    ];
                }
            }
            $result[(int) $item->id] = $options;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $logistics
     */
    private function logisticsPayloadProvided(array $logistics): bool
    {
        foreach (['delivery_method_id', 'delivery_date', 'delivery_time_from', 'delivery_time_to', 'distributor_warehouse_id'] as $key) {
            if (array_key_exists($key, $logistics)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotLogistics(PlatformOrder $order): array
    {
        return [
            'delivery_method_id' => $order->delivery_method_id,
            'delivery_method_name' => $order->deliveryMethod?->name,
            'delivery_date' => $order->delivery_date?->format('Y-m-d'),
            'delivery_time_from' => $order->delivery_time_from,
            'delivery_time_to' => $order->delivery_time_to,
            'distributor_warehouse_id' => $order->distributor_warehouse_id,
            'distributor_warehouse_name' => $order->distributorWarehouse?->name,
        ];
    }

    /**
     * @param  array<string, mixed>  $logistics
     * @return list<array{field: string, label: string, old: mixed, new: mixed}>
     */
    private function applyLogisticsChanges(PlatformOrder $order, array $logistics): array
    {
        $changes = [];

        if (array_key_exists('delivery_method_id', $logistics)) {
            $oldId = $order->delivery_method_id ? (int) $order->delivery_method_id : null;
            $newId = $logistics['delivery_method_id'] !== null && $logistics['delivery_method_id'] !== ''
                ? (int) $logistics['delivery_method_id']
                : null;
            if ($oldId !== $newId) {
                $newName = $newId
                    ? DeliveryMethod::query()->whereKey($newId)->value('name')
                    : null;
                if ($newId && ! $newName) {
                    throw ValidationException::withMessages([
                        'delivery_method_id' => 'Способ доставки не найден.',
                    ]);
                }
                $changes[] = [
                    'field' => 'delivery_method_id',
                    'label' => 'Способ доставки',
                    'old' => $order->deliveryMethod?->name,
                    'new' => $newName,
                ];
                $order->delivery_method_id = $newId;
            }
        }

        if (array_key_exists('delivery_date', $logistics)) {
            $old = $order->delivery_date?->format('Y-m-d');
            $new = filled($logistics['delivery_date'] ?? null)
                ? Carbon::parse((string) $logistics['delivery_date'])->format('Y-m-d')
                : null;
            if ($old !== $new) {
                $changes[] = [
                    'field' => 'delivery_date',
                    'label' => 'Дата доставки',
                    'old' => $old ? Carbon::parse($old)->format('d.m.Y') : null,
                    'new' => $new ? Carbon::parse($new)->format('d.m.Y') : null,
                ];
                $order->delivery_date = $new;
            }
        }

        if (array_key_exists('delivery_time_from', $logistics)) {
            $old = $order->delivery_time_from ? substr((string) $order->delivery_time_from, 0, 5) : null;
            $new = filled($logistics['delivery_time_from'] ?? null)
                ? substr((string) $logistics['delivery_time_from'], 0, 5)
                : null;
            if ($old !== $new) {
                $changes[] = [
                    'field' => 'delivery_time_from',
                    'label' => 'Время доставки с',
                    'old' => $old,
                    'new' => $new,
                ];
                $order->delivery_time_from = $new;
            }
        }

        if (array_key_exists('delivery_time_to', $logistics)) {
            $old = $order->delivery_time_to ? substr((string) $order->delivery_time_to, 0, 5) : null;
            $new = filled($logistics['delivery_time_to'] ?? null)
                ? substr((string) $logistics['delivery_time_to'], 0, 5)
                : null;
            if ($old !== $new) {
                $changes[] = [
                    'field' => 'delivery_time_to',
                    'label' => 'Время доставки до',
                    'old' => $old,
                    'new' => $new,
                ];
                $order->delivery_time_to = $new;
            }
        }

        if (array_key_exists('distributor_warehouse_id', $logistics)) {
            $oldId = $order->distributor_warehouse_id ? (int) $order->distributor_warehouse_id : null;
            $newId = $logistics['distributor_warehouse_id'] !== null && $logistics['distributor_warehouse_id'] !== ''
                ? (int) $logistics['distributor_warehouse_id']
                : null;
            if ($oldId !== $newId) {
                $names = $this->warehouseNamesForOrder($order);
                if ($newId && ! isset($names[$newId])) {
                    throw ValidationException::withMessages([
                        'distributor_warehouse_id' => 'Склад не найден у поставщика.',
                    ]);
                }
                $changes[] = [
                    'field' => 'distributor_warehouse_id',
                    'label' => 'Склад отгрузки',
                    'old' => $oldId ? ($names[$oldId] ?? null) : null,
                    'new' => $newId ? ($names[$newId] ?? null) : null,
                ];
                $order->distributor_warehouse_id = $newId;
            }
        }

        if ($changes !== []) {
            $order->save();
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function restoreLogistics(PlatformOrder $order, array $snapshot): void
    {
        $order->delivery_method_id = $snapshot['delivery_method_id'] ?? $order->delivery_method_id;
        $order->delivery_date = $snapshot['delivery_date'] ?? null;
        $order->delivery_time_from = $snapshot['delivery_time_from'] ?? null;
        $order->delivery_time_to = $snapshot['delivery_time_to'] ?? null;
        $order->distributor_warehouse_id = $snapshot['distributor_warehouse_id'] ?? null;
        $order->save();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function snapshotItems(PlatformOrder $order): array
    {
        return $order->items()->get()->map(function (PlatformOrderItem $item): array {
            return [
                'id' => $item->id,
                'platform_order_id' => $item->platform_order_id,
                'distributor_product_id' => $item->distributor_product_id,
                'product_id' => $item->product_id,
                'distributor_warehouse_id' => $item->distributor_warehouse_id,
                'name' => $item->name,
                'sku' => $item->sku,
                'manufacturer_name' => $item->manufacturer_name,
                'quantity' => $item->quantity,
                'pack_quantity' => $item->pack_quantity,
                'min_order_quantity' => $item->min_order_quantity,
                'unit_price' => $item->unit_price,
                'list_unit_price' => $item->list_unit_price,
                'line_total' => $item->line_total,
                'discount_set_id' => $item->discount_set_id,
                'distributor_discount_set_id' => $item->distributor_discount_set_id,
                'discount_label' => $item->discount_label,
            ];
        })->all();
    }

    /**
     * @param  list<array<string, mixed>>  $snapshot
     */
    private function restoreItems(PlatformOrder $order, array $snapshot, mixed $previousTotal): void
    {
        DB::transaction(function () use ($order, $snapshot, $previousTotal): void {
            $order->items()->delete();
            foreach ($snapshot as $row) {
                $item = new PlatformOrderItem;
                $item->forceFill(collect($row)->except('id')->all());
                if (! empty($row['id'])) {
                    $item->id = (int) $row['id'];
                }
                $item->save();
            }

            if ($previousTotal !== null) {
                $order->total_amount = round((float) $previousTotal, 2);
                $order->save();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $warehouseNames
     * @return array<string, mixed>
     */
    private function replaceItemWithAnalog(
        PlatformOrder $order,
        PlatformOrderItem $item,
        array $row,
        int $replaceOfferId,
        bool $canChangePrice,
        array $warehouseNames,
    ): array {
        $offer = $this->supplierOffer($order, $replaceOfferId);
        $oldQty = (int) $item->quantity;
        $oldPrice = round((float) $item->unit_price, 2);
        $qty = (int) ($row['quantity'] ?? $oldQty);
        $price = $canChangePrice && array_key_exists('unit_price', $row) && $row['unit_price'] !== null && $row['unit_price'] !== ''
            ? round((float) $row['unit_price'], 2)
            : round((float) ($offer->retail_price ?? 0), 2);

        if ($qty < 1 || $price < 0) {
            throw ValidationException::withMessages([
                'items' => 'Некорректные количество или цена при замене на аналог.',
            ]);
        }

        $warehouseId = array_key_exists('distributor_warehouse_id', $row)
            ? ($row['distributor_warehouse_id'] !== null && $row['distributor_warehouse_id'] !== ''
                ? (int) $row['distributor_warehouse_id']
                : null)
            : $item->distributor_warehouse_id;

        $lineTotal = round($qty * $price, 2);
        $oldName = $item->name;
        $oldSku = $item->sku;
        $oldWarehouse = $item->distributor_warehouse_id;

        $item->update([
            'distributor_product_id' => $offer->id,
            'product_id' => $offer->source_product_id,
            'distributor_warehouse_id' => $warehouseId,
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

        return [
            'id' => $item->id,
            'name' => $offer->name,
            'sku' => $offer->manufacturer_sku ?: $offer->internal_sku,
            'old_name' => $oldName,
            'old_sku' => $oldSku,
            'old_quantity' => $oldQty,
            'new_quantity' => $qty,
            'old_unit_price' => $oldPrice,
            'new_unit_price' => $price,
            'old_line_total' => round($oldQty * $oldPrice, 2),
            'new_line_total' => $lineTotal,
            'old_warehouse_id' => $oldWarehouse,
            'new_warehouse_id' => $warehouseId,
            'old_warehouse_name' => $oldWarehouse ? ($warehouseNames[(int) $oldWarehouse] ?? null) : null,
            'new_warehouse_name' => $warehouseId ? ($warehouseNames[$warehouseId] ?? null) : null,
            'reason' => filled($row['reason'] ?? null) ? trim((string) $row['reason']) : 'Замена на аналог',
            'action' => 'replaced',
        ];
    }

    private function supplierOffer(PlatformOrder $order, int $offerId): DistributorProduct
    {
        $query = DistributorProduct::query()
            ->whereKey($offerId)
            ->where('distributor_profile_id', $order->distributor_profile_id);

        if ($order->isDistributorPurchase() && $order->manufacturer_profile_id) {
            $query->where('manufacturer_profile_id', $order->manufacturer_profile_id);
        }

        $offer = $query->first();
        if ($offer === null) {
            throw ValidationException::withMessages([
                'items' => 'Товар не найден в каталоге поставщика.',
            ]);
        }

        return $offer;
    }

    /**
     * @return array<int, string>
     */
    private function warehouseNamesForOrder(PlatformOrder $order): array
    {
        if (! $order->distributor_profile_id) {
            return [];
        }

        return DistributorWarehouse::query()
            ->where('distributor_profile_id', $order->distributor_profile_id)
            ->pluck('name', 'id')
            ->map(fn ($name): string => (string) $name)
            ->all();
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

        $fresh = $order->fresh([
            'distributorProfile.user',
            'endCompanyProfile.user',
            'manufacturerProfile.user',
            'transportCompany',
        ]);

        $this->notifyParties($fresh, $from, $toStatus, $comment);

        try {
            app(OrderCorrespondenceService::class)->postFromWorkflow(
                $fresh,
                $action,
                $from,
                $toStatus,
                $comment,
            );
        } catch (\Throwable) {
            // Переписка не должна блокировать смену статуса.
        }

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

        // Закупка дистрибьютора у производителя: buyer = дистрибьютор, supplier = производитель.
        // Остальные каналы: buyer = конечная компания, supplier = дистрибьютор.
        $buyerUser = $order->isDistributorPurchase()
            ? $order->distributorProfile?->user
            : $order->endCompanyProfile?->user;
        $supplierUser = $order->isDistributorPurchase()
            ? $order->manufacturerProfile?->user
            : $order->distributorProfile?->user;

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
            PlatformOrder::STATUS_SHIPPED => "Ваш заказ №{$number} был отгружен"
                .($order->tracking_number ? '. ТТН: '.$order->tracking_number : '')
                .(filled($order->shipping_comment) ? '. '.$order->shipping_comment : ''),
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

    /**
     * @param  array<string, mixed>  $meta
     */
    private function logEndCompanyAction(
        PlatformOrder $order,
        User $user,
        string $action,
        string $description,
        array $meta = [],
    ): void {
        if (! $order->end_company_profile_id) {
            return;
        }

        try {
            $profile = EndCompanyProfile::query()->find($order->end_company_profile_id);
            if (! $profile) {
                return;
            }

            app(EndCompanyActionLogger::class)->log(
                $profile,
                $user,
                $action,
                $description,
                'platform_order',
                (int) $order->id,
                array_merge(['order_number' => $order->order_number], $meta),
            );
        } catch (\Throwable) {
            // Журнал компании не блокирует смену статуса.
        }
    }
}
