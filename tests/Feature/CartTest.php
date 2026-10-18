<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DistributorProduct;
use App\Models\DistributorProductRegionalPrice;
use App\Models\DistributorProductStock;
use App\Models\DistributorWarehouse;
use App\Models\EndCompanyDeliveryAddress;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\ManufacturerProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Cart\CartSplitService;
use App\Services\Catalog\CatalogRegionService;
use App\Services\CurrentRoleService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_end_company_can_add_item_to_cart_and_see_grouped_list(): void
    {
        [$product, $offer, $user] = $this->seedPurchasableOffer();

        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $this->actingAs($user)
            ->post(route('buyer.cart.items.store'), [
                'distributor_product_id' => $offer->id,
                'quantity' => 2,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'distributor_product_id' => $offer->id,
            'quantity' => 2,
        ]);

        $this->actingAs($user)
            ->postJson(route('buyer.cart.items.store'), [
                'distributor_product_id' => $offer->id,
                'quantity' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('added_quantity', 3)
            ->assertJsonPath('cart_items_count', 1)
            ->assertJsonStructure(['success', 'message', 'product_name', 'added_quantity', 'cart_items_count']);

        $this->assertDatabaseHas('cart_items', [
            'distributor_product_id' => $offer->id,
            'quantity' => 5,
        ]);

        $this->actingAs($user)
            ->get(route('buyer.cart.index'))
            ->assertOk()
            ->assertSee('Суммы по будущим заказам')
            ->assertSee('Будет создано заказов');

        $this->actingAs($user)
            ->getJson(route('buyer.cart.live'))
            ->assertOk()
            ->assertJsonPath('totals.items_count', 1);
    }

    public function test_adding_same_offer_increases_quantity(): void
    {
        [, $offer, $user] = $this->seedPurchasableOffer();

        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        $cart = app(CartService::class);

        $cart->add($user, $offer->id, 1);
        $cart->add($user, $offer->id, 3);

        $this->assertSame(1, CartItem::query()->count());
        $this->assertSame(4, (int) CartItem::query()->first()->quantity);
    }

    public function test_cart_persists_for_company_profile(): void
    {
        [, $offer, $user] = $this->seedPurchasableOffer();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        app(CartService::class)->add($user, $offer->id, 1);

        $profile = $user->fresh()->endCompanyProfile;
        $this->assertNotNull($profile);
        $this->assertDatabaseHas('carts', [
            'end_company_profile_id' => $profile->id,
        ]);
        $this->assertSame(1, Cart::query()->where('end_company_profile_id', $profile->id)->count());
    }

    public function test_update_and_remove_cart_item(): void
    {
        [, $offer, $user] = $this->seedPurchasableOffer();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $item = app(CartService::class)->add($user, $offer->id, 2);

        $this->actingAs($user)
            ->put(route('buyer.cart.items.update', $item), ['quantity' => 5])
            ->assertRedirect(route('buyer.cart.index'));

        $this->assertSame(5, (int) $item->fresh()->quantity);

        $this->actingAs($user)
            ->delete(route('buyer.cart.items.destroy', $item))
            ->assertRedirect(route('buyer.cart.index'));

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_price_change_warning_appears_in_cart(): void
    {
        [, $offer, $user] = $this->seedPurchasableOffer();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        app(CartService::class)->add($user, $offer->id, 1);
        $offer->update(['retail_price' => 3000]);

        $view = app(CartService::class)->view($user);
        $warnings = $view['groups']->first()['items']->first()['warnings'];

        $this->assertTrue(collect($warnings)->contains(
            fn (array $w): bool => $w['code'] === CartService::WARNING_PRICE_CHANGED
        ));
    }

    public function test_distributor_cannot_access_cart(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        $user->roles()->attach($role->id);
        app(CurrentRoleService::class)->set($user, $role->id);

        $this->actingAs($user)
            ->get(route('buyer.cart.index'))
            ->assertForbidden();
    }

    public function test_product_card_shows_add_to_cart_for_end_company(): void
    {
        [$product, , $user] = $this->seedPurchasableOffer();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $this->actingAs($user)
            ->get(route('buyer.catalog.show', $product))
            ->assertOk()
            ->assertSee('В корзину')
            ->assertSee(route('buyer.cart.items.store'), false);
    }

    public function test_cannot_add_quantity_below_min_order(): void
    {
        [, $offer, $user] = $this->seedPurchasableOffer(['min_order_quantity' => 2]);

        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $this->actingAs($user)
            ->postJson(route('buyer.cart.items.store'), [
                'distributor_product_id' => $offer->id,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertDatabaseCount('cart_items', 0);

        $this->actingAs($user)
            ->postJson(route('buyer.cart.items.store'), [
                'distributor_product_id' => $offer->id,
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('added_quantity', 2);

        $this->assertDatabaseHas('cart_items', [
            'distributor_product_id' => $offer->id,
            'quantity' => 2,
        ]);
    }

    public function test_cannot_update_quantity_below_min_order(): void
    {
        [, $offer, $user] = $this->seedPurchasableOffer(['min_order_quantity' => 3]);

        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        $item = app(CartService::class)->add($user, $offer->id, 3);

        $this->actingAs($user)
            ->from(route('buyer.cart.index'))
            ->put(route('buyer.cart.items.update', $item), ['quantity' => 1])
            ->assertRedirect(route('buyer.cart.index'))
            ->assertSessionHasErrors('quantity');

        $this->assertSame(3, (int) $item->fresh()->quantity);
    }

    public function test_add_pins_catalog_region_and_fulfillment_warehouse(): void
    {
        [, $offer, $user] = $this->seedPurchasableOffer();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $region = Region::query()->where('name', 'Москва')->firstOrFail();
        $warehouseId = DistributorProductStock::query()
            ->where('distributor_product_id', $offer->id)
            ->value('distributor_warehouse_id');

        $item = app(CartService::class)->add($user, $offer->id, 2);

        $this->assertSame($region->id, (int) $item->region_id);
        $this->assertSame((int) $warehouseId, (int) $item->distributor_warehouse_id);
    }

    public function test_add_keeps_warehouse_that_still_covers_quantity(): void
    {
        [, $offer, $user] = $this->seedPurchasableOffer();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $item = app(CartService::class)->add($user, $offer->id, 2);
        $pinnedWarehouseId = (int) $item->distributor_warehouse_id;

        $largerWarehouse = DistributorWarehouse::query()->create([
            'distributor_profile_id' => $offer->distributor_profile_id,
            'name' => 'Большой склад',
            'address' => 'Москва, 2',
            'region_id' => $item->region_id,
            'type' => DistributorWarehouse::TYPE_REGIONAL,
            'is_active' => true,
        ]);
        DistributorProductStock::query()->create([
            'distributor_product_id' => $offer->id,
            'distributor_warehouse_id' => $largerWarehouse->id,
            'quantity' => 100,
            'reserved' => 0,
            'stock_updated_at' => now(),
        ]);

        $updated = app(CartService::class)->add($user, $offer->id, 3);

        $this->assertSame(1, CartItem::query()->count());
        $this->assertSame(5, (int) $updated->quantity);
        $this->assertSame($pinnedWarehouseId, (int) $updated->distributor_warehouse_id);
        $this->assertNotSame($largerWarehouse->id, (int) $updated->distributor_warehouse_id);
    }

    public function test_add_picks_warehouse_that_covers_quantity(): void
    {
        [, $offer, $user] = $this->seedPurchasableOffer();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $smallStock = DistributorProductStock::query()
            ->where('distributor_product_id', $offer->id)
            ->firstOrFail();
        $smallStock->update(['quantity' => 4, 'reserved' => 0]);

        $largeWarehouse = DistributorWarehouse::query()->create([
            'distributor_profile_id' => $offer->distributor_profile_id,
            'name' => 'Склад с остатком',
            'address' => 'Москва, 3',
            'region_id' => $smallStock->warehouse()->value('region_id')
                ?? Region::query()->where('name', 'Москва')->value('id'),
            'type' => DistributorWarehouse::TYPE_REGIONAL,
            'is_active' => true,
        ]);
        DistributorProductStock::query()->create([
            'distributor_product_id' => $offer->id,
            'distributor_warehouse_id' => $largeWarehouse->id,
            'quantity' => 15,
            'reserved' => 0,
            'stock_updated_at' => now(),
        ]);

        $item = app(CartService::class)->add($user, $offer->id, 10);

        $this->assertSame($largeWarehouse->id, (int) $item->distributor_warehouse_id);
    }

    public function test_same_offer_in_another_region_creates_separate_cart_item(): void
    {
        [, $offer, $user, $regionA] = $this->seedPurchasableOffer();
        $regionB = Region::factory()->create(['name' => 'Казань']);

        $profile = $user->endCompanyProfile;
        foreach ([$regionA, $regionB] as $i => $region) {
            EndCompanyDeliveryAddress::query()->create([
                'end_company_profile_id' => $profile->id,
                'name' => 'Филиал '.$region->name,
                'address' => $region->name.', ул. Тестовая 1',
                'region_id' => $region->id,
                'is_default' => $i === 0,
            ]);
        }
        $user->load('endCompanyProfile.deliveryAddresses');

        $offer->profile->regions()->sync([
            $regionA->id => ['is_primary' => true],
            $regionB->id => ['is_primary' => false],
        ]);

        $warehouseB = DistributorWarehouse::query()->create([
            'distributor_profile_id' => $offer->distributor_profile_id,
            'name' => 'Склад Казань',
            'address' => 'Казань',
            'region_id' => $regionB->id,
            'type' => DistributorWarehouse::TYPE_REGIONAL,
            'is_active' => true,
        ]);
        DistributorProductStock::query()->create([
            'distributor_product_id' => $offer->id,
            'distributor_warehouse_id' => $warehouseB->id,
            'quantity' => 12,
            'reserved' => 0,
            'stock_updated_at' => now(),
        ]);

        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        $cart = app(CartService::class);
        $regionService = app(CatalogRegionService::class);

        $itemA = $cart->add($user, $offer->id, 1);
        $this->assertSame($regionA->id, (int) $itemA->region_id);

        $this->assertTrue($regionService->setRegionId($user, $regionB->id));
        $itemB = $cart->add($user, $offer->id, 2);

        $this->assertSame(2, CartItem::query()->count());
        $this->assertSame($regionB->id, (int) $itemB->region_id);
        $this->assertSame($warehouseB->id, (int) $itemB->distributor_warehouse_id);
        $this->assertSame(1, (int) $itemA->fresh()->quantity);
        $this->assertSame(2, (int) $itemB->quantity);

        $view = $cart->view($user);
        $this->assertSame(2, $view['totals']['groups_count']);
        $this->assertSame(
            [$regionA->id, $regionB->id],
            $view['groups']->pluck('region_id')->sort()->values()->all()
        );
    }

    public function test_update_quantity_does_not_change_pinned_region(): void
    {
        [, $offer, $user, $regionA] = $this->seedPurchasableOffer();
        $regionB = Region::factory()->create(['name' => 'Казань']);
        $profile = $user->endCompanyProfile;
        foreach ([$regionA, $regionB] as $i => $region) {
            EndCompanyDeliveryAddress::query()->create([
                'end_company_profile_id' => $profile->id,
                'name' => 'Филиал '.$region->name,
                'address' => $region->name.', 1',
                'region_id' => $region->id,
                'is_default' => $i === 0,
            ]);
        }
        $user->load('endCompanyProfile.deliveryAddresses');

        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        $item = app(CartService::class)->add($user, $offer->id, 2);

        $this->assertTrue(app(CatalogRegionService::class)->setRegionId($user, $regionB->id));
        $updated = app(CartService::class)->updateQuantity($user, $item, 4);

        $this->assertSame($regionA->id, (int) $updated->region_id);
        $this->assertSame(4, (int) $updated->quantity);
    }

    public function test_same_supplier_different_warehouses_create_separate_groups(): void
    {
        [, $offerA, $user, $region] = $this->seedPurchasableOffer();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $offerB = $this->createSiblingOffer($offerA, $region, 'Кабель второй', warehouseName: 'Склад 2');
        $cart = app(CartService::class);
        $cart->add($user, $offerA->id, 1);
        $cart->add($user, $offerB->id, 1);

        $view = $cart->view($user);

        $this->assertSame(2, $view['totals']['groups_count']);
        $this->assertTrue($view['groups']->every(
            fn (array $group): bool => (int) $group['distributor_profile_id'] === (int) $offerA->distributor_profile_id
        ));
        $this->assertNotSame(
            $view['groups'][0]['distributor_warehouse_id'],
            $view['groups'][1]['distributor_warehouse_id'],
        );

        $this->actingAs($user)
            ->get(route('buyer.cart.index'))
            ->assertOk()
            ->assertSee('Склад отгрузки')
            ->assertSee('Условия цены')
            ->assertSee('Способы поставки', false);
    }

    public function test_regional_and_base_price_create_separate_groups(): void
    {
        [, $offerA, $user, $region] = $this->seedPurchasableOffer();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $warehouseId = (int) DistributorProductStock::query()
            ->where('distributor_product_id', $offerA->id)
            ->value('distributor_warehouse_id');
        $offerB = $this->createSiblingOffer($offerA, $region, 'Кабель без региональной цены', warehouseId: $warehouseId);

        DistributorProductRegionalPrice::query()->create([
            'distributor_product_id' => $offerA->id,
            'region_id' => $region->id,
            'price' => 1800,
        ]);

        $cart = app(CartService::class);
        $cart->add($user, $offerA->id, 1);
        $cart->add($user, $offerB->id, 1);

        $view = $cart->view($user);
        $scopes = $view['groups']->pluck('price_scope')->sort()->values();

        $this->assertSame(2, $view['totals']['groups_count']);
        $this->assertSame([CartSplitService::PRICE_SCOPE_BASE, CartSplitService::PRICE_SCOPE_REGIONAL], $scopes->all());
    }

    public function test_blocked_items_are_excluded_from_placeable_groups(): void
    {
        [, $offerA, $user, $region] = $this->seedPurchasableOffer();
        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $offerB = $this->createSiblingOffer($offerA, $region, 'Кабель проблемный');
        $cart = app(CartService::class);
        $cart->add($user, $offerA->id, 1);
        $blocked = $cart->add($user, $offerB->id, 1);
        $blocked->update(['quantity' => 1000]);

        $view = $cart->view($user);

        $this->assertSame(1, $view['totals']['groups_count']);
        $this->assertSame(1, $view['totals']['blocked_items_count']);
        $this->assertTrue($view['has_blocking_warnings']);
        $this->assertSame($offerA->id, $view['groups']->first()['items'][0]['distributor_product_id']);
        $this->assertSame($offerB->id, $view['blocked_items']->first()['distributor_product_id']);
    }

    /**
     * @param  array{min_order_quantity?: int}  $offerOverrides
     * @return array{0: Product, 1: DistributorProduct, 2: User, 3: Region}
     */
    private function seedPurchasableOffer(array $offerOverrides = []): array
    {
        $region = Region::factory()->create(['name' => 'Москва']);
        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();

        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
            'name' => 'Кабель ВВГ 3x2.5',
        ]);

        $role = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['company_region' => $region->name]);
        $user->getOrCreateEndCompanyProfile();

        $distUser = User::factory()->create();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        $distUser->roles()->attach($distRole->id);
        $profile = $distUser->getOrCreateDistributorProfile();
        $profile->update(['full_name' => 'ООО Поставщик Тест', 'short_name' => 'Поставщик Тест']);
        $profile->regions()->sync([$region->id => ['is_primary' => true]]);

        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'distributor_profile_id' => $profile->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);

        $warehouse = DistributorWarehouse::query()->create([
            'distributor_profile_id' => $profile->id,
            'name' => 'Склад',
            'address' => 'Москва',
            'region_id' => $region->id,
            'type' => DistributorWarehouse::TYPE_MAIN,
            'is_active' => true,
        ]);

        $offer = DistributorProduct::query()->create(array_merge([
            'distributor_profile_id' => $profile->id,
            'source_product_id' => $product->id,
            'manufacturer_profile_id' => $manufacturer->id,
            'product_category_id' => $category->id,
            'name' => $product->name,
            'internal_sku' => 'D-CART-1',
            'manufacturer_sku' => 'MFR-1',
            'retail_price' => 2500,
            'status' => DistributorProduct::STATUS_ACTIVE,
            'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
            'min_order_quantity' => 1,
        ], $offerOverrides));

        DistributorProductStock::query()->create([
            'distributor_product_id' => $offer->id,
            'distributor_warehouse_id' => $warehouse->id,
            'quantity' => 20,
            'reserved' => 0,
            'stock_updated_at' => now(),
        ]);

        return [$product, $offer, $user->fresh(), $region];
    }

    private function createSiblingOffer(
        DistributorProduct $existing,
        Region $region,
        string $name,
        ?int $warehouseId = null,
        string $warehouseName = 'Склад доп.',
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
                'name' => $warehouseName,
                'address' => $region->name,
                'region_id' => $region->id,
                'type' => DistributorWarehouse::TYPE_REGIONAL,
                'is_active' => true,
            ]);
            $warehouseId = $warehouse->id;
        }

        $offer = DistributorProduct::query()->create([
            'distributor_profile_id' => $existing->distributor_profile_id,
            'source_product_id' => $product->id,
            'manufacturer_profile_id' => $existing->manufacturer_profile_id,
            'product_category_id' => $existing->product_category_id,
            'name' => $name,
            'internal_sku' => 'D-CART-'.uniqid(),
            'manufacturer_sku' => 'MFR-'.uniqid(),
            'retail_price' => 2600,
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
}
