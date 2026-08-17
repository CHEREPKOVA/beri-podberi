<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\DistributorContact;
use App\Models\DistributorProduct;
use App\Models\DistributorProductStock;
use App\Models\DistributorWarehouse;
use App\Models\EndCompanyDeliveryAddress;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\ManufacturerProfile;
use App\Models\PlatformOrder;
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

class DistributorOrdersCabinetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(DeliveryMethodSeeder::class);
    }

    public function test_index_filters_by_number_and_status(): void
    {
        Notification::fake();
        [, $distUser, $order] = $this->seedOrder();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        app(CurrentRoleService::class)->set($distUser, $distRole->id);

        $this->actingAs($distUser)
            ->get(route('distributor.orders.index', [
                'number' => substr($order->order_number, -4),
                'status' => PlatformOrder::STATUS_NEW,
            ]))
            ->assertOk()
            ->assertSee('Мои заказы')
            ->assertSee($order->order_number);

        $this->actingAs($distUser)
            ->get(route('distributor.orders.index', [
                'number' => 'NO-SUCH-ORDER',
            ]))
            ->assertOk()
            ->assertDontSee($order->order_number);

        $this->actingAs($distUser)
            ->get(route('distributor.orders.index', [
                'status' => PlatformOrder::STATUS_COMPLETED,
            ]))
            ->assertOk()
            ->assertDontSee($order->order_number);
    }

    public function test_sidebar_attention_count_ignores_confirmed_orders(): void
    {
        Notification::fake();
        [, $distUser, $order] = $this->seedOrder();

        $workflow = app(\App\Services\Order\OrderStatusWorkflowService::class);
        $profileId = (int) $distUser->distributorProfile->id;

        $this->assertSame(1, $workflow->countRequiringAttentionForDistributor($profileId));

        $order->update(['status' => PlatformOrder::STATUS_CONFIRMED]);
        $this->assertSame(0, $workflow->countRequiringAttentionForDistributor($profileId));

        $order->update(['status' => PlatformOrder::STATUS_AWAITING_CONFIRMATION]);
        $this->assertSame(1, $workflow->countRequiringAttentionForDistributor($profileId));
    }

    public function test_export_returns_csv(): void
    {
        Notification::fake();
        [, $distUser, $order] = $this->seedOrder();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        app(CurrentRoleService::class)->set($distUser, $distRole->id);

        $response = $this->actingAs($distUser)
            ->get(route('distributor.orders.export', ['format' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString($order->order_number, $content);
        $this->assertStringContainsString('Номер', $content);
    }

    public function test_mark_in_work(): void
    {
        Notification::fake();
        [, $distUser, $order] = $this->seedOrder();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        app(CurrentRoleService::class)->set($distUser, $distRole->id);

        $this->actingAs($distUser)
            ->post(route('distributor.orders.confirm', $order))
            ->assertRedirect();

        $this->actingAs($distUser)
            ->post(route('distributor.orders.mark_in_work', $order))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(PlatformOrder::STATUS_IN_WORK, $order->status);

        $this->actingAs($distUser)
            ->get(route('distributor.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Изменить заказ')
            ->assertSee('Готов к отгрузке');

        $item = $order->items()->firstOrFail();
        $this->actingAs($distUser)
            ->post(route('distributor.orders.send_for_approval', $order), [
                'items' => [
                    ['id' => $item->id, 'quantity' => 1, 'unit_price' => 1000],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors();
    }

    public function test_assign_responsible(): void
    {
        Notification::fake();
        [, $distUser, $order] = $this->seedOrder();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        app(CurrentRoleService::class)->set($distUser, $distRole->id);

        $contact = DistributorContact::query()->create([
            'distributor_profile_id' => $distUser->distributorProfile->id,
            'full_name' => 'Иван Менеджер',
            'position' => 'Менеджер',
            'email' => 'manager@example.com',
            'phone' => '+79990001122',
            'is_primary' => false,
        ]);

        $this->actingAs($distUser)
            ->post(route('distributor.orders.assign_responsible', $order), [
                'responsible_contact_id' => $contact->id,
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame($contact->id, (int) $order->responsible_contact_id);
        $this->assertDatabaseHas('platform_order_status_logs', [
            'platform_order_id' => $order->id,
            'action' => 'assign_responsible',
        ]);
    }

    public function test_print_ok(): void
    {
        Notification::fake();
        [, $distUser, $order] = $this->seedOrder();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        app(CurrentRoleService::class)->set($distUser, $distRole->id);

        $this->actingAs($distUser)
            ->get(route('distributor.orders.print', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Состав заказа');
    }

    public function test_adding_product_sends_order_for_approval(): void
    {
        Notification::fake();
        [, $distUser, $order] = $this->seedOrder();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        app(CurrentRoleService::class)->set($distUser, $distRole->id);

        $this->actingAs($distUser)
            ->get(route('distributor.orders.show', $order))
            ->assertOk()
            ->assertSee('Добавить товар')
            ->assertSee('Поиск по названию или артикулу')
            ->assertSee('Все категории');

        $existing = $order->items()->firstOrFail();
        $existingOffer = DistributorProduct::query()->findOrFail($existing->distributor_product_id);
        $category = ProductCategory::query()->firstOrFail();

        $extraProduct = Product::factory()->create([
            'manufacturer_profile_id' => $existingOffer->manufacturer_profile_id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
            'name' => 'Дополнительный товар дистрибьютора',
            'sku' => 'SKU-D-EXTRA',
        ]);

        $extraOffer = DistributorProduct::query()->create([
            'distributor_profile_id' => $order->distributor_profile_id,
            'source_product_id' => $extraProduct->id,
            'manufacturer_profile_id' => $extraProduct->manufacturer_profile_id,
            'product_category_id' => $category->id,
            'name' => $extraProduct->name,
            'internal_sku' => 'SKU-D-EXTRA',
            'retail_price' => 1800,
            'status' => DistributorProduct::STATUS_ACTIVE,
            'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
            'min_order_quantity' => 1,
        ]);

        $this->actingAs($distUser)
            ->from(route('distributor.orders.show', $order))
            ->post(route('distributor.orders.send_for_approval', $order), [
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

    public function test_send_for_approval_with_delete_item_keeps_at_least_one(): void
    {
        Notification::fake();
        [, $distUser, $order] = $this->seedOrder(withSecondItem: true);
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        app(CurrentRoleService::class)->set($distUser, $distRole->id);

        $items = $order->items()->orderBy('id')->get();
        $this->assertCount(2, $items);

        $keep = $items[0];
        $remove = $items[1];

        $this->actingAs($distUser)
            ->post(route('distributor.orders.send_for_approval', $order), [
                'items' => [
                    [
                        'id' => $keep->id,
                        'quantity' => $keep->quantity,
                        'unit_price' => $keep->unit_price,
                    ],
                    [
                        'id' => $remove->id,
                        'quantity' => $remove->quantity,
                        'unit_price' => $remove->unit_price,
                        'delete' => '1',
                        'reason' => 'Нет на складе',
                    ],
                ],
                'comment' => 'Убрали одну позицию',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(PlatformOrder::STATUS_NEEDS_APPROVAL, $order->status);
        $this->assertSame(1, $order->items()->count());
        $this->assertTrue($order->items()->whereKey($keep->id)->exists());
        $this->assertFalse($order->items()->whereKey($remove->id)->exists());
    }

    /**
     * @return array{0: User, 1: User, 2: PlatformOrder}
     */
    private function seedOrder(bool $withSecondItem = false): array
    {
        $region = Region::factory()->create(['name' => 'Москва']);
        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();

        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
            'name' => 'Товар дистрибьютора',
        ]);

        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $buyer = User::factory()->create();
        $buyer->roles()->attach($buyerRole->id, ['company_region' => $region->name]);
        $buyerProfile = $buyer->getOrCreateEndCompanyProfile();
        $buyerProfile->update(['full_name' => 'Покупатель Тест', 'short_name' => 'ПТ']);

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
        $distProfile->update(['full_name' => 'Поставщик Кабинет', 'short_name' => 'ПК']);
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
            'internal_sku' => 'SKU-D1',
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

        if ($withSecondItem) {
            $product2 = Product::factory()->create([
                'manufacturer_profile_id' => $manufacturer->id,
                'category_id' => $category->id,
                'show_in_catalog' => true,
                'status' => Product::STATUS_ACTIVE,
                'name' => 'Второй товар',
            ]);

            $offer2 = DistributorProduct::query()->create([
                'distributor_profile_id' => $distProfile->id,
                'source_product_id' => $product2->id,
                'manufacturer_profile_id' => $manufacturer->id,
                'product_category_id' => $category->id,
                'name' => $product2->name,
                'internal_sku' => 'SKU-D2',
                'retail_price' => 1000,
                'status' => DistributorProduct::STATUS_ACTIVE,
                'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
                'min_order_quantity' => 1,
            ]);

            DistributorProductStock::query()->create([
                'distributor_product_id' => $offer2->id,
                'distributor_warehouse_id' => $warehouse->id,
                'quantity' => 10,
                'reserved' => 0,
                'stock_updated_at' => now(),
            ]);

            app(CartService::class)->add($buyer, $offer2->id, 1);
        }

        $deliveryMethod = DeliveryMethod::query()->where('slug', DeliveryMethod::SLUG_SELF_PICKUP)->firstOrFail();
        $this->actingAs($buyer)->post(route('buyer.checkout.store', $distProfile->id), [
            'delivery_method_id' => $deliveryMethod->id,
            'end_company_delivery_address_id' => $address->id,
        ]);

        $order = PlatformOrder::query()->firstOrFail();

        return [$buyer->fresh(), $distUser->fresh(), $order];
    }
}
