<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\DistributorWarehouse;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\PlatformOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStock;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CurrentRoleService;
use Database\Seeders\DeliveryMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DistributorPurchasesCabinetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(DeliveryMethodSeeder::class);
    }

    public function test_distributor_can_add_to_purchase_cart_checkout_and_submit(): void
    {
        Notification::fake();
        [$distUser, $product, $manufacturerId] = $this->seedPartnerCatalog();
        $this->actAsDistributor($distUser);

        $this->actingAs($distUser)
            ->post(route('distributor.purchases.cart.items.store'), [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('distributor_purchase_cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $method = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();

        $this->actingAs($distUser)
            ->post(route('distributor.purchases.checkout.store', $manufacturerId), [
                'delivery_method_id' => $method->id,
            ])
            ->assertRedirect();

        $order = PlatformOrder::query()
            ->where('order_channel', PlatformOrder::CHANNEL_DISTRIBUTOR_MANUFACTURER)
            ->first();

        $this->assertNotNull($order);
        $this->assertSame(PlatformOrder::STATUS_NEW, $order->status);
        $this->assertNull($order->end_company_profile_id);
        $this->assertSame((int) $manufacturerId, (int) $order->manufacturer_profile_id);

        $this->actingAs($distUser)
            ->get(route('distributor.purchases.index'))
            ->assertOk()
            ->assertSee('Мои покупки')
            ->assertSee($order->order_number);

        $this->actingAs($distUser)
            ->post(route('distributor.purchases.submit', $order))
            ->assertRedirect(route('distributor.purchases.show', $order));

        $this->assertSame(PlatformOrder::STATUS_AWAITING_CONFIRMATION, $order->fresh()->status);
    }

    public function test_sales_orders_list_excludes_purchases(): void
    {
        Notification::fake();
        [$distUser, $product, $manufacturerId] = $this->seedPartnerCatalog();
        $this->actAsDistributor($distUser);

        $method = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();
        $this->actingAs($distUser)
            ->post(route('distributor.purchases.cart.items.store'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);
        $this->actingAs($distUser)
            ->post(route('distributor.purchases.checkout.store', $manufacturerId), [
                'delivery_method_id' => $method->id,
            ]);

        $order = PlatformOrder::query()->where('order_channel', PlatformOrder::CHANNEL_DISTRIBUTOR_MANUFACTURER)->firstOrFail();

        $this->actingAs($distUser)
            ->get(route('distributor.orders.index'))
            ->assertOk()
            ->assertDontSee($order->order_number);

        $this->actingAs($distUser)
            ->get(route('distributor.orders.show', $order))
            ->assertForbidden();
    }

    public function test_manufacturer_sees_purchase_only_after_submit(): void
    {
        Notification::fake();
        [$distUser, $product, $manufacturerId, $mfrUser] = $this->seedPartnerCatalog();
        $this->actAsDistributor($distUser);

        $method = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();
        $this->actingAs($distUser)
            ->post(route('distributor.purchases.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($distUser)
            ->post(route('distributor.purchases.checkout.store', $manufacturerId), [
                'delivery_method_id' => $method->id,
            ]);

        $order = PlatformOrder::query()->where('order_channel', PlatformOrder::CHANNEL_DISTRIBUTOR_MANUFACTURER)->firstOrFail();

        app(CurrentRoleService::class)->set($mfrUser, $mfrUser->roles->first()->id);
        $this->actingAs($mfrUser)
            ->get(route('manufacturer.orders.index'))
            ->assertOk()
            ->assertDontSee($order->order_number);

        $this->actAsDistributor($distUser);
        $this->actingAs($distUser)->post(route('distributor.purchases.submit', $order));

        app(CurrentRoleService::class)->set($mfrUser, $mfrUser->roles->first()->id);
        $this->actingAs($mfrUser)
            ->get(route('manufacturer.orders.index'))
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_buyer_can_edit_items_before_confirm(): void
    {
        Notification::fake();
        [$distUser, $product, $manufacturerId] = $this->seedPartnerCatalog();
        $this->actAsDistributor($distUser);

        $method = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();
        $this->actingAs($distUser)
            ->post(route('distributor.purchases.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2]);
        $this->actingAs($distUser)
            ->post(route('distributor.purchases.checkout.store', $manufacturerId), [
                'delivery_method_id' => $method->id,
            ]);

        $order = PlatformOrder::query()->where('order_channel', PlatformOrder::CHANNEL_DISTRIBUTOR_MANUFACTURER)->firstOrFail();
        $item = $order->items()->firstOrFail();

        $this->actingAs($distUser)
            ->put(route('distributor.purchases.items.update', [$order, $item]), ['quantity' => 5])
            ->assertRedirect();

        $this->assertSame(5, (int) $item->fresh()->quantity);
        $this->assertEquals(5 * (float) $item->unit_price, (float) $order->fresh()->total_amount);
    }

    /**
     * @return array{0: User, 1: Product, 2: int, 3: User}
     */
    private function seedPartnerCatalog(): array
    {
        $region = Region::factory()->create(['name' => 'Москва']);

        $mfrUser = User::factory()->create();
        $mfrRole = Role::query()->where('slug', Role::SLUG_MANUFACTURER)->firstOrFail();
        $mfrUser->roles()->attach($mfrRole->id);
        $manufacturer = $mfrUser->getOrCreateManufacturerProfile();
        $manufacturer->update(['full_name' => 'Завод Покупок', 'short_name' => 'ЗП']);
        $manufacturer->regions()->sync([$region->id]);

        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
            'name' => 'Товар для закупки',
            'base_price' => 1500,
            'min_order_quantity' => 1,
        ]);

        $warehouse = Warehouse::query()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'name' => 'Склад ЗП',
            'address' => 'Москва',
            'region_id' => $region->id,
            'type' => Warehouse::TYPE_MAIN,
            'is_active' => true,
        ]);

        ProductStock::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'reserved' => 0,
            'stock_updated_at' => now(),
        ]);

        $product->availableRegions()->sync([$region->id]);

        $distUser = User::factory()->create();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        $distUser->roles()->attach($distRole->id, ['company_region' => $region->name]);
        $distProfile = $distUser->getOrCreateDistributorProfile();
        $distProfile->update(['full_name' => 'Дистрибьютор Покупок', 'short_name' => 'ДП']);
        $distProfile->regions()->sync([$region->id => ['is_primary' => true]]);

        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'distributor_profile_id' => $distProfile->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);

        DistributorWarehouse::query()->create([
            'distributor_profile_id' => $distProfile->id,
            'name' => 'Склад ДП',
            'address' => 'Москва',
            'region_id' => $region->id,
            'type' => DistributorWarehouse::TYPE_MAIN,
            'is_active' => true,
        ]);

        return [$distUser, $product, (int) $manufacturer->id, $mfrUser];
    }

    private function actAsDistributor(User $user): void
    {
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
    }
}
