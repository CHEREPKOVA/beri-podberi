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
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
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

    public function test_distributor_card_shows_only_own_warehouse_rows(): void
    {
        $region = Region::factory()->create(['name' => 'Казань']);
        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
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
        $this->assertCount(1, $data['warehouseStockRows']);
        $this->assertStringContainsString('Свой', $data['warehouseStockRows']->first()['distributor_name']);
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
