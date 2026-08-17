<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PlatformOrder extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_AWAITING_CONFIRMATION = 'awaiting_confirmation';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_IN_WORK = 'in_work';

    public const STATUS_NEEDS_APPROVAL = 'needs_approval';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_READY_TO_SHIP = 'ready_to_ship';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_COMPLETED = 'completed';

    /** @deprecated legacy alias */
    public const STATUS_PROCESSING = 'awaiting_confirmation';

    /** @deprecated legacy alias */
    public const STATUS_CANCELLED = 'rejected';

    public const SOURCE_LK = 'lk';

    public const SOURCE_API = 'api';

    public const SOURCE_ONE_C = '1c';

    public const SOURCE_MANUAL = 'manual';

    /** Заказ конечной компании у дистрибьютора. */
    public const CHANNEL_END_COMPANY_DISTRIBUTOR = 'end_company_distributor';

    /** Закупка дистрибьютора у производителя. */
    public const CHANNEL_DISTRIBUTOR_MANUFACTURER = 'distributor_manufacturer';

    /**
     * Основная линия прогресса (happy path).
     *
     * @return list<string>
     */
    public static function progressStatuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_AWAITING_CONFIRMATION,
            self::STATUS_CONFIRMED,
            self::STATUS_IN_WORK,
            self::STATUS_READY_TO_SHIP,
            self::STATUS_SHIPPED,
            self::STATUS_COMPLETED,
        ];
    }

    protected $fillable = [
        'order_number',
        'source',
        'order_channel',
        'distributor_profile_id',
        'responsible_contact_id',
        'manufacturer_responsible_contact_id',
        'manufacturer_profile_id',
        'end_company_profile_id',
        'delivery_method_id',
        'transport_company_id',
        'end_company_delivery_address_id',
        'distributor_warehouse_id',
        'buyer_comment',
        'rejection_reason',
        'tracking_number',
        'shipped_at',
        'received_at',
        'completion_notes',
        'shipping_from_warehouse',
        'status_changed_at',
        'status_changed_by_user_id',
        'paused_at',
        'paused_by_user_id',
        'pause_reason',
        'has_active_claim',
        'has_integration_error',
        'delivery_date',
        'delivery_time_from',
        'delivery_time_to',
        'delivery_vehicle_type',
        'total_amount',
        'status',
        'ordered_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'ordered_at' => 'datetime',
            'delivery_date' => 'date',
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'paused_at' => 'datetime',
            'has_active_claim' => 'boolean',
            'has_integration_error' => 'boolean',
        ];
    }

    public function discountTotal(): float
    {
        return round((float) $this->items->sum(fn (PlatformOrderItem $item): float => $item->discountAmount()), 2);
    }

    public function listTotal(): float
    {
        return round((float) $this->items->sum(function (PlatformOrderItem $item): float {
            $list = $item->list_unit_price !== null ? (float) $item->list_unit_price : (float) $item->unit_price;

            return $list * $item->quantity;
        }), 2);
    }

    /**
     * @return array<string, string>
     */
    public static function fallbackStatusLabels(): array
    {
        return [
            self::STATUS_NEW => 'Новый',
            self::STATUS_AWAITING_CONFIRMATION => 'Ожидает подтверждения',
            self::STATUS_CONFIRMED => 'Подтверждён',
            self::STATUS_IN_WORK => 'В работе',
            self::STATUS_NEEDS_APPROVAL => 'Ожидает подтверждения',
            self::STATUS_REJECTED => 'Отклонён',
            self::STATUS_READY_TO_SHIP => 'Готов к отгрузке',
            self::STATUS_SHIPPED => 'Отгружен',
            self::STATUS_COMPLETED => 'Завершён',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fallbackStatusDescriptions(): array
    {
        return [
            self::STATUS_NEW => 'Заказ создан и ожидает обработки поставщиком.',
            self::STATUS_AWAITING_CONFIRMATION => 'Поставщик проверяет заказ.',
            self::STATUS_CONFIRMED => 'Заказ принят поставщиком и готовится к сборке.',
            self::STATUS_IN_WORK => 'Заказ передан на комплектование склада.',
            self::STATUS_NEEDS_APPROVAL => 'Ожидается подтверждение изменений покупателем.',
            self::STATUS_REJECTED => 'Заказ отклонён или отменён.',
            self::STATUS_READY_TO_SHIP => 'Заказ собран и готов к отгрузке.',
            self::STATUS_SHIPPED => 'Заказ отгружен, ожидается получение.',
            self::STATUS_COMPLETED => 'Заказ полностью выполнен.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_LK => 'Личный кабинет',
            self::SOURCE_API => 'API',
            self::SOURCE_ONE_C => '1С',
            self::SOURCE_MANUAL => 'Вручную',
        ];
    }

    public function sourceLabel(): string
    {
        return self::sourceLabels()[$this->source] ?? ($this->source ?: '—');
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_NEW => 'bg-red-100 text-[#c3242a] dark:bg-red-900/30 dark:text-red-300',
            self::STATUS_AWAITING_CONFIRMATION => 'bg-red-50 text-[#c3242a] ring-1 ring-[#c3242a]/30 dark:bg-red-900/20 dark:text-red-300',
            self::STATUS_CONFIRMED => 'bg-[#c3242a] text-white dark:bg-[#c3242a] dark:text-white',
            self::STATUS_IN_WORK => 'bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-200',
            self::STATUS_NEEDS_APPROVAL => 'bg-red-100 text-[#a01e24] ring-1 ring-[#c3242a]/40 dark:bg-red-900/30 dark:text-red-200',
            self::STATUS_REJECTED => 'bg-red-200 text-[#7f161b] dark:bg-red-900/40 dark:text-red-200',
            self::STATUS_READY_TO_SHIP => 'bg-red-100 text-[#c3242a] dark:bg-red-900/30 dark:text-red-300',
            self::STATUS_SHIPPED => 'bg-[#c3242a]/90 text-white dark:bg-[#c3242a] dark:text-white',
            self::STATUS_COMPLETED => 'bg-[#a01e24] text-white dark:bg-[#a01e24] dark:text-white',
            default => 'bg-red-50 text-[#c3242a] dark:bg-red-900/20 dark:text-red-300',
        };
    }

    public function requiresSupplierAttention(): bool
    {
        return in_array($this->status, [
            self::STATUS_NEW,
            self::STATUS_AWAITING_CONFIRMATION,
        ], true);
    }

    public static function statusLabels(): array
    {
        return OrderStatus::labelsMap();
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status]
            ?? self::fallbackStatusLabels()[$this->status]
            ?? $this->status;
    }

    public function statusDescription(): string
    {
        return OrderStatus::descriptionsMap()[$this->status]
            ?? self::fallbackStatusDescriptions()[$this->status]
            ?? '';
    }

    public function requiresBuyerAttention(): bool
    {
        if ($this->isDistributorPurchase() && $this->status === self::STATUS_NEW) {
            return true;
        }

        return in_array($this->status, [
            self::STATUS_NEEDS_APPROVAL,
            self::STATUS_SHIPPED,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_REJECTED, self::STATUS_COMPLETED], true);
    }

    public function isPaused(): bool
    {
        return $this->paused_at !== null;
    }

    /**
     * Момент последней смены статуса (или создания заказа).
     */
    public function lastActivityAt(): ?Carbon
    {
        return $this->status_changed_at ?? $this->ordered_at ?? $this->created_at;
    }

    public function isStuck(?int $pendingHours = null): bool
    {
        if ($this->isTerminal() || $this->isPaused()) {
            return false;
        }

        $hours = $pendingHours ?? (int) SystemSetting::getActiveParsed('timings.order_pending_hours', 24);
        $lastActivity = $this->lastActivityAt();
        if ($lastActivity === null || $hours < 1) {
            return false;
        }

        return $lastActivity->lte(now()->subHours($hours));
    }

    /**
     * Полные календарные дни с последней смены статуса (или даты заказа).
     */
    public function daysWithoutAction(): int
    {
        $lastActivity = $this->lastActivityAt();
        if ($lastActivity === null) {
            return 0;
        }

        return max(0, (int) $lastActivity->copy()->startOfDay()->diffInDays(now()->startOfDay()));
    }

    public function problemFlagLabel(string $flag): string
    {
        $label = self::problemFlagLabels()[$flag] ?? $flag;

        if ($flag !== 'stuck') {
            return $label;
        }

        $days = max(1, $this->daysWithoutAction());

        return $label.' · '.$days.' '.self::russianDaysWord($days);
    }

    private static function russianDaysWord(int $days): string
    {
        $mod100 = $days % 100;
        $mod10 = $days % 10;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return 'дней';
        }

        return match ($mod10) {
            1 => 'день',
            2, 3, 4 => 'дня',
            default => 'дней',
        };
    }

    public function isRejectedWithoutReason(): bool
    {
        if ($this->status !== self::STATUS_REJECTED) {
            return false;
        }

        return ! filled(trim((string) $this->rejection_reason));
    }

    /**
     * @return list<string>
     */
    public function problemFlags(?int $pendingHours = null): array
    {
        $flags = [];

        if ($this->isStuck($pendingHours)) {
            $flags[] = 'stuck';
        }
        if ($this->has_active_claim) {
            $flags[] = 'active_claim';
        }
        if ($this->has_integration_error) {
            $flags[] = 'integration_error';
        }
        if ($this->isRejectedWithoutReason()) {
            $flags[] = 'rejected_without_reason';
        }
        if ($this->isPaused()) {
            $flags[] = 'paused';
        }

        return $flags;
    }

    public function hasProblems(?int $pendingHours = null): bool
    {
        return $this->problemFlags($pendingHours) !== [];
    }

    /**
     * @return array<string, string>
     */
    public static function problemFlagLabels(): array
    {
        return [
            'stuck' => 'Без движения',
            'active_claim' => 'Активная претензия',
            'integration_error' => 'Ошибка интеграции',
            'rejected_without_reason' => 'Отклонён без причины',
            'paused' => 'Приостановлен',
        ];
    }

    public function scopeStuck(Builder $query, ?int $pendingHours = null): Builder
    {
        $hours = $pendingHours ?? (int) SystemSetting::getActiveParsed('timings.order_pending_hours', 24);
        $threshold = now()->subHours(max(1, $hours));

        return $query
            ->whereNull('paused_at')
            ->whereNotIn('status', [self::STATUS_REJECTED, self::STATUS_COMPLETED])
            ->whereRaw('COALESCE(status_changed_at, ordered_at, created_at) <= ?', [$threshold]);
    }

    public function scopeProblematic(Builder $query, ?int $pendingHours = null): Builder
    {
        $hours = $pendingHours ?? (int) SystemSetting::getActiveParsed('timings.order_pending_hours', 24);
        $threshold = now()->subHours(max(1, $hours));

        return $query->where(function (Builder $inner) use ($threshold): void {
            $inner->whereNotNull('paused_at')
                ->orWhere('has_active_claim', true)
                ->orWhere('has_integration_error', true)
                ->orWhere(function (Builder $rejected): void {
                    $rejected->where('status', self::STATUS_REJECTED)
                        ->where(function (Builder $reason): void {
                            $reason->whereNull('rejection_reason')
                                ->orWhere('rejection_reason', '');
                        });
                })
                ->orWhere(function (Builder $stuck) use ($threshold): void {
                    $stuck->whereNull('paused_at')
                        ->whereNotIn('status', [self::STATUS_REJECTED, self::STATUS_COMPLETED])
                        ->whereRaw('COALESCE(status_changed_at, ordered_at, created_at) <= ?', [$threshold]);
                });
        });
    }

    public function distributorProfile(): BelongsTo
    {
        return $this->belongsTo(DistributorProfile::class);
    }

    public function responsibleContact(): BelongsTo
    {
        return $this->belongsTo(DistributorContact::class, 'responsible_contact_id');
    }

    public function manufacturerResponsibleContact(): BelongsTo
    {
        return $this->belongsTo(ManufacturerContact::class, 'manufacturer_responsible_contact_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(PlatformOrderClaim::class)->orderByDesc('id');
    }

    public function manufacturerProfile(): BelongsTo
    {
        return $this->belongsTo(ManufacturerProfile::class);
    }

    public function isOverdueUnconfirmed(?int $hours = 24): bool
    {
        if (! in_array($this->status, [self::STATUS_NEW, self::STATUS_AWAITING_CONFIRMATION], true)) {
            return false;
        }

        $from = $this->ordered_at ?? $this->created_at;
        if ($from === null) {
            return false;
        }

        return $from->lte(now()->subHours(max(1, $hours)));
    }

    public function endCompanyProfile(): BelongsTo
    {
        return $this->belongsTo(EndCompanyProfile::class);
    }

    public function deliveryMethod(): BelongsTo
    {
        return $this->belongsTo(DeliveryMethod::class);
    }

    public function transportCompany(): BelongsTo
    {
        return $this->belongsTo(TransportCompany::class);
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(EndCompanyDeliveryAddress::class, 'end_company_delivery_address_id');
    }

    public function distributorWarehouse(): BelongsTo
    {
        return $this->belongsTo(DistributorWarehouse::class);
    }

    public function isDistributorPurchase(): bool
    {
        return $this->order_channel === self::CHANNEL_DISTRIBUTOR_MANUFACTURER;
    }

    public function isEndCompanySale(): bool
    {
        return $this->order_channel === self::CHANNEL_END_COMPANY_DISTRIBUTOR
            || ($this->order_channel === null && $this->end_company_profile_id !== null);
    }

    public function buyerDisplayName(): string
    {
        if ($this->isDistributorPurchase()) {
            return $this->distributorProfile?->displayName() ?? 'Дистрибьютор';
        }

        return $this->endCompanyProfile?->displayName() ?? 'Покупатель';
    }

    public function sellerDisplayName(): string
    {
        if ($this->isDistributorPurchase()) {
            return $this->manufacturerProfile?->displayName() ?? 'Производитель';
        }

        return $this->distributorProfile?->displayName() ?? 'Поставщик';
    }

    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by_user_id');
    }

    public function pausedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paused_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlatformOrderItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(PlatformOrderStatusLog::class)->orderByDesc('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PlatformOrderDocument::class)->orderByDesc('id');
    }
}
