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
use App\Models\PlatformOrderMessage;
use App\Models\PlatformOrderMessageAttachment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Notifications\OrderCorrespondenceNotification;
use App\Services\Cart\CartService;
use App\Services\CurrentRoleService;
use App\Services\Order\OrderCorrespondenceService;
use Database\Seeders\DeliveryMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderCorrespondenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(DeliveryMethodSeeder::class);
    }

    public function test_checkout_posts_order_created_system_message_and_show_has_chat(): void
    {
        Notification::fake();
        [$buyer, $distUser, $order] = $this->seedOrder();

        $this->assertDatabaseHas('platform_order_messages', [
            'platform_order_id' => $order->id,
            'type' => PlatformOrderMessage::TYPE_SYSTEM,
            'event_code' => PlatformOrderMessage::EVENT_ORDER_CREATED,
            'body' => 'Заказ сформирован.',
        ]);

        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);

        $this->actingAs($buyer)
            ->get(route('buyer.orders.show', $order))
            ->assertOk()
            ->assertSee('Переписка по заказу')
            ->assertSee('Заказ сформирован.');
    }

    public function test_buyer_and_supplier_can_exchange_messages_with_attachment(): void
    {
        Notification::fake();
        Storage::fake('public');
        [$buyer, $distUser, $order] = $this->seedOrder();

        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();

        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        $this->actingAs($buyer)
            ->post(route('buyer.orders.messages.store', $order), [
                'body' => 'Нужна отгрузка завтра',
                'attachments' => [UploadedFile::fake()->image('photo.jpg')],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('platform_order_messages', [
            'platform_order_id' => $order->id,
            'author_user_id' => $buyer->id,
            'author_role' => PlatformOrderMessage::ROLE_BUYER,
            'body' => 'Нужна отгрузка завтра',
        ]);
        $this->assertDatabaseHas('platform_order_status_logs', [
            'platform_order_id' => $order->id,
            'action' => OrderCorrespondenceService::ACTION_MESSAGE,
        ]);

        Notification::assertSentTo($distUser, OrderCorrespondenceNotification::class);

        $attachment = PlatformOrderMessageAttachment::query()->firstOrFail();

        app(CurrentRoleService::class)->set($distUser, $distRole->id);
        $this->actingAs($distUser)
            ->get(route('distributor.orders.show', $order))
            ->assertOk()
            ->assertSee('Нужна отгрузка завтра')
            ->assertSee('photo.jpg');

        $this->actingAs($distUser)
            ->get(route('distributor.orders.messages.attachments.download', [$order, $attachment]))
            ->assertOk();

        $this->actingAs($distUser)
            ->post(route('distributor.orders.messages.store', $order), [
                'body' => 'Отгрузим утром',
            ])
            ->assertRedirect();

        Notification::assertSentTo($buyer, OrderCorrespondenceNotification::class);

        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        $this->actingAs($buyer)
            ->get(route('buyer.orders.show', $order))
            ->assertOk()
            ->assertSee('Отгрузим утром');
    }

    public function test_outsider_cannot_see_or_write_correspondence(): void
    {
        Notification::fake();
        [$buyer, , $order] = $this->seedOrder();

        $strangerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $stranger = User::factory()->create();
        $stranger->roles()->attach($strangerRole->id);
        $stranger->getOrCreateEndCompanyProfile();
        app(CurrentRoleService::class)->set($stranger, $strangerRole->id);

        $this->actingAs($stranger)
            ->get(route('buyer.orders.show', $order))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->post(route('buyer.orders.messages.store', $order), [
                'body' => 'Чужое сообщение',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('platform_order_messages', [
            'platform_order_id' => $order->id,
            'body' => 'Чужое сообщение',
        ]);

        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        $this->actingAs($buyer)->post(route('buyer.orders.messages.store', $order), [
            'body' => 'Только для участников',
        ]);

        $attachment = PlatformOrderMessageAttachment::query()->first();
        $this->assertNull($attachment);
    }

    public function test_workflow_events_create_system_messages(): void
    {
        Notification::fake();
        [$buyer, $distUser, $order] = $this->seedOrder();

        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();

        app(CurrentRoleService::class)->set($distUser, $distRole->id);
        $this->actingAs($distUser)->get(route('distributor.orders.show', $order));

        $this->assertDatabaseHas('platform_order_messages', [
            'platform_order_id' => $order->id,
            'event_code' => PlatformOrderMessage::EVENT_STATUS_CHANGED,
            'body' => 'Заказ перешёл в статус «Ожидает подтверждения».',
        ]);

        $item = $order->items()->firstOrFail();
        $this->actingAs($distUser)->post(route('distributor.orders.send_for_approval', $order), [
            'items' => [
                ['id' => $item->id, 'quantity' => 2, 'unit_price' => 2500],
            ],
            'comment' => 'Цена обновлена',
        ]);
        $this->assertDatabaseHas('platform_order_messages', [
            'platform_order_id' => $order->id,
            'event_code' => PlatformOrderMessage::EVENT_SUPPLIER_CHANGES,
            'body' => 'Поставщик внёс изменения.',
        ]);

        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        $this->actingAs($buyer)->post(route('buyer.orders.approve_changes', $order));
        $this->assertDatabaseHas('platform_order_messages', [
            'platform_order_id' => $order->id,
            'event_code' => PlatformOrderMessage::EVENT_BUYER_APPROVED,
            'body' => 'Покупатель согласовал корректировки.',
        ]);

        app(CurrentRoleService::class)->set($distUser, $distRole->id);
        $this->actingAs($distUser)->post(route('distributor.orders.mark_ready', $order));
        $this->actingAs($distUser)->post(route('distributor.orders.mark_shipped', $order), [
            'tracking_number' => 'TTN-555',
        ]);
        $this->assertDatabaseHas('platform_order_messages', [
            'platform_order_id' => $order->id,
            'event_code' => PlatformOrderMessage::EVENT_TTN_ADDED,
            'body' => 'Поставщик добавил ТТН: TTN-555',
        ]);
    }

    public function test_admin_can_moderate_message_but_parties_cannot_delete(): void
    {
        Notification::fake();
        [$buyer, , $order] = $this->seedOrder();

        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        $this->actingAs($buyer)->post(route('buyer.orders.messages.store', $order), [
            'body' => 'Сообщение для модерации',
        ]);

        $message = PlatformOrderMessage::query()
            ->where('body', 'Сообщение для модерации')
            ->firstOrFail();

        $this->actingAs($buyer)
            ->delete(route('admin.orders.messages.destroy', [$order, $message]))
            ->assertForbidden();

        $this->assertFalse($message->fresh()->trashed());

        $admin = $this->makeAdmin();
        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles->first()->id])
            ->delete(route('admin.orders.messages.destroy', [$order, $message]))
            ->assertRedirect();

        $this->assertTrue($message->fresh()->trashed());
        $this->assertDatabaseHas('platform_order_status_logs', [
            'platform_order_id' => $order->id,
            'action' => OrderCorrespondenceService::ACTION_MESSAGE_DELETED,
        ]);

        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        $this->actingAs($buyer)
            ->withSession(['current_role_id' => $buyerRole->id])
            ->get(route('buyer.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Удалено администратором');

        $this->assertFalse(
            PlatformOrderMessage::query()
                ->where('platform_order_id', $order->id)
                ->where('body', 'Сообщение для модерации')
                ->exists()
        );

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles->first()->id])
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Сообщение для модерации')
            ->assertSee('Удалено администратором');
    }

    public function test_correspondence_remains_available_after_completion(): void
    {
        Notification::fake();
        [$buyer, $distUser, $order] = $this->seedOrder();

        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();

        app(CurrentRoleService::class)->set($distUser, $distRole->id);
        $this->actingAs($distUser)->get(route('distributor.orders.show', $order));
        $this->actingAs($distUser)->post(route('distributor.orders.confirm', $order));
        $this->actingAs($distUser)->post(route('distributor.orders.mark_in_work', $order));
        $this->actingAs($distUser)->post(route('distributor.orders.mark_ready', $order));
        $this->actingAs($distUser)->post(route('distributor.orders.mark_shipped', $order), [
            'tracking_number' => 'TTN-DONE',
        ]);

        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        $this->actingAs($buyer)->post(route('buyer.orders.confirm_receipt', $order));
        $this->assertSame(PlatformOrder::STATUS_COMPLETED, $order->fresh()->status);

        $this->actingAs($buyer)
            ->post(route('buyer.orders.messages.store', $order), [
                'body' => 'Документы после завершения',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('platform_order_messages', [
            'platform_order_id' => $order->id,
            'body' => 'Документы после завершения',
        ]);
    }

    private function makeAdmin(): User
    {
        $role = Role::query()->where('slug', Role::SLUG_ADMIN)->firstOrFail();
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
            'name' => 'Товар переписки',
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
            'contact_person' => 'Иван Ответственный',
            'phone' => '+7 900 000-00-00',
        ]);

        $distUser = User::factory()->create();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        $distUser->roles()->attach($distRole->id);
        $distProfile = $distUser->getOrCreateDistributorProfile();
        $distProfile->update([
            'full_name' => 'Поставщик Переписка',
            'short_name' => 'ПП',
        ]);
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
            'internal_sku' => 'SKU-CHAT',
            'retail_price' => 2500,
            'status' => DistributorProduct::STATUS_ACTIVE,
            'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
            'min_order_quantity' => 2,
            'pack_quantity' => 2,
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
