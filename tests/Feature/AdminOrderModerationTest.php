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
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\CurrentRoleService;
use App\Services\Order\OrderStatusWorkflowService;
use Database\Seeders\DeliveryMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminOrderModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(DeliveryMethodSeeder::class);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'timings.order_pending_hours'],
            [
                'group_key' => 'timings',
                'label' => 'Часы ожидания',
                'value' => '24',
                'value_type' => 'integer',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    public function test_manager_can_list_and_view_all_orders(): void
    {
        [, , $order] = $this->seedOrder();
        $manager = $this->makeManager();

        $this->actingAs($manager)
            ->withSession(['current_role_id' => $manager->roles->first()->id])
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee($order->order_number);

        $this->actingAs($manager)
            ->withSession(['current_role_id' => $manager->roles->first()->id])
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_analyst_cannot_access_order_moderation(): void
    {
        [, , $order] = $this->seedOrder();
        $analystRole = Role::query()->where('slug', Role::SLUG_ANALYST)->firstOrFail();
        $analyst = User::factory()->create();
        $analyst->roles()->sync([$analystRole->id]);

        $this->actingAs($analyst)
            ->withSession(['current_role_id' => $analystRole->id])
            ->get(route('admin.orders.index'))
            ->assertForbidden();

        $this->actingAs($analyst)
            ->withSession(['current_role_id' => $analystRole->id])
            ->get(route('admin.orders.show', $order))
            ->assertForbidden();
    }

    public function test_admin_can_change_status_with_service_comment(): void
    {
        Notification::fake();

        [, , $order] = $this->seedOrder();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles->first()->id])
            ->post(route('admin.orders.update_status', $order), [
                'status' => PlatformOrder::STATUS_CONFIRMED,
                'comment' => 'Вмешательство: поставщик не отвечает',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();
        $this->assertSame(PlatformOrder::STATUS_CONFIRMED, $order->status);
        $this->assertDatabaseHas('platform_order_status_logs', [
            'platform_order_id' => $order->id,
            'action' => OrderStatusWorkflowService::ACTION_ADMIN_STATUS_CHANGE,
            'to_status' => PlatformOrder::STATUS_CONFIRMED,
        ]);
    }

    public function test_admin_can_pause_and_resume_order(): void
    {
        Notification::fake();

        [, $distUser, $order] = $this->seedOrder();
        $admin = $this->makeAdmin();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles->first()->id])
            ->post(route('admin.orders.pause', $order), [
                'pause_reason' => 'Спор между сторонами',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();
        $this->assertNotNull($order->paused_at);
        $this->assertSame('Спор между сторонами', $order->pause_reason);

        app(CurrentRoleService::class)->set($distUser, $distRole->id);
        $this->actingAs($distUser)
            ->post(route('distributor.orders.confirm', $order))
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertSame(PlatformOrder::STATUS_NEW, $order->fresh()->status);

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles->first()->id])
            ->post(route('admin.orders.resume', $order), [
                'comment' => 'Спор урегулирован',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertNull($order->fresh()->paused_at);
    }

    public function test_admin_service_comment_is_logged(): void
    {
        Notification::fake();

        [, , $order] = $this->seedOrder();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles->first()->id])
            ->post(route('admin.orders.comment', $order), [
                'comment' => 'Проверена корректность данных доставки',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertDatabaseHas('platform_order_status_logs', [
            'platform_order_id' => $order->id,
            'action' => OrderStatusWorkflowService::ACTION_SERVICE_COMMENT,
            'comment' => 'Проверена корректность данных доставки',
        ]);
    }

    public function test_stuck_orders_are_highlighted_in_problem_filter(): void
    {
        [, , $order] = $this->seedOrder();
        $order->forceFill([
            'status' => PlatformOrder::STATUS_NEW,
            'status_changed_at' => now()->subHours(30),
            'ordered_at' => now()->subHours(30),
        ])->save();

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles->first()->id])
            ->get(route('admin.orders.index', ['problem' => 'stuck']))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Без движения · 1 день');

        $this->assertTrue($order->fresh()->isStuck(24));
        $this->assertContains('stuck', $order->fresh()->problemFlags(24));
        $this->assertSame('Без движения · 1 день', $order->fresh()->problemFlagLabel('stuck'));

        $order->forceFill([
            'status_changed_at' => now()->subDays(15),
            'ordered_at' => now()->subDays(15),
        ])->save();
        $this->assertSame('Без движения · 15 дней', $order->fresh()->problemFlagLabel('stuck'));

        $order->forceFill([
            'status_changed_at' => now()->subDays(3),
            'ordered_at' => now()->subDays(3),
        ])->save();
        $this->assertSame('Без движения · 3 дня', $order->fresh()->problemFlagLabel('stuck'));
    }

    public function test_rejected_without_reason_is_flagged(): void
    {
        [, , $order] = $this->seedOrder();
        $order->forceFill([
            'status' => PlatformOrder::STATUS_REJECTED,
            'rejection_reason' => null,
            'status_changed_at' => now(),
        ])->save();

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles->first()->id])
            ->get(route('admin.orders.index', ['problem' => 'rejected_without_reason']))
            ->assertOk()
            ->assertSee($order->order_number);

        $this->assertTrue($order->fresh()->isRejectedWithoutReason());
    }

    private function makeAdmin(): User
    {
        $role = Role::query()->where('slug', Role::SLUG_ADMIN)->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user->fresh(['roles']);
    }

    private function makeManager(): User
    {
        $role = Role::query()->where('slug', Role::SLUG_MANAGER)->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user->fresh(['roles']);
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
            'name' => 'Товар модерации',
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
        $distProfile->update(['full_name' => 'Поставщик Модерация', 'short_name' => 'ПМ']);
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
            'internal_sku' => 'SKU-M',
            'retail_price' => 1500,
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
