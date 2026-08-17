<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DistributorProduct;
use App\Models\DistributorProductStock;
use App\Models\DistributorWarehouse;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\ManufacturerProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\Cart\CartService;
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

    /**
     * @param  array{min_order_quantity?: int}  $offerOverrides
     * @return array{0: Product, 1: DistributorProduct, 2: User}
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

        return [$product, $offer, $user->fresh()];
    }
}
