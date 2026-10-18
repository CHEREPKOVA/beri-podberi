<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\DeliveryMethod;
use App\Models\DistributorProduct;
use App\Models\DistributorProductRegionalPrice;
use App\Models\DistributorProductStock;
use App\Models\DistributorWarehouse;
use App\Models\EndCompanyDeliveryAddress;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\ManufacturerProfile;
use App\Models\PlatformOrder;
use App\Models\PlatformOrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\TransportCompany;
use App\Models\User;
use App\Notifications\OrderCreatedNotification;
use App\Services\Cart\CartService;
use App\Services\Cart\CartSplitService;
use App\Services\Catalog\CatalogRegionService;
use App\Services\CurrentRoleService;
use App\Services\Order\OrderCheckoutService;
use Database\Seeders\DeliveryMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(DeliveryMethodSeeder::class);
    }

    public function test_checkout_creates_order_clears_group_and_keeps_other_supplier(): void
    {
        [$user, $offerA, $offerB, $address] = $this->seedTwoSupplierCart();

        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        $cart = app(CartService::class);
        $itemA = $cart->add($user, $offerA->id, 2);
        $cart->add($user, $offerB->id, 1);
        $warehouseId = (int) $itemA->distributor_warehouse_id;

        $deliveryMethod = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();

        $response = $this->actingAs($user)
            ->post(route('buyer.checkout.store', $offerA->distributor_profile_id), [
                'delivery_method_id' => $deliveryMethod->id,
                'end_company_delivery_address_id' => $address->id,
                'buyer_comment' => 'Упаковать на паллету',
            ]);

        $order = PlatformOrder::query()->with('items')->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('buyer.orders.show', $order));

        $this->assertSame(PlatformOrder::STATUS_NEW, $order->status);
        $this->assertSame('Упаковать на паллету', $order->buyer_comment);
        $this->assertSame(1, PlatformOrderItem::query()->count());
        $this->assertSame(2, (int) PlatformOrderItem::query()->first()->quantity);
        $this->assertSame(5000.0, (float) $order->total_amount);

        $this->assertDatabaseMissing('cart_items', ['distributor_product_id' => $offerA->id]);
        $this->assertDatabaseHas('cart_items', ['distributor_product_id' => $offerB->id, 'quantity' => 1]);
        $this->assertSame((int) $warehouseId, (int) $order->distributor_warehouse_id);
        $this->assertSame((int) $warehouseId, (int) $order->items->first()->distributor_warehouse_id);
    }

    public function test_checkout_of_one_warehouse_group_keeps_other_group_of_same_supplier(): void
    {
        [$user, $offerA, $offerB, $address, $region] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $sibling = $this->createSiblingOfferFor($offerA, $region, 'Второй товар поставщика А');
        $cart = app(CartService::class);
        $cart->add($user, $offerA->id, 1);
        $cart->add($user, $sibling->id, 1);
        $cart->add($user, $offerB->id, 1);

        $view = $cart->view($user);
        $this->assertSame(3, $view['totals']['groups_count']);

        $groupA = $view['groups']->first(
            fn (array $group): bool => collect($group['items'])->contains(
                fn (array $item): bool => (int) $item['distributor_product_id'] === (int) $offerA->id
            )
        );
        $this->assertNotNull($groupA);

        $deliveryMethod = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();
        $this->actingAs($user)
            ->post(route('buyer.checkout.store', $offerA->distributor_profile_id), [
                'delivery_method_id' => $deliveryMethod->id,
                'end_company_delivery_address_id' => $address->id,
                'group_key' => $groupA['group_key'],
            ])
            ->assertRedirect();

        $this->assertSame(1, PlatformOrder::query()->count());
        $this->assertDatabaseMissing('cart_items', ['distributor_product_id' => $offerA->id]);
        $this->assertDatabaseHas('cart_items', ['distributor_product_id' => $sibling->id]);
        $this->assertDatabaseHas('cart_items', ['distributor_product_id' => $offerB->id]);
    }

    public function test_checkout_form_shows_composition_and_delivery_options(): void
    {
        [$user, $offerA, , $address] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        app(CartService::class)->add($user, $offerA->id, 1);

        $this->actingAs($user)
            ->get(route('buyer.checkout.create', $offerA->distributor_profile_id))
            ->assertOk()
            ->assertSee('Состав заказа')
            ->assertSee('Способ доставки')
            ->assertSee($address->name)
            ->assertSee('Самовывоз');
    }

    public function test_blocking_warnings_prevent_checkout(): void
    {
        [$user, $offerA, , $address] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $item = app(CartService::class)->add($user, $offerA->id, 1);
        $item->update(['quantity' => 1000]);

        $deliveryMethod = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();

        $this->actingAs($user)
            ->from(route('buyer.cart.index'))
            ->post(route('buyer.checkout.store', $offerA->distributor_profile_id), [
                'delivery_method_id' => $deliveryMethod->id,
                'end_company_delivery_address_id' => $address->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertSame(0, PlatformOrder::query()->count());
        $this->assertSame(1, CartItem::query()->count());
    }

    public function test_buyer_can_view_own_order(): void
    {
        [$user, $offerA, , $address] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        app(CartService::class)->add($user, $offerA->id, 1);

        $deliveryMethod = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();

        $this->actingAs($user)
            ->post(route('buyer.checkout.store', $offerA->distributor_profile_id), [
                'delivery_method_id' => $deliveryMethod->id,
                'end_company_delivery_address_id' => $address->id,
            ]);

        $order = PlatformOrder::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('buyer.orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Новый');

        $this->actingAs($user)
            ->get(route('buyer.orders.index'))
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_cart_checkout_button_links_to_form(): void
    {
        [$user, $offerA] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        app(CartService::class)->add($user, $offerA->id, 1);

        $this->actingAs($user)
            ->get(route('buyer.cart.index'))
            ->assertOk()
            ->assertSee('Суммы по будущим заказам')
            ->assertSee('Оформить заказ')
            ->assertSee(json_encode(route('buyer.checkout.batch')), false);
    }

    public function test_zero_stock_blocks_checkout_when_regional_stock_required(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'catalog.end_company_require_regional_stock'],
            ['group_key' => 'catalog', 'label' => 'stock', 'value' => '1', 'value_type' => 'boolean', 'sort_order' => 1, 'is_active' => true],
        );

        [$user, $offerA, , $address] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        DistributorProductStock::query()->where('distributor_product_id', $offerA->id)->update(['quantity' => 0, 'reserved' => 0]);
        app(CartService::class)->add($user, $offerA->id, 1);

        $deliveryMethod = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();

        $this->actingAs($user)
            ->from(route('buyer.cart.index'))
            ->get(route('buyer.checkout.create', $offerA->distributor_profile_id))
            ->assertRedirect(route('buyer.cart.index'))
            ->assertSessionHasErrors('cart');
    }

    public function test_distributor_can_view_incoming_order(): void
    {
        [$user, $offerA, , $address] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        app(CartService::class)->add($user, $offerA->id, 1);

        $deliveryMethod = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();
        $this->actingAs($user)->post(route('buyer.checkout.store', $offerA->distributor_profile_id), [
            'delivery_method_id' => $deliveryMethod->id,
            'end_company_delivery_address_id' => $address->id,
        ]);

        $order = PlatformOrder::query()->firstOrFail();
        $distUser = User::query()
            ->whereHas('distributorProfile', fn ($q) => $q->whereKey($offerA->distributor_profile_id))
            ->firstOrFail();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        app(CurrentRoleService::class)->set($distUser, $distRole->id);

        $this->actingAs($distUser)
            ->get(route('distributor.orders.index'))
            ->assertOk()
            ->assertSee($order->order_number);

        $this->actingAs($distUser)
            ->get(route('distributor.orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Новый');
    }

    public function test_discount_is_shown_when_regional_price_lower_than_base_retail(): void
    {
        [$user, $offerA, , , $region] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        DistributorProductRegionalPrice::query()->create([
            'distributor_product_id' => $offerA->id,
            'region_id' => $region->id,
            'price' => 2200,
        ]);

        app(CartService::class)->add($user, $offerA->id, 2);
        $view = app(CartService::class)->view($user);
        $item = $view['groups']->first()['items']->first();

        $this->assertTrue($item['has_discount']);
        $this->assertSame(2500.0, $item['list_unit_price']);
        $this->assertSame(2200.0, $item['unit_price']);
    }

    public function test_checkout_saves_delivery_extras(): void
    {
        [$user, $offerA, , $address] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        app(CartService::class)->add($user, $offerA->id, 1);

        $deliveryMethod = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_OWN_TRANSPORT)->firstOrFail();

        $this->actingAs($user)->post(route('buyer.checkout.store', $offerA->distributor_profile_id), [
            'delivery_method_id' => $deliveryMethod->id,
            'end_company_delivery_address_id' => $address->id,
            'delivery_date' => now()->addDay()->format('Y-m-d'),
            'delivery_time_from' => '10:00',
            'delivery_time_to' => '14:00',
            'delivery_vehicle_type' => 'Газель',
        ]);

        $order = PlatformOrder::query()->firstOrFail();
        $this->assertSame('Газель', $order->delivery_vehicle_type);
        $this->assertNotNull($order->delivery_date);
    }

    public function test_cart_live_endpoint_returns_warnings_payload(): void
    {
        [$user, $offerA] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        app(CartService::class)->add($user, $offerA->id, 1);

        $this->actingAs($user)
            ->getJson(route('buyer.cart.live'))
            ->assertOk()
            ->assertJsonStructure(['groups', 'blocked_items', 'totals', 'has_blocking_warnings', 'refreshed_at']);
    }

    public function test_batch_checkout_creates_orders_for_each_supplier(): void
    {
        [$user, $offerA, $offerB] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        $cart = app(CartService::class);
        $cart->add($user, $offerA->id, 2);
        $cart->add($user, $offerB->id, 1);

        $this->actingAs($user)
            ->get(route('buyer.checkout.batch'))
            ->assertOk()
            ->assertSee('Предварительный список заказов')
            ->assertSee('Создать заказы (2)');

        $this->actingAs($user)
            ->post(route('buyer.checkout.batch.store'), $this->selfPickupBatchPayload($user))
            ->assertRedirect(route('buyer.orders.index'));

        $this->assertSame(2, PlatformOrder::query()->count());
        $this->assertSame(0, CartItem::query()->count());
        $this->assertEqualsCanonicalizing(
            [$offerA->distributor_profile_id, $offerB->distributor_profile_id],
            PlatformOrder::query()->pluck('distributor_profile_id')->all(),
        );
    }

    public function test_batch_checkout_merges_same_supplier_warehouse_and_price(): void
    {
        [$user, $offerA, , , $region] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $warehouseId = (int) DistributorProductStock::query()
            ->where('distributor_product_id', $offerA->id)
            ->value('distributor_warehouse_id');
        $sibling = $this->createSiblingOfferFor($offerA, $region, 'Тот же склад', $warehouseId);

        $cart = app(CartService::class);
        $cart->add($user, $offerA->id, 1);
        $cart->add($user, $sibling->id, 1);

        $this->assertSame(1, $cart->view($user)['totals']['groups_count']);

        $this->actingAs($user)
            ->post(route('buyer.checkout.batch.store'), $this->selfPickupBatchPayload($user))
            ->assertRedirect();

        $this->assertSame(1, PlatformOrder::query()->count());
        $this->assertSame(2, PlatformOrderItem::query()->count());
        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_batch_checkout_splits_warehouses_of_same_supplier(): void
    {
        [$user, $offerA, $offerB, , $region] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        $sibling = $this->createSiblingOfferFor($offerA, $region, 'Другой склад А');

        $cart = app(CartService::class);
        $cart->add($user, $offerA->id, 1);
        $cart->add($user, $sibling->id, 1);
        $cart->add($user, $offerB->id, 1);

        $this->actingAs($user)
            ->post(route('buyer.checkout.batch.store'), $this->selfPickupBatchPayload($user))
            ->assertRedirect(route('buyer.orders.index'));

        $this->assertSame(3, PlatformOrder::query()->count());
        $this->assertSame(0, CartItem::query()->count());
        $this->assertSame(
            2,
            PlatformOrder::query()->where('distributor_profile_id', $offerA->distributor_profile_id)->count(),
        );
    }

    public function test_batch_checkout_splits_regional_and_base_price(): void
    {
        [$user, $offerA, , , $region] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $warehouseId = (int) DistributorProductStock::query()
            ->where('distributor_product_id', $offerA->id)
            ->value('distributor_warehouse_id');
        $sibling = $this->createSiblingOfferFor($offerA, $region, 'Базовая цена', $warehouseId);
        DistributorProductRegionalPrice::query()->create([
            'distributor_product_id' => $offerA->id,
            'region_id' => $region->id,
            'price' => 1800,
        ]);

        $cart = app(CartService::class);
        $cart->add($user, $offerA->id, 1);
        $cart->add($user, $sibling->id, 1);

        $view = $cart->view($user);
        $this->assertSame(2, $view['totals']['groups_count']);
        $this->assertEqualsCanonicalizing(
            [CartSplitService::PRICE_SCOPE_BASE, CartSplitService::PRICE_SCOPE_REGIONAL],
            $view['groups']->pluck('price_scope')->all(),
        );

        $this->actingAs($user)
            ->post(route('buyer.checkout.batch.store'), $this->selfPickupBatchPayload($user))
            ->assertRedirect(route('buyer.orders.index'));

        $this->assertSame(2, PlatformOrder::query()->count());
    }

    public function test_batch_checkout_binds_each_region_to_its_address(): void
    {
        [$user, $offerA, , $addressA, $regionA] = $this->seedTwoSupplierCart();
        $regionB = Region::factory()->create(['name' => 'Казань']);
        $profile = $user->endCompanyProfile;
        $addressB = EndCompanyDeliveryAddress::query()->create([
            'end_company_profile_id' => $profile->id,
            'name' => 'Склад Казань',
            'address' => 'Казань, 1',
            'region_id' => $regionB->id,
            'is_default' => false,
        ]);
        $user->load('endCompanyProfile.deliveryAddresses');

        $offerA->profile->regions()->sync([
            $regionA->id => ['is_primary' => true],
            $regionB->id => ['is_primary' => false],
        ]);
        $warehouseB = DistributorWarehouse::query()->create([
            'distributor_profile_id' => $offerA->distributor_profile_id,
            'name' => 'Склад Казань',
            'address' => 'Казань',
            'region_id' => $regionB->id,
            'type' => DistributorWarehouse::TYPE_REGIONAL,
            'is_active' => true,
        ]);
        DistributorProductStock::query()->create([
            'distributor_product_id' => $offerA->id,
            'distributor_warehouse_id' => $warehouseB->id,
            'quantity' => 12,
            'reserved' => 0,
            'stock_updated_at' => now(),
        ]);

        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        $cart = app(CartService::class);
        $regionService = app(CatalogRegionService::class);
        $cart->add($user, $offerA->id, 1);
        $this->assertTrue($regionService->setRegionId($user, $regionB->id));
        $cart->add($user, $offerA->id, 1);

        $transport = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_TRANSPORT_COMPANY)->firstOrFail();
        $tc = TransportCompany::query()->create([
            'name' => 'ТК Тест',
            'slug' => 'tc-test',
            'is_active' => true,
        ]);

        $payload = $this->selfPickupBatchPayload($user, function (array $row, array $section) use ($transport, $tc, $addressA, $addressB): array {
            $row['delivery_method_id'] = $transport->id;
            $row['transport_company_id'] = $tc->id;
            $row['end_company_delivery_address_id'] = (int) $section['group']['region_id'] === (int) $addressB->region_id
                ? $addressB->id
                : $addressA->id;

            return $row;
        });

        $this->actingAs($user)
            ->post(route('buyer.checkout.batch.store'), $payload)
            ->assertRedirect(route('buyer.orders.index'));

        $orders = PlatformOrder::query()->with('deliveryAddress')->get();
        $this->assertCount(2, $orders);
        $this->assertEqualsCanonicalizing(
            [$addressA->id, $addressB->id],
            $orders->pluck('end_company_delivery_address_id')->all(),
        );
    }

    public function test_batch_checkout_keeps_blocked_item_and_creates_valid_orders(): void
    {
        [$user, $offerA, $offerB] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        $cart = app(CartService::class);
        $cart->add($user, $offerA->id, 1);
        $blocked = $cart->add($user, $offerB->id, 1);
        $blocked->update(['quantity' => 1000]);

        $this->actingAs($user)
            ->post(route('buyer.checkout.batch.store'), $this->selfPickupBatchPayload($user))
            ->assertRedirect();

        $this->assertSame(1, PlatformOrder::query()->count());
        $this->assertSame($offerA->distributor_profile_id, PlatformOrder::query()->value('distributor_profile_id'));
        $this->assertDatabaseHas('cart_items', ['distributor_product_id' => $offerB->id]);
        $this->assertDatabaseMissing('cart_items', ['distributor_product_id' => $offerA->id]);
    }

    public function test_legacy_checkout_url_does_not_mix_suppliers(): void
    {
        [$user, $offerA, $offerB] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        app(CartService::class)->add($user, $offerA->id, 1);
        app(CartService::class)->add($user, $offerB->id, 1);

        $this->actingAs($user)
            ->get(route('buyer.checkout.create', $offerA->distributor_profile_id))
            ->assertRedirect(route('buyer.checkout.batch'));

        $deliveryMethod = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();
        $this->actingAs($user)
            ->post(route('buyer.checkout.store', $offerA->distributor_profile_id), [
                'delivery_method_id' => $deliveryMethod->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, PlatformOrder::query()->count());
        $this->assertSame($offerA->distributor_profile_id, (int) PlatformOrder::query()->value('distributor_profile_id'));
        $this->assertDatabaseHas('cart_items', ['distributor_product_id' => $offerB->id]);
    }

    public function test_batch_checkout_rolls_back_all_orders_when_one_section_invalid(): void
    {
        [$user, $offerA, $offerB] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        app(CartService::class)->add($user, $offerA->id, 1);
        app(CartService::class)->add($user, $offerB->id, 1);

        $ownTransport = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_OWN_TRANSPORT)->firstOrFail();
        $payload = $this->selfPickupBatchPayload($user, function (array $row, array $section, int $index) use ($ownTransport): array {
            if ($index === 1) {
                $row['delivery_method_id'] = $ownTransport->id;
                unset($row['delivery_date']);
            }

            return $row;
        });

        $this->actingAs($user)
            ->from(route('buyer.checkout.batch'))
            ->post(route('buyer.checkout.batch.store'), $payload)
            ->assertRedirect(route('buyer.checkout.batch'))
            ->assertSessionHasErrors();

        $this->assertSame(0, PlatformOrder::query()->count());
        $this->assertSame(2, CartItem::query()->count());
    }

    public function test_checkout_notifies_distributor_about_new_order(): void
    {
        [$user, $offerA, $offerB, $address] = $this->seedTwoSupplierCart();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        app(CartService::class)->add($user, $offerA->id, 1);

        $deliveryMethod = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();
        $this->actingAs($user)->post(route('buyer.checkout.store', $offerA->distributor_profile_id), [
            'delivery_method_id' => $deliveryMethod->id,
            'end_company_delivery_address_id' => $address->id,
        ])->assertRedirect();

        $order = PlatformOrder::query()->firstOrFail();
        $distUser = User::query()
            ->whereHas('distributorProfile', fn ($q) => $q->whereKey($offerA->distributor_profile_id))
            ->firstOrFail();
        $otherUser = User::query()
            ->whereHas('distributorProfile', fn ($q) => $q->whereKey($offerB->distributor_profile_id))
            ->firstOrFail();

        $notification = $distUser->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame(OrderCreatedNotification::class, $notification->type);
        $this->assertSame($order->id, $notification->data['order_id']);
        $this->assertSame(route('distributor.orders.show', $order), $notification->data['url']);
        $this->assertStringContainsString('Новый заказ №'.$order->order_number, (string) $notification->data['message']);
        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(0, $otherUser->notifications()->count());
    }

    /**
     * @return array{0: User, 1: DistributorProduct, 2: DistributorProduct, 3: EndCompanyDeliveryAddress, 4: Region}
     */
    private function seedTwoSupplierCart(): array
    {
        $region = Region::factory()->create(['name' => 'Москва']);
        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();

        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
            'name' => 'Товар для заказа',
        ]);

        $role = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['company_region' => $region->name]);
        $profile = $user->getOrCreateEndCompanyProfile();

        $address = EndCompanyDeliveryAddress::query()->create([
            'end_company_profile_id' => $profile->id,
            'name' => 'Склад ЦФО',
            'address' => 'Москва, ул. Тестовая 1',
            'region_id' => $region->id,
            'is_default' => true,
        ]);

        $offerA = $this->createOffer($manufacturer, $category, $product, $region, 'Поставщик А', 2500);
        $offerB = $this->createOffer($manufacturer, $category, $product, $region, 'Поставщик Б', 2700);

        return [$user->fresh(), $offerA, $offerB, $address, $region];
    }

    private function createOffer(
        ManufacturerProfile $manufacturer,
        ProductCategory $category,
        Product $product,
        Region $region,
        string $name,
        float $price,
    ): DistributorProduct {
        $distUser = User::factory()->create();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        $distUser->roles()->attach($distRole->id);
        $distProfile = $distUser->getOrCreateDistributorProfile();
        $distProfile->update(['full_name' => $name, 'short_name' => $name]);
        $distProfile->regions()->sync([$region->id => ['is_primary' => true]]);

        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'distributor_profile_id' => $distProfile->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);

        $warehouse = DistributorWarehouse::query()->create([
            'distributor_profile_id' => $distProfile->id,
            'name' => 'Склад '.$name,
            'address' => 'Москва',
            'region_id' => $region->id,
            'type' => DistributorWarehouse::TYPE_MAIN,
            'is_active' => true,
        ]);

        $offer = DistributorProduct::query()->create([
            'distributor_profile_id' => $distProfile->id,
            'source_product_id' => $product->id,
            'manufacturer_profile_id' => $manufacturer->id,
            'product_category_id' => $category->id,
            'name' => $product->name,
            'internal_sku' => 'SKU-'.$name,
            'retail_price' => $price,
            'status' => DistributorProduct::STATUS_ACTIVE,
            'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
            'min_order_quantity' => 1,
        ]);

        DistributorProductStock::query()->create([
            'distributor_product_id' => $offer->id,
            'distributor_warehouse_id' => $warehouse->id,
            'quantity' => 20,
            'reserved' => 0,
            'stock_updated_at' => now(),
        ]);

        return $offer;
    }

    private function createSiblingOfferFor(
        DistributorProduct $existing,
        Region $region,
        string $name,
        ?int $warehouseId = null,
    ): DistributorProduct {
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $existing->manufacturer_profile_id,
            'category_id' => $existing->product_category_id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
            'name' => $name,
        ]);

        if ($warehouseId === null) {
            $warehouse = DistributorWarehouse::query()->create([
                'distributor_profile_id' => $existing->distributor_profile_id,
                'name' => 'Склад '.$name,
                'address' => $region->name,
                'region_id' => $region->id,
                'type' => DistributorWarehouse::TYPE_REGIONAL,
                'is_active' => true,
            ]);
            $warehouseId = (int) $warehouse->id;
        }

        $offer = DistributorProduct::query()->create([
            'distributor_profile_id' => $existing->distributor_profile_id,
            'source_product_id' => $product->id,
            'manufacturer_profile_id' => $existing->manufacturer_profile_id,
            'product_category_id' => $existing->product_category_id,
            'name' => $name,
            'internal_sku' => 'SKU-'.$name,
            'retail_price' => 2550,
            'status' => DistributorProduct::STATUS_ACTIVE,
            'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
            'min_order_quantity' => 1,
        ]);

        DistributorProductStock::query()->create([
            'distributor_product_id' => $offer->id,
            'distributor_warehouse_id' => $warehouseId,
            'quantity' => 20,
            'reserved' => 0,
            'stock_updated_at' => now(),
        ]);

        return $offer;
    }

    /**
     * @param  callable(array<string, mixed>, array<string, mixed>, int): array<string, mixed>|null  $customize
     * @return array{orders: list<array<string, mixed>>}
     */
    private function selfPickupBatchPayload(User $user, ?callable $customize = null): array
    {
        $prepared = app(OrderCheckoutService::class)->prepareAll($user);
        $method = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();
        $orders = [];

        foreach ($prepared['sections'] as $index => $section) {
            $row = [
                'group_key' => $section['group']['group_key'],
                'delivery_method_id' => $method->id,
            ];
            if ($customize !== null) {
                $row = $customize($row, $section, $index);
            }
            $orders[] = $row;
        }

        return ['orders' => $orders];
    }
}
