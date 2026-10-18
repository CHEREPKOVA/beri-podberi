<?php

namespace App\Services\Order;

use App\Models\DeliveryMethod;
use App\Models\DistributorProfile;
use App\Models\EndCompanyDeliveryAddress;
use App\Models\PlatformOrder;
use App\Models\PlatformOrderItem;
use App\Models\PlatformOrderStatusLog;
use App\Models\TransportCompany;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Catalog\CatalogRegionService;
use App\Services\EndCompanyStaffAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderCheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CatalogRegionService $regionService,
        private readonly EndCompanyStaffAccess $staffAccess,
    ) {}

    /**
     * @return array{
     *     group: array<string, mixed>,
     *     distributor: DistributorProfile,
     *     delivery_methods: Collection<int, DeliveryMethod>,
     *     transport_companies: Collection<int, TransportCompany>,
     *     delivery_addresses: Collection<int, EndCompanyDeliveryAddress>,
     *     default_address_id: ?int,
     *     delivery_notes: ?string,
     *     price_change_notices: list<string>,
     *     item_warnings: list<array{name: string, warnings: list<array<string, mixed>>}>
     * }
     */
    public function prepare(User $user, int $distributorProfileId, ?string $groupKey = null): array
    {
        $group = $this->requireGroup($user, $distributorProfileId, $groupKey);

        if ($group['has_blocking_warnings']) {
            throw ValidationException::withMessages([
                'cart' => 'В корзине есть критичные ошибки. Исправьте количество или удалите проблемные позиции.',
            ]);
        }

        $distributor = DistributorProfile::query()->findOrFail($distributorProfileId);

        return $this->decorateGroup($user, $group, $distributor);
    }

    /**
     * @return array{
     *     sections: Collection<int, array<string, mixed>>,
     *     blocked_items: Collection<int, array<string, mixed>>,
     *     totals: array<string, mixed>
     * }
     */
    public function prepareAll(User $user): array
    {
        $view = $this->cartService->view($user);

        if ($view['groups']->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => $view['blocked_items']->isNotEmpty()
                    ? 'Нет позиций, доступных к оформлению. Исправьте ошибки в корзине.'
                    : 'Корзина пуста.',
            ]);
        }

        $distributorIds = $view['groups']->pluck('distributor_profile_id')->unique()->filter()->all();
        $distributors = DistributorProfile::query()->whereIn('id', $distributorIds)->get()->keyBy('id');

        $sections = $view['groups']->map(function (array $group) use ($distributors, $user): array {
            $distributor = $distributors->get((int) $group['distributor_profile_id']);
            if ($distributor === null) {
                throw ValidationException::withMessages([
                    'cart' => 'Поставщик одной из групп больше недоступен.',
                ]);
            }

            return $this->decorateGroup($user, $group, $distributor);
        })->values();

        return [
            'sections' => $sections,
            'blocked_items' => $view['blocked_items'],
            'totals' => $view['totals'],
        ];
    }

    /**
     * @param  array{
     *     delivery_method_id: int,
     *     end_company_delivery_address_id?: ?int,
     *     transport_company_id?: ?int,
     *     buyer_comment?: ?string,
     *     delivery_date?: ?string,
     *     delivery_time_from?: ?string,
     *     delivery_time_to?: ?string,
     *     delivery_vehicle_type?: ?string,
     *     group_key?: ?string
     * }  $data
     */
    public function place(User $user, int $distributorProfileId, array $data): PlatformOrder
    {
        $prepared = $this->prepare($user, $distributorProfileId, $data['group_key'] ?? null);
        $resolved = $this->resolveSectionPlacement($user, $prepared, $data);

        $order = DB::transaction(function () use ($user, $resolved): PlatformOrder {
            $order = $this->persistOrder($user, $resolved);
            $this->cartService->clearItems(
                $user,
                collect($resolved['group']['items'])->pluck('id')->all(),
            );

            return $order;
        });

        return $order->load(['items', 'deliveryMethod', 'transportCompany', 'deliveryAddress', 'distributorProfile']);
    }

    /**
     * @param  list<array<string, mixed>>  $ordersData
     * @return Collection<int, PlatformOrder>
     */
    public function placeAll(User $user, array $ordersData): Collection
    {
        $prepared = $this->prepareAll($user);
        $sectionsByKey = $prepared['sections']->keyBy(
            fn (array $section): string => (string) $section['group']['group_key']
        );

        $submitted = collect($ordersData)->values();
        $submittedKeys = $submitted
            ->map(fn (array $row): string => (string) ($row['group_key'] ?? ''))
            ->filter()
            ->sort()
            ->values();
        $expectedKeys = $sectionsByKey->keys()->map(fn ($key): string => (string) $key)->sort()->values();

        if ($submittedKeys->all() !== $expectedKeys->all()) {
            throw ValidationException::withMessages([
                'orders' => 'Состав корзины изменился. Обновите страницу оформления.',
            ]);
        }

        $errors = [];
        $resolved = [];

        foreach ($submitted as $index => $data) {
            $section = $sectionsByKey->get((string) $data['group_key']);
            if ($section === null) {
                $errors['orders.'.$index.'.group_key'] = ['Группа заказа не найдена.'];

                continue;
            }

            try {
                $resolved[] = $this->resolveSectionPlacement($user, $section, $data);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    $errors['orders.'.$index.'.'.$field] = $messages;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $orders = DB::transaction(function () use ($user, $resolved): Collection {
            $created = collect();
            $itemIds = [];

            foreach ($resolved as $row) {
                $created->push($this->persistOrder($user, $row));
                foreach ($row['group']['items'] as $item) {
                    $itemIds[] = (int) $item['id'];
                }
            }

            $this->cartService->clearItems($user, $itemIds);

            return $created;
        });

        return $orders->map(
            fn (PlatformOrder $order): PlatformOrder => $order->load([
                'items',
                'deliveryMethod',
                'transportCompany',
                'deliveryAddress',
                'distributorProfile',
            ])
        );
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    private function decorateGroup(User $user, array $group, DistributorProfile $distributor): array
    {
        $groupRegionId = $this->groupRegionId($group);
        $addresses = $this->staffAccess->visibleAddresses($user);

        if ($groupRegionId !== null) {
            $addresses = $addresses
                ->filter(fn (EndCompanyDeliveryAddress $address): bool => $address->region_id === null
                    || (int) $address->region_id === $groupRegionId)
                ->values();
        }

        $defaultAddressId = $addresses->count() === 1
            ? $addresses->first()?->id
            : ($addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id);

        return [
            'group' => $group,
            'distributor' => $distributor,
            'delivery_methods' => $this->availableDeliveryMethods($distributor),
            'transport_companies' => $this->availableTransportCompanies($distributor),
            'delivery_addresses' => $addresses,
            'default_address_id' => $defaultAddressId,
            'delivery_notes' => $distributor->delivery_notes,
            'price_change_notices' => $this->priceChangeNotices($group),
            'item_warnings' => $this->itemWarnings($group),
        ];
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveSectionPlacement(User $user, array $section, array $data): array
    {
        $deliveryMethod = $this->resolveDeliveryMethod(
            $section['delivery_methods'],
            (int) $data['delivery_method_id'],
        );
        $address = $this->resolveAddress(
            $user,
            $deliveryMethod,
            $data['end_company_delivery_address_id'] ?? null,
            $this->groupRegionId($section['group']),
        );
        $transportCompany = $this->resolveTransportCompany(
            $section['transport_companies'],
            $deliveryMethod,
            isset($data['transport_company_id']) ? (int) $data['transport_company_id'] : null,
        );

        return [
            'group' => $section['group'],
            'distributor' => $section['distributor'],
            'delivery_method' => $deliveryMethod,
            'address' => $address,
            'transport_company' => $transportCompany,
            'delivery_extras' => $this->resolveDeliveryExtras($deliveryMethod, $data),
            'buyer_comment' => filled($data['buyer_comment'] ?? null) ? trim((string) $data['buyer_comment']) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $resolved
     */
    private function persistOrder(User $user, array $resolved): PlatformOrder
    {
        $group = $resolved['group'];
        $distributor = $resolved['distributor'];
        $deliveryExtras = $resolved['delivery_extras'];
        $profile = $this->cartService->resolveProfile($user);

        $manufacturerIds = collect($group['items'])
            ->pluck('manufacturer_profile_id')
            ->filter()
            ->unique()
            ->values();

        $order = PlatformOrder::query()->create([
            'order_number' => $this->generateOrderNumber(),
            'source' => PlatformOrder::SOURCE_LK,
            'order_channel' => PlatformOrder::CHANNEL_END_COMPANY_DISTRIBUTOR,
            'distributor_profile_id' => $distributor->id,
            'manufacturer_profile_id' => $manufacturerIds->count() === 1 ? (int) $manufacturerIds->first() : null,
            'end_company_profile_id' => $profile->id,
            'created_by_user_id' => $user->id,
            'delivery_method_id' => $resolved['delivery_method']->id,
            'transport_company_id' => $resolved['transport_company']?->id,
            'end_company_delivery_address_id' => $resolved['address']?->id,
            'distributor_warehouse_id' => $this->sharedWarehouseId($group),
            'buyer_comment' => $resolved['buyer_comment'],
            'delivery_date' => $deliveryExtras['delivery_date'],
            'delivery_time_from' => $deliveryExtras['delivery_time_from'],
            'delivery_time_to' => $deliveryExtras['delivery_time_to'],
            'delivery_vehicle_type' => $deliveryExtras['delivery_vehicle_type'],
            'total_amount' => $group['subtotal'],
            'discount_amount' => $group['discount_amount'] ?? 0,
            'discount_label' => collect($group['items'])->pluck('discount_label')->filter()->unique()->implode(', ') ?: null,
            'distributor_discount_set_id' => $this->primaryDistributorDiscountSetId($group),
            'status' => PlatformOrder::STATUS_NEW,
            'ordered_at' => now(),
        ]);

        foreach ($group['items'] as $item) {
            PlatformOrderItem::query()->create([
                'platform_order_id' => $order->id,
                'distributor_product_id' => $item['distributor_product_id'],
                'product_id' => $item['product_id'],
                'distributor_warehouse_id' => $item['distributor_warehouse_id'] ?? null,
                'name' => $item['name'],
                'sku' => $item['sku'],
                'manufacturer_name' => $item['manufacturer_name'],
                'quantity' => $item['quantity'],
                'pack_quantity' => $item['pack_quantity'] ?? null,
                'min_order_quantity' => $item['min_order_quantity'] ?? null,
                'unit_price' => $item['unit_price'],
                'list_unit_price' => $item['list_unit_price'] ?? null,
                'line_total' => $item['line_total'],
                'distributor_discount_set_id' => $item['distributor_discount_set_id'] ?? null,
                'discount_label' => $item['discount_label'] ?? null,
            ]);
        }

        PlatformOrderStatusLog::query()->create([
            'platform_order_id' => $order->id,
            'from_status' => null,
            'to_status' => PlatformOrder::STATUS_NEW,
            'action' => OrderStatusWorkflowService::ACTION_CREATE,
            'comment' => 'Заказ создан покупателем',
            'performed_by_user_id' => $user->id,
            'meta' => ['actor' => OrderStatusWorkflowService::ACTOR_BUYER],
        ]);

        try {
            app(OrderCorrespondenceService::class)->postOrderCreated($order);
        } catch (\Throwable) {
            // Системное сообщение не блокирует оформление.
        }

        app(OrderCreatedNotifier::class)->notifySupplier($order, $user);

        return $order;
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function primaryDistributorDiscountSetId(array $group): ?int
    {
        $id = collect($group['items'] ?? [])
            ->pluck('distributor_discount_set_id')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        return $id ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function sharedWarehouseId(array $group): ?int
    {
        $ids = collect($group['items'] ?? [])
            ->pluck('distributor_warehouse_id')
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        return $ids->count() === 1 ? $ids->first() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireGroup(User $user, int $distributorProfileId, ?string $groupKey = null): array
    {
        $view = $this->cartService->view($user);
        $groups = $view['groups']->filter(
            fn (array $g): bool => (int) $g['distributor_profile_id'] === $distributorProfileId
        )->values();

        $group = null;
        if (filled($groupKey)) {
            $group = $groups->first(fn (array $g): bool => ($g['group_key'] ?? null) === $groupKey);
        } elseif ($groups->count() === 1) {
            $group = $groups->first();
        } elseif ($groups->count() > 1) {
            throw ValidationException::withMessages([
                'cart' => 'У этого поставщика несколько заказов. Оформите их вместе из корзины.',
            ]);
        }

        if ($group === null || collect($group['items'])->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'В корзине нет товаров выбранного поставщика.',
            ]);
        }

        return $group;
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function groupRegionId(array $group): ?int
    {
        $regionId = $group['region_id'] ?? null;

        return $regionId !== null && (int) $regionId > 0 ? (int) $regionId : null;
    }

    /**
     * @return Collection<int, DeliveryMethod>
     */
    private function availableDeliveryMethods(DistributorProfile $distributor): Collection
    {
        $configured = $distributor->deliveryMethods()
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
    private function availableTransportCompanies(DistributorProfile $distributor): Collection
    {
        $configured = $distributor->transportCompanies()
            ->where('transport_companies.is_active', true)
            ->wherePivot('is_active', true)
            ->orderBy('transport_companies.name')
            ->get();

        if ($configured->isNotEmpty()) {
            return $configured;
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
                'delivery_method_id' => 'Выбранный способ доставки недоступен у поставщика.',
            ]);
        }

        return $method;
    }

    private function resolveAddress(
        User $user,
        DeliveryMethod $method,
        mixed $addressId,
        ?int $groupRegionId = null,
    ): ?EndCompanyDeliveryAddress {
        if ($method->slug !== DeliveryMethod::SLUG_TRANSPORT_COMPANY) {
            return null;
        }

        $profile = $this->cartService->resolveProfile($user);
        $hasAddresses = $profile->deliveryAddresses()->exists();

        if ($addressId === null || $addressId === '') {
            if ($hasAddresses) {
                throw ValidationException::withMessages([
                    'end_company_delivery_address_id' => 'Выберите склад / адрес получения.',
                ]);
            }

            return null;
        }

        $address = $profile->deliveryAddresses()->whereKey((int) $addressId)->first();

        if ($address === null || ! $this->staffAccess->canAccessAddress($user, (int) $address->id)) {
            throw ValidationException::withMessages([
                'end_company_delivery_address_id' => 'Адрес получения не найден.',
            ]);
        }

        $regionId = $groupRegionId ?? $this->regionService->resolveRegionId($user);
        if ($regionId !== null && $address->region_id !== null && (int) $address->region_id !== $regionId) {
            throw ValidationException::withMessages([
                'end_company_delivery_address_id' => 'Адрес не соответствует региону этого заказа.',
            ]);
        }

        return $address;
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
     * @return list<string>
     */
    private function priceChangeNotices(array $group): array
    {
        $notices = [];

        foreach ($group['items'] as $item) {
            foreach ($item['warnings'] as $warning) {
                if ($warning['code'] === CartService::WARNING_PRICE_CHANGED) {
                    $notices[] = $item['name'].': '.$warning['message'];
                }
            }
        }

        return $notices;
    }

    /**
     * @param  array<string, mixed>  $group
     * @return list<array{name: string, warnings: list<array<string, mixed>>}>
     */
    private function itemWarnings(array $group): array
    {
        $result = [];

        foreach ($group['items'] as $item) {
            $warnings = collect($item['warnings'] ?? [])
                ->reject(fn (array $warning): bool => $warning['code'] === CartService::WARNING_PRICE_CHANGED)
                ->values()
                ->all();

            if ($warnings !== []) {
                $result[] = [
                    'name' => $item['name'],
                    'warnings' => $warnings,
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     delivery_date: ?string,
     *     delivery_time_from: ?string,
     *     delivery_time_to: ?string,
     *     delivery_vehicle_type: ?string
     * }
     */
    private function resolveDeliveryExtras(DeliveryMethod $method, array $data): array
    {
        $extras = [
            'delivery_date' => filled($data['delivery_date'] ?? null) ? (string) $data['delivery_date'] : null,
            'delivery_time_from' => filled($data['delivery_time_from'] ?? null) ? (string) $data['delivery_time_from'] : null,
            'delivery_time_to' => filled($data['delivery_time_to'] ?? null) ? (string) $data['delivery_time_to'] : null,
            'delivery_vehicle_type' => filled($data['delivery_vehicle_type'] ?? null)
                ? trim((string) $data['delivery_vehicle_type'])
                : null,
        ];

        if ($method->slug === DeliveryMethod::SLUG_OWN_TRANSPORT && $extras['delivery_date'] === null) {
            throw ValidationException::withMessages([
                'delivery_date' => 'Укажите желаемую дату доставки.',
            ]);
        }

        if (
            $extras['delivery_time_from'] !== null
            && $extras['delivery_time_to'] !== null
            && $extras['delivery_time_from'] >= $extras['delivery_time_to']
        ) {
            throw ValidationException::withMessages([
                'delivery_time_to' => 'Время окончания должно быть позже времени начала.',
            ]);
        }

        return $extras;
    }

    private function generateOrderNumber(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $number = 'BP-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
            if (! PlatformOrder::query()->where('order_number', $number)->exists()) {
                return $number;
            }
        }

        return 'BP-'.now()->format('ymdHis').'-'.Str::upper(Str::random(4));
    }
}
