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
use App\Models\PlatformOrderDocument;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\CurrentRoleService;
use App\Services\Order\OrderStatusWorkflowService;
use Database\Seeders\DeliveryMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderDetailsDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(DeliveryMethodSeeder::class);
    }

    public function test_order_show_contains_tz44_blocks_for_buyer_and_supplier(): void
    {
        Notification::fake();
        [$buyer, $distUser, $order] = $this->seedOrder();

        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();

        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        $this->actingAs($buyer)
            ->get(route('buyer.orders.show', $order))
            ->assertOk()
            ->assertSee('Состав заказа')
            ->assertSee('Участники заказа')
            ->assertSee('Стоимость')
            ->assertSee('Логистика')
            ->assertSee('Документы по заказу')
            ->assertSee('История изменений')
            ->assertSee('Создание заказа');

        app(CurrentRoleService::class)->set($distUser, $distRole->id);
        $this->actingAs($distUser)
            ->get(route('distributor.orders.show', $order))
            ->assertOk()
            ->assertSee('Участники заказа')
            ->assertSee('Документы по заказу')
            ->assertSee('Ознакомление');

        $this->assertSame(PlatformOrder::STATUS_AWAITING_CONFIRMATION, $order->fresh()->status);
        $this->assertDatabaseHas('platform_order_status_logs', [
            'platform_order_id' => $order->id,
            'action' => OrderStatusWorkflowService::ACTION_OPEN,
        ]);
    }

    public function test_supplier_and_buyer_can_upload_and_download_documents(): void
    {
        Notification::fake();
        Storage::fake('public');

        [$buyer, $distUser, $order] = $this->seedOrder();
        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();

        app(CurrentRoleService::class)->set($distUser, $distRole->id);
        $this->actingAs($distUser)
            ->post(route('distributor.orders.documents.store', $order), [
                'name' => 'ТТН файл',
                'type' => PlatformOrderDocument::TYPE_TTN,
                'file' => UploadedFile::fake()->create('ttn.pdf', 120, 'application/pdf'),
                'notes' => 'Скан ТТН',
            ])
            ->assertRedirect(route('distributor.orders.show', $order));

        $supplierDoc = PlatformOrderDocument::query()->where('uploader_role', PlatformOrderDocument::ROLE_SUPPLIER)->firstOrFail();
        $this->assertSame('ТТН файл', $supplierDoc->name);

        $this->actingAs($distUser)
            ->get(route('distributor.orders.documents.download', [$order, $supplierDoc]))
            ->assertOk();

        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        $this->actingAs($buyer)
            ->post(route('buyer.orders.documents.store', $order), [
                'name' => 'Претензия по качеству',
                'type' => PlatformOrderDocument::TYPE_CLAIM,
                'file' => UploadedFile::fake()->create('claim.pdf', 80, 'application/pdf'),
            ])
            ->assertRedirect(route('buyer.orders.show', $order));

        $this->assertTrue($order->fresh()->has_active_claim);
        $this->assertDatabaseHas('platform_order_status_logs', [
            'platform_order_id' => $order->id,
            'action' => 'upload_document',
        ]);
    }

    public function test_documents_locked_after_completion(): void
    {
        Notification::fake();
        Storage::fake('public');

        [$buyer, $distUser, $order] = $this->seedOrder();
        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();

        app(CurrentRoleService::class)->set($distUser, $distRole->id);
        $this->actingAs($distUser)->post(route('distributor.orders.confirm', $order));
        $this->actingAs($distUser)->post(route('distributor.orders.mark_ready', $order));
        $this->actingAs($distUser)->post(route('distributor.orders.mark_shipped', $order), [
            'tracking_number' => 'TTN-DOC-1',
        ]);

        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        $this->actingAs($buyer)
            ->post(route('buyer.orders.confirm_receipt', $order), [
                'completion_notes' => 'Всё получено',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(PlatformOrder::STATUS_COMPLETED, $order->status);
        $this->assertNotNull($order->received_at);
        $this->assertSame('Всё получено', $order->completion_notes);

        $this->actingAs($buyer)
            ->from(route('buyer.orders.show', $order))
            ->post(route('buyer.orders.documents.store', $order), [
                'name' => 'Поздно',
                'type' => PlatformOrderDocument::TYPE_CLAIM,
                'file' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('file');
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
            'name' => 'Товар документов',
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
            'full_name' => 'Поставщик Документы',
            'short_name' => 'ПД',
            'delivery_notes' => 'Отгрузка по будням',
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
            'internal_sku' => 'SKU-DOC',
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
        $this->assertSame(2, $order->items->first()?->pack_quantity);

        return [$buyer->fresh(), $distUser->fresh(), $order];
    }
}
