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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderCheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CatalogRegionService $regionService,
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
    public function prepare(User $user, int $distributorProfileId): array
    {
        $group = $this->requireGroup($user, $distributorProfileId);

        if ($group['has_blocking_warnings']) {
            throw ValidationException::withMessages([
                'cart' => 'В корзине есть критичные ошибки. Исправьте количество или удалите проблемные позиции.',
            ]);
        }

        $distributor = DistributorProfile::query()->findOrFail($distributorProfileId);
        $profile = $this->cartService->resolveProfile($user);
        $addresses = $profile->deliveryAddresses()->with(['region', 'contact'])->orderByDesc('is_default')->orderBy('name')->get();
        $priceNotices = $this->priceChangeNotices($group);
        $itemWarnings = $this->itemWarnings($group);

        return [
            'group' => $group,
            'distributor' => $distributor,
            'delivery_methods' => $this->availableDeliveryMethods($distributor),
            'transport_companies' => $this->availableTransportCompanies($distributor),
            'delivery_addresses' => $addresses,
            'default_address_id' => $profile->defaultDeliveryAddress()?->id,
            'delivery_notes' => $distributor->delivery_notes,
            'price_change_notices' => $priceNotices,
            'item_warnings' => $itemWarnings,
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
     *     delivery_vehicle_type?: ?string
     * }  $data
     */
    public function place(User $user, int $distributorProfileId, array $data): PlatformOrder
    {
        $prepared = $this->prepare($user, $distributorProfileId);
        $group = $prepared['group'];
        $distributor = $prepared['distributor'];

        $deliveryMethod = $this->resolveDeliveryMethod($prepared['delivery_methods'], (int) $data['delivery_method_id']);
        $address = $this->resolveAddress(
            $user,
            $deliveryMethod,
            $data['end_company_delivery_address_id'] ?? null,
        );
        $transportCompany = $this->resolveTransportCompany(
            $prepared['transport_companies'],
            $deliveryMethod,
            isset($data['transport_company_id']) ? (int) $data['transport_company_id'] : null,
        );
        $deliveryExtras = $this->resolveDeliveryExtras($deliveryMethod, $data);

        $profile = $this->cartService->resolveProfile($user);

        $order = DB::transaction(function () use (
            $user,
            $profile,
            $distributor,
            $group,
            $deliveryMethod,
            $address,
            $transportCompany,
            $data,
            $deliveryExtras,
            $distributorProfileId,
        ): PlatformOrder {
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
                'delivery_method_id' => $deliveryMethod->id,
                'transport_company_id' => $transportCompany?->id,
                'end_company_delivery_address_id' => $address?->id,
                'buyer_comment' => filled($data['buyer_comment'] ?? null) ? trim((string) $data['buyer_comment']) : null,
                'delivery_date' => $deliveryExtras['delivery_date'],
                'delivery_time_from' => $deliveryExtras['delivery_time_from'],
                'delivery_time_to' => $deliveryExtras['delivery_time_to'],
                'delivery_vehicle_type' => $deliveryExtras['delivery_vehicle_type'],
                'total_amount' => $group['subtotal'],
                'status' => PlatformOrder::STATUS_NEW,
                'ordered_at' => now(),
            ]);

            foreach ($group['items'] as $item) {
                PlatformOrderItem::query()->create([
                    'platform_order_id' => $order->id,
                    'distributor_product_id' => $item['distributor_product_id'],
                    'product_id' => $item['product_id'],
                    'name' => $item['name'],
                    'sku' => $item['sku'],
                    'manufacturer_name' => $item['manufacturer_name'],
                    'quantity' => $item['quantity'],
                    'pack_quantity' => $item['pack_quantity'] ?? null,
                    'min_order_quantity' => $item['min_order_quantity'] ?? null,
                    'unit_price' => $item['unit_price'],
                    'list_unit_price' => $item['list_unit_price'] ?? null,
                    'line_total' => $item['line_total'],
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

            $this->cartService->clearGroup($user, $distributorProfileId);

            return $order;
        });

        return $order->load(['items', 'deliveryMethod', 'transportCompany', 'deliveryAddress', 'distributorProfile']);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireGroup(User $user, int $distributorProfileId): array
    {
        $view = $this->cartService->view($user);
        $group = $view['groups']->first(
            fn (array $g): bool => (int) $g['distributor_profile_id'] === $distributorProfileId
        );

        if ($group === null || collect($group['items'])->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'В корзине нет товаров выбранного поставщика.',
            ]);
        }

        return $group;
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

        if ($address === null) {
            throw ValidationException::withMessages([
                'end_company_delivery_address_id' => 'Адрес получения не найден.',
            ]);
        }

        $regionId = $this->regionService->resolveRegionId($user);
        if ($regionId !== null && $address->region_id !== null && (int) $address->region_id !== $regionId) {
            throw ValidationException::withMessages([
                'end_company_delivery_address_id' => 'Адрес не соответствует выбранному региону каталога.',
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
