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
use App\Models\TransportCompany;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\CurrentRoleService;
use Database\Seeders\DeliveryMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderBuyerCabinetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(DeliveryMethodSeeder::class);
    }

    public function test_orders_index_shows_tz_columns_and_highlights_needs_approval(): void
    {
        Notification::fake();
        [$buyer, $distUser, $order] = $this->seedOrder();

        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();

        app(CurrentRoleService::class)->set($distUser, $distRole->id);
        $this->actingAs($distUser)->post(route('distributor.orders.send_for_approval', $order), [
            'items' => [
                [
                    'id' => $order->items->first()->id,
                    'quantity' => 3,
                    'unit_price' => 2600,
                    'reason' => 'Нет нужного остатка',
                ],
            ],
            'comment' => 'Нужно согласовать',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(PlatformOrder::STATUS_NEEDS_APPROVAL, $order->status);

        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        $this->actingAs($buyer)
            ->get(route('buyer.orders.index'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Позиции')
            ->assertSee('Обновлён')
            ->assertSee('Требуется согласование изменений')
            ->assertSee('1'); // items_count

        $this->actingAs($buyer)
            ->get(route('buyer.orders.show', $order))
            ->assertOk()
            ->assertSee('Согласовать изменения')
            ->assertSee('Причина: Нет нужного остатка')
            ->assertDontSee('Повторить заказ');
    }

    public function test_reorder_adds_items_to_cart(): void
    {
        Notification::fake();
        [$buyer, , $order] = $this->seedOrder();
        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);

        $this->actingAs($buyer)
            ->post(route('buyer.orders.reorder', $order))
            ->assertRedirect(route('buyer.cart.index'));

        $cart = app(CartService::class)->view($buyer);
        $this->assertSame(1, $cart['totals']['items_count']);
        $this->assertSame(2, (int) $cart['groups']->first()['items']->first()['quantity']);
    }

    public function test_document_preview_and_tracking_link(): void
    {
        Notification::fake();
        Storage::fake('public');

        [$buyer, $distUser, $order] = $this->seedOrder();
        $buyerRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();

        $tc = TransportCompany::query()->create([
            'name' => 'Быстрая ТК',
            'slug' => 'fast-tc',
            'website' => 'https://fast-tc.example',
            'tracking_url' => 'https://fast-tc.example/track/',
            'is_active' => true,
        ]);

        app(CurrentRoleService::class)->set($distUser, $distRole->id);
        $this->actingAs($distUser)->post(route('distributor.orders.confirm', $order));
        $this->actingAs($distUser)->post(route('distributor.orders.mark_ready', $order));
        $this->actingAs($distUser)->post(route('distributor.orders.mark_shipped', $order), [
            'tracking_number' => 'TRACK-77',
            'transport_company_id' => $tc->id,
        ]);

        $this->actingAs($distUser)->post(route('distributor.orders.documents.store', $order), [
            'name' => 'Счёт',
            'type' => PlatformOrderDocument::TYPE_ACCOUNT,
            'file' => UploadedFile::fake()->create('invoice.pdf', 40, 'application/pdf'),
        ])->assertRedirect();

        $document = PlatformOrderDocument::query()->firstOrFail();

        app(CurrentRoleService::class)->set($buyer, $buyerRole->id);
        $this->actingAs($buyer)
            ->get(route('buyer.orders.show', $order))
            ->assertOk()
            ->assertSee('Отследить на сайте ТК')
            ->assertSee('https://fast-tc.example/track/TRACK-77')
            ->assertSee('Просмотреть');

        $this->actingAs($buyer)
            ->get(route('buyer.orders.documents.preview', [$order, $document]))
            ->assertOk()
            ->assertHeader('content-disposition');
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
            'name' => 'Товар кабинета',
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
            'internal_sku' => 'SKU-CAB',
            'retail_price' => 2500,
            'status' => DistributorProduct::STATUS_ACTIVE,
            'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
            'min_order_quantity' => 1,
        ]);

        DistributorProductStock::query()->create([
            'distributor_product_id' => $offer->id,
            'distributor_warehouse_id' => $warehouse->id,
            'quantity' => 50,
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

        return [$buyer->fresh(), $distUser->fresh(), PlatformOrder::query()->firstOrFail()];
    }
}
