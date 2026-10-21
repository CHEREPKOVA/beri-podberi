<?php

namespace App\Services\Order;

use App\Models\DeliveryMethod;
use App\Models\DistributorProfile;
use App\Models\DistributorWarehouse;
use App\Models\ManufacturerProfile;
use App\Models\ManufacturerPromoCode;
use App\Models\PlatformOrder;
use App\Models\PlatformOrderItem;
use App\Models\PlatformOrderStatusLog;
use App\Models\TransportCompany;
use App\Models\User;
use App\Services\Cart\DistributorPurchaseCartService;
use App\Services\Discount\ManufacturerPromoCodeService;
use App\Services\DistributorStaffAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DistributorPurchaseCheckoutService
{
    public function __construct(
        private readonly DistributorPurchaseCartService $cartService,
        private readonly DistributorStaffAccess $staffAccess,
        private readonly ManufacturerPromoCodeService $promoCodes,
    ) {}

    /**
     * @return array{
     *     group: array<string, mixed>,
     *     manufacturer: ManufacturerProfile,
     *     delivery_methods: Collection<int, DeliveryMethod>,
     *     transport_companies: Collection<int, TransportCompany>,
     *     warehouses: Collection<int, DistributorWarehouse>,
     *     default_warehouse_id: ?int,
     *     delivery_notes: ?string,
     *     item_warnings: list<array{name: string, warnings: list<array<string, mixed>>}>,
     *     applied_promo_code: ?string
     * }
     */
    public function prepare(User $user, int $manufacturerProfileId): array
    {
        $group = $this->requireGroup($user, $manufacturerProfileId);

        if ($group['has_blocking_warnings']) {
            throw ValidationException::withMessages([
                'cart' => 'В корзине есть критичные ошибки. Исправьте количество или удалите проблемные позиции.',
            ]);
        }

        $manufacturer = ManufacturerProfile::query()->findOrFail($manufacturerProfileId);
        $warehouses = $this->staffAccess->visibleWarehouses($user)->where('is_active', true)->values();

        return [
            'group' => $group,
            'manufacturer' => $manufacturer,
            'delivery_methods' => $this->availableDeliveryMethods($manufacturer),
            'transport_companies' => $this->availableTransportCompanies($manufacturer),
            'warehouses' => $warehouses,
            'default_warehouse_id' => $warehouses->count() === 1 ? $warehouses->first()?->id : null,
            'delivery_notes' => $manufacturer->delivery_notes ?? null,
            'item_warnings' => $this->itemWarnings($group),
            'applied_promo_code' => $this->currentPromoCode($user, $manufacturerProfileId),
        ];
    }

    public function applyPromoCode(User $user, int $manufacturerProfileId, ?string $promoCode): void
    {
        $this->requireGroup($user, $manufacturerProfileId);

        $previous = $this->currentPromoCode($user, $manufacturerProfileId);
        $this->persistPromoCode($user, $manufacturerProfileId, $promoCode);

        try {
            $this->requireGroup($user, $manufacturerProfileId);
        } catch (ValidationException $e) {
            $this->persistPromoCode($user, $manufacturerProfileId, $previous);
            throw $e;
        }
    }

    /**
     * @param  array{
     *     delivery_method_id: int,
     *     distributor_warehouse_id?: ?int,
     *     transport_company_id?: ?int,
     *     buyer_comment?: ?string,
     *     delivery_date?: ?string,
     *     promo_code?: ?string
     * }  $data
     */
    public function place(User $user, int $manufacturerProfileId, array $data): PlatformOrder
    {
        $prepared = $this->prepare($user, $manufacturerProfileId);
        $group = $prepared['group'];
        $manufacturer = $prepared['manufacturer'];
        $profile = $this->cartService->resolveProfile($user);

        $deliveryMethod = $this->resolveDeliveryMethod($prepared['delivery_methods'], (int) $data['delivery_method_id']);
        $warehouse = $this->resolveWarehouse($profile, $user, $data['distributor_warehouse_id'] ?? null);
        $transportCompany = $this->resolveTransportCompany(
            $prepared['transport_companies'],
            $deliveryMethod,
            isset($data['transport_company_id']) ? (int) $data['transport_company_id'] : null,
        );

        $responsibleId = isset($data['responsible_contact_id']) && $data['responsible_contact_id'] !== ''
            ? (int) $data['responsible_contact_id']
            : null;
        if ($responsibleId !== null) {
            $ownsContact = $profile->contacts()->whereKey($responsibleId)->exists();
            if (! $ownsContact) {
                throw ValidationException::withMessages([
                    'responsible_contact_id' => 'Выберите ответственного из списка контактов.',
                ]);
            }
        }

        $order = DB::transaction(function () use (
            $user,
            $profile,
            $manufacturer,
            $group,
            $deliveryMethod,
            $warehouse,
            $transportCompany,
            $data,
            $responsibleId,
            $manufacturerProfileId,
        ): PlatformOrder {
            $promoCode = filled($data['promo_code'] ?? null)
                ? mb_strtoupper(trim((string) $data['promo_code']))
                : $this->currentPromoCode($user, $manufacturerProfileId);
            if ($promoCode) {
                $this->persistPromoCode($user, $manufacturerProfileId, $promoCode);
                $group = $this->requireGroup($user, $manufacturerProfileId);
            }

            $discountAmount = (float) ($group['discount_amount'] ?? 0);
            $discountLabels = $group['discount_labels'] ?? [];
            $promoId = collect($group['items'])->pluck('promo_code_id')->filter()->first();
            $setId = collect($group['items'])->pluck('discount_set_id')->filter()->first();

            $order = PlatformOrder::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'source' => PlatformOrder::SOURCE_LK,
                'order_channel' => PlatformOrder::CHANNEL_DISTRIBUTOR_MANUFACTURER,
                'distributor_profile_id' => $profile->id,
                'manufacturer_profile_id' => $manufacturer->id,
                'end_company_profile_id' => null,
                'delivery_method_id' => $deliveryMethod->id,
                'transport_company_id' => $transportCompany?->id,
                'distributor_warehouse_id' => $warehouse?->id,
                'responsible_contact_id' => $responsibleId,
                'buyer_comment' => filled($data['buyer_comment'] ?? null) ? trim((string) $data['buyer_comment']) : null,
                'delivery_date' => filled($data['delivery_date'] ?? null) ? $data['delivery_date'] : null,
                'total_amount' => $group['subtotal'],
                'discount_amount' => $discountAmount,
                'discount_label' => $discountLabels !== [] ? implode(', ', $discountLabels) : null,
                'discount_set_id' => $setId,
                'promo_code_id' => $promoId,
                'promo_code' => $promoCode,
                'status' => PlatformOrder::STATUS_NEW,
                'ordered_at' => now(),
            ]);

            foreach ($group['items'] as $item) {
                PlatformOrderItem::query()->create([
                    'platform_order_id' => $order->id,
                    'distributor_product_id' => null,
                    'product_id' => $item['product_id'],
                    'name' => $item['name'],
                    'sku' => $item['sku'],
                    'manufacturer_name' => $item['manufacturer_name'],
                    'quantity' => $item['quantity'],
                    'pack_quantity' => $item['pack_quantity'] ?? null,
                    'min_order_quantity' => $item['min_order_quantity'] ?? null,
                    'unit_price' => $item['unit_price'],
                    'list_unit_price' => $item['list_unit_price'] ?? $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'discount_set_id' => $item['discount_set_id'] ?? null,
                    'discount_label' => $item['discount_label'] ?? null,
                ]);
            }

            if ($promoId) {
                $promo = ManufacturerPromoCode::query()->find($promoId);
                if ($promo) {
                    $this->promoCodes->recordUsage($promo, $user, $order, $discountAmount);
                }
                $this->persistPromoCode($user, $manufacturerProfileId, null);
            }

            PlatformOrderStatusLog::query()->create([
                'platform_order_id' => $order->id,
                'from_status' => null,
                'to_status' => PlatformOrder::STATUS_NEW,
                'action' => OrderStatusWorkflowService::ACTION_CREATE,
                'comment' => 'Заказ создан дистрибьютором (закупка у производителя)',
                'performed_by_user_id' => $user->id,
                'meta' => [
                    'actor' => OrderStatusWorkflowService::ACTOR_BUYER,
                    'channel' => PlatformOrder::CHANNEL_DISTRIBUTOR_MANUFACTURER,
                ],
            ]);

            try {
                app(OrderCorrespondenceService::class)->postOrderCreated($order);
            } catch (\Throwable) {
                // Переписка не должна блокировать оформление.
            }

            $this->cartService->clearGroup($user, $manufacturerProfileId);

            return $order;
        });

        return $order->load([
            'items',
            'deliveryMethod',
            'transportCompany',
            'distributorWarehouse',
            'manufacturerProfile',
            'distributorProfile',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireGroup(User $user, int $manufacturerProfileId): array
    {
        $view = $this->cartService->view($user);
        $group = $view['groups']->first(
            fn (array $g): bool => (int) $g['manufacturer_profile_id'] === $manufacturerProfileId
        );

        if ($group === null || collect($group['items'])->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'В корзине нет товаров выбранного производителя.',
            ]);
        }

        return $group;
    }

    private function currentPromoCode(User $user, int $manufacturerProfileId): ?string
    {
        $profile = $this->cartService->resolveProfile($user);
        $cart = $this->cartService->getOrCreateCart($profile);
        $codes = is_array($cart->promo_codes) ? $cart->promo_codes : [];
        $promo = $codes[(string) $manufacturerProfileId]
            ?? $codes[$manufacturerProfileId]
            ?? session('purchase_promo.'.$manufacturerProfileId);

        if (! is_string($promo)) {
            return null;
        }

        $promo = mb_strtoupper(trim($promo));

        return $promo !== '' ? $promo : null;
    }

    private function persistPromoCode(User $user, int $manufacturerProfileId, ?string $promoCode): void
    {
        $profile = $this->cartService->resolveProfile($user);
        $cart = $this->cartService->getOrCreateCart($profile);
        $codes = is_array($cart->promo_codes) ? $cart->promo_codes : [];
        $normalized = filled($promoCode) ? mb_strtoupper(trim((string) $promoCode)) : null;

        unset($codes[(string) $manufacturerProfileId], $codes[$manufacturerProfileId]);

        if ($normalized) {
            $codes[(string) $manufacturerProfileId] = $normalized;
            session(['purchase_promo.'.$manufacturerProfileId => $normalized]);
        } else {
            session()->forget('purchase_promo.'.$manufacturerProfileId);
        }

        $cart->promo_codes = $codes === [] ? null : $codes;
        $cart->save();
    }

    /**
     * @return Collection<int, DeliveryMethod>
     */
    private function availableDeliveryMethods(ManufacturerProfile $manufacturer): Collection
    {
        $configured = $manufacturer->deliveryMethods()
            ->where('delivery_methods.is_active', true)
            ->wherePivot('is_active', true)
            ->orderBy('delivery_methods.sort_order')
            ->get();

        if ($configured->isNotEmpty()) {
            return $configured;
        }

        return DeliveryMethod::query()->active()->orderBy('sort_order')->get();
    }

    /**
     * @return Collection<int, TransportCompany>
     */
    private function availableTransportCompanies(ManufacturerProfile $manufacturer): Collection
    {
        if (method_exists($manufacturer, 'transportCompanies')) {
            $configured = $manufacturer->transportCompanies()
                ->where('transport_companies.is_active', true)
                ->wherePivot('is_active', true)
                ->orderBy('transport_companies.name')
                ->get();

            if ($configured->isNotEmpty()) {
                return $configured;
            }
        }

        return TransportCompany::query()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @param  Collection<int, DeliveryMethod>  $methods
     */
    private function resolveDeliveryMethod(Collection $methods, int $deliveryMethodId): DeliveryMethod
    {
        $method = $methods->firstWhere('id', $deliveryMethodId);
        if ($method === null) {
            throw ValidationException::withMessages([
                'delivery_method_id' => 'Выбранный способ доставки недоступен у производителя.',
            ]);
        }

        return $method;
    }

    private function resolveWarehouse(DistributorProfile $profile, User $user, mixed $warehouseId): ?DistributorWarehouse
    {
        $allowed = $this->staffAccess->visibleWarehouses($user)->where('is_active', true)->values();

        if ($warehouseId === null || $warehouseId === '') {
            if ($allowed->count() === 1) {
                return $allowed->first();
            }

            if ($allowed->isEmpty()) {
                throw ValidationException::withMessages([
                    'distributor_warehouse_id' => 'Нет доступного склада для оформления заказа.',
                ]);
            }

            throw ValidationException::withMessages([
                'distributor_warehouse_id' => 'Выберите филиал / склад поставки.',
            ]);
        }

        $warehouse = $allowed->firstWhere('id', (int) $warehouseId);
        if ($warehouse === null) {
            throw ValidationException::withMessages([
                'distributor_warehouse_id' => 'Этот склад недоступен для вашего профиля.',
            ]);
        }

        return $warehouse;
    }

    /**
     * @param  Collection<int, TransportCompany>  $companies
     */
    private function resolveTransportCompany(
        Collection $companies,
        DeliveryMethod $method,
        ?int $transportCompanyId,
    ): ?TransportCompany {
        if ($method->slug !== DeliveryMethod::SLUG_TRANSPORT_COMPANY) {
            return null;
        }

        if ($transportCompanyId === null) {
            throw ValidationException::withMessages([
                'transport_company_id' => 'Выберите транспортную компанию.',
            ]);
        }

        $company = $companies->firstWhere('id', $transportCompanyId);
        if ($company === null) {
            throw ValidationException::withMessages([
                'transport_company_id' => 'Транспортная компания недоступна.',
            ]);
        }

        return $company;
    }

    /**
     * @param  array<string, mixed>  $group
     * @return list<array{name: string, warnings: list<array<string, mixed>>}>
     */
    private function itemWarnings(array $group): array
    {
        return collect($group['items'])
            ->filter(fn (array $item): bool => ($item['warnings'] ?? []) !== [])
            ->map(fn (array $item): array => [
                'name' => $item['name'],
                'warnings' => $item['warnings'],
            ])
            ->values()
            ->all();
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'PO-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (PlatformOrder::query()->where('order_number', $number)->exists());

        return $number;
    }
}
