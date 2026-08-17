<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\DistributorProduct;
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
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\CurrentRoleService;
use Database\Seeders\DeliveryMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(DeliveryMethodSeeder::class);
    }

    public function test_full_happy_path_statuses(): void
    {
        Notification::fake();

        [$buyer, $distUser, $order] = $this->seedOrder();

        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();

        app(CurrentRoleService::class)->set($distUser, $distRole->id);
        $this->actingAs($distUser)
            ->get(route('distributor.orders.show', $order))
            ->assertOk();

        $order->refresh();
        $this->assertSame(PlatformOrder::STATUS_AWAITING_CONFIRMATION, $order->status);

        $this->actingAs($distUser)
            ->post(route('distributor.orders.confirm', $order))
            ->assertRedirect();

        $this->assertSame(PlatformOrder::STATUS_CONFIRMED, $order->fresh()->status);

        $this->actingAs($distUser)
            ->post(route('distributor.orders.mark_ready', $order))
            ->assertRedirect();

        $this->assertSame(PlatformOrder::STATUS_READY_TO_SHIP, $order->fresh()->status);

        $this->actingAs($distUser)
            ->post(route('distributor.orders.mark_shipped', $order), [
                'tracking_number' => 'TTN-123123',
                'shipping_from_warehouse' => 'Склад 1',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(PlatformOrder::STATUS_SHIPPED, $order->status);
        $this->assertSame('TTN-123123', $order->tracking_number);

        $this->actingAs($distUser)
            ->get(route('distributor.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Завершить заказ');

        $this->actingAs($distUser)
            ->post(route('distributor.orders.complete', $order))
            ->assertRedirect()
            ->assertSessionHasErrors();

        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        $this->actingAs($buyer)
            ->post(route('buyer.orders.confirm_receipt', $order))
            ->assertRedirect();

        $this->assertSame(PlatformOrder::STATUS_COMPLETED, $order->fresh()->status);
    }

    public function test_supplier_reject_requires_reason(): void
    {
        [, $distUser, $order] = $this->seedOrder();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        app(CurrentRoleService::class)->set($distUser, $distRole->id);

        $this->actingAs($distUser)->get(route('distributor.orders.show', $order));

        $this->actingAs($distUser)
            ->from(route('distributor.orders.show', $order))
            ->post(route('distributor.orders.reject', $order), [])
            ->assertRedirect()
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($distUser)
            ->post(route('distributor.orders.reject', $order), [
                'rejection_reason' => 'Нет товара на складе',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(PlatformOrder::STATUS_REJECTED, $order->status);
        $this->assertSame('Нет товара на складе', $order->rejection_reason);
    }

    public function test_needs_approval_flow(): void
    {
        [$buyer, $distUser, $order] = $this->seedOrder();
        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();

        app(CurrentRoleService::class)->set($distUser, $distRole->id);
        $this->actingAs($distUser)->get(route('distributor.orders.show', $order));

        $item = $order->items()->firstOrFail();

        $this->actingAs($distUser)
            ->post(route('distributor.orders.send_for_approval', $order), [
                'items' => [
                    ['id' => $item->id, 'quantity' => 1, 'unit_price' => 2000],
                ],
                'comment' => 'Скорректировали цену',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(PlatformOrder::STATUS_NEEDS_APPROVAL, $order->status);
        $this->assertSame(2000.0, (float) $order->items()->first()->unit_price);

        $workflow = app(\App\Services\Order\OrderStatusWorkflowService::class);
        $this->assertSame(
            array_search(PlatformOrder::STATUS_AWAITING_CONFIRMATION, PlatformOrder::progressStatuses(), true),
            $workflow->progressIndex($order),
        );
        $this->assertSame('Ожидает подтверждения', $order->statusLabel());

        $proposal = $workflow->latestApprovalProposal($order);
        $this->assertNotNull($proposal);
        $this->assertSame('Скорректировали цену', $proposal['supplier_comment']);
        $this->assertNotEmpty($proposal['changes']);
        $this->assertSame(2000.0, (float) $proposal['changes'][0]['new_unit_price']);

        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        $this->actingAs($buyer)
            ->get(route('buyer.orders.show', $order))
            ->assertOk()
            ->assertSee('Предложенные изменения')
            ->assertSee('Скорректировали цену')
            ->assertSee('Согласовать изменения');

        $this->actingAs($buyer)
            ->post(route('buyer.orders.approve_changes', $order))
            ->assertRedirect();

        $this->assertSame(PlatformOrder::STATUS_CONFIRMED, $order->fresh()->status);
    }

    public function test_buyer_reject_changes_returns_order_to_supplier(): void
    {
        [$buyer, $distUser, $order] = $this->seedOrder();
        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();

        app(CurrentRoleService::class)->set($distUser, $distRole->id);
        $item = $order->items()->firstOrFail();

        $this->actingAs($distUser)
            ->post(route('distributor.orders.send_for_approval', $order), [
                'items' => [
                    ['id' => $item->id, 'quantity' => 1, 'unit_price' => 2000],
                ],
                'comment' => 'Новая цена',
            ])
            ->assertRedirect();

        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        $this->actingAs($buyer)
            ->post(route('buyer.orders.reject_changes', $order), [
                'reason' => 'Цена слишком высокая',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(PlatformOrder::STATUS_AWAITING_CONFIRMATION, $order->status);
        $this->assertNull($order->rejection_reason);

        app(CurrentRoleService::class)->set($distUser, $distRole->id);
        $this->actingAs($distUser)
            ->get(route('distributor.orders.show', $order))
            ->assertOk()
            ->assertSee('Покупатель отклонил изменения')
            ->assertSee('Цена слишком высокая')
            ->assertSee('Подтвердить')
            ->assertSee('Изменить заказ');
    }

    public function test_buyer_can_cancel_new_order(): void
    {
        [$buyer, , $order] = $this->seedOrder();
        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);

        $this->actingAs($buyer)
            ->post(route('buyer.orders.cancel', $order))
            ->assertRedirect();

        $this->assertSame(PlatformOrder::STATUS_REJECTED, $order->fresh()->status);
    }

    public function test_tz_statuses_exist_in_directory(): void
    {
        $this->assertDatabaseHas('order_statuses', ['slug' => 'awaiting_confirmation', 'is_active' => 1]);
        $this->assertDatabaseHas('order_statuses', ['slug' => 'confirmed', 'is_active' => 1]);
        $this->assertDatabaseHas('order_statuses', ['slug' => 'needs_approval', 'is_active' => 1]);
        $this->assertDatabaseHas('order_statuses', ['slug' => 'rejected', 'is_active' => 1]);
        $this->assertDatabaseHas('order_statuses', ['slug' => 'ready_to_ship', 'is_active' => 1]);
        $this->assertDatabaseHas('order_statuses', ['slug' => 'shipped', 'is_active' => 1]);
        $this->assertDatabaseHas('order_statuses', ['slug' => 'completed', 'name' => 'Завершён']);
    }

    /**
     * @return array{0: User, 1: User, 2: PlatformOrder}
     */
    private function seedOrder(): array
    {
        $region = Region::factory()->create(['name' => 'Москва']);
        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();

        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
            'name' => 'Товар статуса',
        ]);

        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $buyer = User::factory()->create();
        $buyer->roles()->attach($buyerRole->id, ['company_region' => $region->name]);
        $buyerProfile = $buyer->getOrCreateEndCompanyProfile();

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
        $distProfile->update(['full_name' => 'Поставщик Статус', 'short_name' => 'ПС']);
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
            'internal_sku' => 'SKU-S',
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

        return [$buyer->fresh(), $distUser->fresh(), $order];
    }
}
