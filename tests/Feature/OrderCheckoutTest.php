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
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\CurrentRoleService;
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
        $cart->add($user, $offerA->id, 2);
        $cart->add($user, $offerB->id, 1);

        $deliveryMethod = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();

        $response = $this->actingAs($user)
            ->post(route('buyer.checkout.store', $offerA->distributor_profile_id), [
                'delivery_method_id' => $deliveryMethod->id,
                'end_company_delivery_address_id' => $address->id,
                'buyer_comment' => 'Упаковать на паллету',
            ]);

        $order = PlatformOrder::query()->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('buyer.orders.show', $order));

        $this->assertSame(PlatformOrder::STATUS_NEW, $order->status);
        $this->assertSame('Упаковать на паллету', $order->buyer_comment);
        $this->assertSame(1, PlatformOrderItem::query()->count());
        $this->assertSame(2, (int) PlatformOrderItem::query()->first()->quantity);
        $this->assertSame(5000.0, (float) $order->total_amount);

        $this->assertDatabaseMissing('cart_items', ['distributor_product_id' => $offerA->id]);
        $this->assertDatabaseHas('cart_items', ['distributor_product_id' => $offerB->id, 'quantity' => 1]);
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
            ->assertSee('Оформить заказ');
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
            ->assertJsonStructure(['groups', 'totals', 'has_blocking_warnings', 'refreshed_at']);
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
}
