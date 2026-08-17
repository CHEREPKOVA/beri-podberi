<?php

namespace Tests\Feature;

use App\Models\DistributorProduct;
use App\Models\DistributorProductStock;
use App\Models\DistributorProfile;
use App\Models\DistributorWarehouse;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\ManufacturerProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStock;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Catalog\ProductCatalogCardService;
use App\Services\CurrentRoleService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_end_company_card_includes_full_tz_blocks(): void
    {
        [$product, , $user] = $this->seedPurchasableProduct();

        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $response = $this->actingAs($user)
            ->get(route('buyer.catalog.show', $product));

        $response->assertOk();
        $response->assertSee('Фотогалерея');
        $response->assertSee('Наличие у поставщиков');
        $response->assertSee('Техническая документация');
        $response->assertSee('Логистические параметры');
        $response->assertSee('productCatalogLive', false);
    }

    public function test_catalog_marks_are_shown_on_card(): void
    {
        [$product, , $user] = $this->seedPurchasableProduct();
        $product->update(['mark_is_new' => true, 'mark_on_sale' => true]);

        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $this->actingAs($user)
            ->get(route('buyer.catalog.show', $product))
            ->assertOk()
            ->assertSee('Новинка')
            ->assertSee('Распродажа');
    }

    public function test_live_endpoint_returns_fresh_offer_data(): void
    {
        [$product, , $user] = $this->seedPurchasableProduct();

        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $this->actingAs($user)
            ->getJson(route('buyer.catalog.product.live', $product))
            ->assertOk()
            ->assertJsonPath('visible', true)
            ->assertJsonStructure(['live' => ['warehouse_stock_rows', 'display_price_formatted']]);
    }

    public function test_distributor_card_shows_manufacturer_stocks_not_other_distributors(): void
    {
        $region = Region::factory()->create(['name' => 'Казань']);
        $manufacturer = ManufacturerProfile::factory()->create([
            'short_name' => 'ЗаводКазань',
            'full_name' => 'ООО «ЗаводКазань»',
        ]);
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
            'base_price' => 1500,
        ]);

        $mfrWarehouse = Warehouse::query()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'name' => 'Склад производителя Казань',
            'address' => 'Казань',
            'region_id' => $region->id,
            'type' => Warehouse::TYPE_MAIN,
            'is_active' => true,
        ]);
        ProductStock::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $mfrWarehouse->id,
            'quantity' => 40,
            'reserved' => 0,
            'stock_updated_at' => now(),
        ]);

        $ownDist = $this->createDistributorUser('Свой Дистрибьютор', $region, $manufacturer);
        $otherDist = $this->createDistributorUser('Чужой Дистрибьютор', $region, $manufacturer);

        foreach ([$ownDist, $otherDist] as $index => $distUser) {
            $profile = $distUser->distributorProfile;
            $warehouse = DistributorWarehouse::query()->create([
                'distributor_profile_id' => $profile->id,
                'name' => 'Склад '.$index,
                'address' => 'Адрес',
                'region_id' => $region->id,
                'type' => DistributorWarehouse::TYPE_MAIN,
                'is_active' => true,
            ]);
            $offer = DistributorProduct::query()->create([
                'distributor_profile_id' => $profile->id,
                'source_product_id' => $product->id,
                'manufacturer_profile_id' => $manufacturer->id,
                'product_category_id' => $category->id,
                'name' => $product->name,
                'internal_sku' => 'D-'.$profile->id,
                'retail_price' => 1000 + $index,
                'status' => DistributorProduct::STATUS_ACTIVE,
                'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
            ]);
            DistributorProductStock::query()->create([
                'distributor_product_id' => $offer->id,
                'distributor_warehouse_id' => $warehouse->id,
                'quantity' => 5,
                'reserved' => 0,
            ]);
        }

        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        app(CurrentRoleService::class)->set($ownDist, $distRole->id);

        $card = new ProductCatalogCardService($ownDist, new CatalogQueryService($ownDist));
        $data = $card->build($product->fresh(['category.parent', 'additionalCategories', 'images', 'unitType', 'attributeValues.attribute', 'documents', 'manufacturerProfile.regions', 'stocks.warehouse.region']));

        $this->assertSame('distributor', $data['cardRole']);
        $this->assertTrue($data['canAddToPurchase']);
        $this->assertCount(1, $data['warehouseStockRows']);
        $this->assertSame('ЗаводКазань', $data['warehouseStockRows']->first()['distributor_name']);
        $this->assertSame('Склад производителя Казань', $data['warehouseStockRows']->first()['warehouse_name']);
        $this->assertStringNotContainsString('Чужой', $data['warehouseStockRows']->first()['distributor_name']);
        $this->assertStringNotContainsString('Свой', $data['warehouseStockRows']->first()['distributor_name']);
    }

    public function test_distributor_card_has_purchase_cart_in_warehouse_block(): void
    {
        $region = Region::factory()->create(['name' => 'Казань']);
        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
            'base_price' => 1500,
            'min_order_quantity' => 1,
        ]);
        $manufacturer->regions()->sync([$region->id => ['is_primary' => true]]);

        $ownDist = $this->createDistributorUser('Свой Дистрибьютор', $region, $manufacturer);
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        app(CurrentRoleService::class)->set($ownDist, $distRole->id);

        $response = $this->actingAs($ownDist)
            ->get(route('buyer.catalog.show', $product));

        $response->assertOk();
        $response->assertSee('Наличие у поставщиков');
        $response->assertSee('Заказ');
        $response->assertSee(route('distributor.purchases.cart.items.store'), false);
        $response->assertSee(route('distributor.purchases.cart.index'), false);
        $response->assertSee('js-add-to-cart', false);
        $response->assertDontSee('>В корзину закупок<', false);
    }

    /**
     * @return array{0: Product, 1: Region, 2: User}
     */
    private function seedPurchasableProduct(): array
    {
        $region = Region::factory()->create(['name' => 'Москва']);
        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();

        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $role = Role::query()->where('slug', Role::SLUG_END_COMPANY)->first()
            ?? Role::query()->create(['slug' => Role::SLUG_END_COMPANY, 'name' => 'КК', 'sort_order' => 1]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['company_region' => $region->name]);

        $distUser = User::factory()->create();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->first()
            ?? Role::query()->create(['slug' => Role::SLUG_DISTRIBUTOR, 'name' => 'Дистрибьютор', 'sort_order' => 2]);
        $distUser->roles()->attach($distRole->id);
        $profile = $distUser->getOrCreateDistributorProfile();
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

        $offer = DistributorProduct::query()->create([
            'distributor_profile_id' => $profile->id,
            'source_product_id' => $product->id,
            'manufacturer_profile_id' => $manufacturer->id,
            'product_category_id' => $category->id,
            'name' => $product->name,
            'internal_sku' => 'D-1',
            'retail_price' => 2500,
            'status' => DistributorProduct::STATUS_ACTIVE,
            'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
        ]);

        DistributorProductStock::query()->create([
            'distributor_product_id' => $offer->id,
            'distributor_warehouse_id' => $warehouse->id,
            'quantity' => 20,
            'reserved' => 0,
            'stock_updated_at' => now(),
        ]);

        return [$product, $region, $user];
    }

    private function createDistributorUser(string $name, Region $region, ManufacturerProfile $manufacturer): User
    {
        $distUser = User::factory()->create();
        $distRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        $distUser->roles()->attach($distRole->id, ['company_name' => $name, 'company_region' => $region->name]);

        $profile = DistributorProfile::query()->create([
            'user_id' => $distUser->id,
            'full_name' => $name,
            'short_name' => $name,
            'inn' => '7700000000',
        ]);
        $profile->regions()->sync([$region->id => ['is_primary' => true]]);

        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'distributor_profile_id' => $profile->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);

        return $distUser->fresh('distributorProfile');
    }
}
