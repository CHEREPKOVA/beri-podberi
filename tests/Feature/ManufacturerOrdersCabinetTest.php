<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\DistributorProduct;
use App\Models\DistributorProductStock;
use App\Models\DistributorWarehouse;
use App\Models\EndCompanyDeliveryAddress;
use App\Models\ManufacturerContact;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\PlatformOrder;
use App\Models\PlatformOrderClaim;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\CurrentRoleService;
use Database\Seeders\DeliveryMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ManufacturerOrdersCabinetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(DeliveryMethodSeeder::class);
    }

    public function test_manufacturer_can_index_and_see_order(): void
    {
        Notification::fake();
        [$mfrUser, $order] = $this->seedOrder();
        $this->actAsManufacturer($mfrUser);

        $this->actingAs($mfrUser)
            ->get(route('manufacturer.orders.index'))
            ->assertOk()
            ->assertSee('Заказы')
            ->assertSee($order->order_number);

        $this->actingAs($mfrUser)
            ->get(route('manufacturer.orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Доступные действия')
            ->assertSee('Добавить товар');
    }

    public function test_filter_attention(): void
    {
        Notification::fake();
        [$mfrUser, $order] = $this->seedOrder();
        $this->actAsManufacturer($mfrUser);

        $this->actingAs($mfrUser)
            ->get(route('manufacturer.orders.index', ['attention' => 1]))
            ->assertOk()
            ->assertSee($order->order_number);

        $order->update(['status' => PlatformOrder::STATUS_COMPLETED]);

        $this->actingAs($mfrUser)
            ->get(route('manufacturer.orders.index', ['attention' => 1]))
            ->assertOk()
            ->assertDontSee($order->order_number);
    }

    public function test_export_returns_csv(): void
    {
        Notification::fake();
        [$mfrUser, $order] = $this->seedOrder();
        $this->actAsManufacturer($mfrUser);

        $response = $this->actingAs($mfrUser)
            ->get(route('manufacturer.orders.export', ['format' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString($order->order_number, $content);
        $this->assertStringContainsString('Номер', $content);
    }

    public function test_confirm_order(): void
    {
        Notification::fake();
        [$mfrUser, $order] = $this->seedOrder();
        $this->actAsManufacturer($mfrUser);

        $this->actingAs($mfrUser)
            ->post(route('manufacturer.orders.confirm', $order))
            ->assertRedirect();

        $this->assertSame(PlatformOrder::STATUS_CONFIRMED, $order->fresh()->status);
    }

    public function test_assign_responsible(): void
    {
        Notification::fake();
        [$mfrUser, $order] = $this->seedOrder();
        $this->actAsManufacturer($mfrUser);

        $contact = ManufacturerContact::query()->create([
            'manufacturer_profile_id' => $mfrUser->manufacturerProfile->id,
            'full_name' => 'Пётр Менеджер',
            'position' => 'Менеджер',
            'email' => 'mfr-manager@example.com',
            'phone' => '+79990003344',
            'is_primary' => false,
        ]);

        $this->actingAs($mfrUser)
            ->post(route('manufacturer.orders.assign_responsible', $order), [
                'manufacturer_responsible_contact_id' => $contact->id,
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame($contact->id, (int) $order->manufacturer_responsible_contact_id);
        $this->assertDatabaseHas('platform_order_status_logs', [
            'platform_order_id' => $order->id,
            'action' => 'assign_responsible',
        ]);
    }

    public function test_create_claim(): void
    {
        Notification::fake();
        [$mfrUser, $order] = $this->seedOrder();
        $this->actAsManufacturer($mfrUser);

        $this->actingAs($mfrUser)
            ->post(route('manufacturer.orders.claims.store', $order), [
                'reason' => PlatformOrderClaim::REASON_DELAY,
                'description' => 'Срыв сроков поставки по позиции',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertTrue($order->has_active_claim);
        $this->assertDatabaseHas('platform_order_claims', [
            'platform_order_id' => $order->id,
            'reason' => PlatformOrderClaim::REASON_DELAY,
            'creator_role' => 'manufacturer',
        ]);
        $this->assertDatabaseHas('platform_order_status_logs', [
            'platform_order_id' => $order->id,
            'action' => 'create_claim',
        ]);
    }

    public function test_print_ok(): void
    {
        Notification::fake();
        [$mfrUser, $order] = $this->seedOrder();
        $this->actAsManufacturer($mfrUser);

        $this->actingAs($mfrUser)
            ->get(route('manufacturer.orders.print', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Состав заказа');
    }

    public function test_export_history(): void
    {
        Notification::fake();
        [$mfrUser, $order] = $this->seedOrder();
        $this->actAsManufacturer($mfrUser);

        $this->actingAs($mfrUser)
            ->post(route('manufacturer.orders.confirm', $order))
            ->assertRedirect();

        $response = $this->actingAs($mfrUser)
            ->get(route('manufacturer.orders.history.export', $order));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Действие', $content);
        $this->assertStringContainsString('Подтверждение', $content);
    }

    public function test_adding_product_sends_order_for_approval(): void
    {
        Notification::fake();
        [$mfrUser, $order] = $this->seedOrder();
        $this->actAsManufacturer($mfrUser);

        $this->actingAs($mfrUser)
            ->get(route('manufacturer.orders.show', $order))
            ->assertOk()
            ->assertSee('Добавить товар')
            ->assertSee('Поиск по названию или артикулу')
            ->assertSee('Все категории');

        $existing = $order->items()->firstOrFail();
        $category = ProductCategory::query()->firstOrFail();

        $extraProduct = Product::factory()->create([
            'manufacturer_profile_id' => $mfrUser->manufacturerProfile->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
            'name' => 'Дополнительный товар',
            'sku' => 'SKU-M2',
        ]);

        $extraOffer = DistributorProduct::query()->create([
            'distributor_profile_id' => $order->distributor_profile_id,
            'source_product_id' => $extraProduct->id,
            'manufacturer_profile_id' => $mfrUser->manufacturerProfile->id,
            'product_category_id' => $category->id,
            'name' => $extraProduct->name,
            'internal_sku' => 'SKU-M2',
            'retail_price' => 1800,
            'status' => DistributorProduct::STATUS_ACTIVE,
            'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
            'min_order_quantity' => 1,
        ]);

        $this->actingAs($mfrUser)
            ->from(route('manufacturer.orders.show', $order))
            ->post(route('manufacturer.orders.send_for_approval', $order), [
                'items' => [
                    [
                        'id' => $existing->id,
                        'quantity' => $existing->quantity,
                        'unit_price' => $existing->unit_price,
                    ],
                    [
                        'distributor_product_id' => $extraOffer->id,
                        'quantity' => 1,
                        'unit_price' => 1800,
                    ],
                ],
                'comment' => 'Добавлен товар из номенклатуры',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(PlatformOrder::STATUS_NEEDS_APPROVAL, $order->status);
        $this->assertTrue($order->items()->where('distributor_product_id', $extraOffer->id)->exists());
    }

    private function actAsManufacturer(User $user): void
    {
        $role = Role::query()->where('slug', Role::SLUG_MANUFACTURER)->firstOrFail();
        app(CurrentRoleService::class)->set($user, $role->id);
    }

    /**
     * @return array{0: User, 1: PlatformOrder}
     */
    private function seedOrder(): array
    {
        $region = Region::factory()->create(['name' => 'Москва']);
        $mfrUser = User::factory()->create();
        $mfrRole = Role::query()->where('slug', Role::SLUG_MANUFACTURER)->firstOrFail();
        $mfrUser->roles()->attach($mfrRole->id);
        $manufacturer = $mfrUser->getOrCreateManufacturerProfile();
        $manufacturer->update(['full_name' => 'Завод Тест', 'short_name' => 'ЗТ']);

        $category = ProductCategory::factory()->create();

        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
            'name' => 'Товар производителя',
        ]);

        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $buyer = User::factory()->create();
        $buyer->roles()->attach($buyerRole->id, ['company_region' => $region->name]);
        $buyerProfile = $buyer->getOrCreateEndCompanyProfile();
        $buyerProfile->update(['full_name' => 'Покупатель Мфр', 'short_name' => 'ПМ']);

        $address = EndCompanyDeliveryAddress::query()->create([
            'end_company_profile_id' => $buyerProfile->id,
            'name' => 'Склад',
            'address' => 'Москва',
            'region_id' => $region->id,
            'is_default' => true,
        ]);

        $distUser = User::factory()->create();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        $distUser->roles()->attach($distRole->id);
        $distProfile = $distUser->getOrCreateDistributorProfile();
        $distProfile->update(['full_name' => 'Дистрибьютор Мфр', 'short_name' => 'ДМ']);
        $distProfile->regions()->sync([$region->id => ['is_primary' => true]]);

        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'distributor_profile_id' => $distProfile->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);

        $warehouse = DistributorWarehouse::query()->create([
            'distributor_profile_id' => $distProfile->id,
            'name' => 'Склад',
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
            'internal_sku' => 'SKU-M1',
            'retail_price' => 2500,
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

        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        app(CartService::class)->add($buyer, $offer->id, 2);

        $deliveryMethod = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();
        $this->actingAs($buyer)->post(route('buyer.checkout.store', $distProfile->id), [
            'delivery_method_id' => $deliveryMethod->id,
            'end_company_delivery_address_id' => $address->id,
        ]);

        $order = PlatformOrder::query()->firstOrFail();
        $this->assertSame($manufacturer->id, (int) $order->manufacturer_profile_id);

        return [$mfrUser->fresh(), $order];
    }
}
