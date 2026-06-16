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
use App\Services\Catalog\CatalogCacheService;
use App\Services\Catalog\CatalogListingParams;
use App\Services\Catalog\CatalogListingService;
use App\Services\Catalog\CatalogQueryService;
use App\Services\CurrentRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CatalogListingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_distributor_filter_limits_end_company_catalog_products(): void
    {
        $role = Role::query()->create([
            'slug' => Role::SLUG_END_COMPANY,
            'name' => 'Конечная компания',
            'sort_order' => 0,
        ]);
        $region = Region::factory()->create(['name' => 'Москва']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['company_region' => $region->name]);

        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();

        $productA = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $productB = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $distA = $this->linkProductToDistributorInRegion($productA, $region, $manufacturer, 'Dist A');
        $this->linkProductToDistributorInRegion($productB, $region, $manufacturer, 'Dist B');

        $user->load('roles');
        app(CurrentRoleService::class)->set($user, $role->id);
        $catalog = new CatalogQueryService($user);
        $listing = new CatalogListingService($catalog, $user);

        $result = $listing->build($category, new CatalogListingParams(
            distributorIds: [$distA->id],
        ));

        $this->assertSame([$productA->id], $result['products']->pluck('id')->all());
    }

    public function test_stock_filter_in_stock_for_end_company(): void
    {
        $role = Role::query()->create([
            'slug' => Role::SLUG_END_COMPANY,
            'name' => 'Конечная компания',
            'sort_order' => 0,
        ]);
        $region = Region::factory()->create(['name' => 'Москва']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['company_region' => $region->name]);

        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();

        $inStockProduct = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $onOrderProduct = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $distProfile = $this->linkProductToDistributorInRegion($inStockProduct, $region, $manufacturer, 'Dist Stock');
        $this->linkProductToDistributorInRegion($onOrderProduct, $region, $manufacturer, 'Dist Stock', withStock: false);

        $user->load('roles');
        app(CurrentRoleService::class)->set($user, $role->id);
        $listing = new CatalogListingService(new CatalogQueryService($user), $user);

        $inStock = $listing->build($category, new CatalogListingParams(
            stock: CatalogListingParams::STOCK_IN_STOCK,
        ));
        $this->assertSame([$inStockProduct->id], $inStock['products']->pluck('id')->all());

        $onOrder = $listing->build($category, new CatalogListingParams(
            stock: CatalogListingParams::STOCK_ON_ORDER,
        ));
        $this->assertSame([$onOrderProduct->id], $onOrder['products']->pluck('id')->all());
    }

    public function test_search_finds_product_by_manufacturer_name(): void
    {
        $role = Role::query()->create([
            'slug' => Role::SLUG_MANUFACTURER,
            'name' => 'Производитель',
            'sort_order' => 0,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $profile = ManufacturerProfile::factory()->create([
            'user_id' => $user->id,
            'short_name' => 'UniqueBrandXYZ',
        ]);
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $profile->id,
            'category_id' => $category->id,
            'name' => 'Neutral product title',
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $user->load('roles');
        app(CurrentRoleService::class)->set($user, $role->id);
        $listing = new CatalogListingService(new CatalogQueryService($user), $user);

        $result = $listing->build(null, new CatalogListingParams(search: 'UniqueBrandXYZ'));
        $this->assertContains($product->id, $result['products']->pluck('id')->all());
    }

    public function test_global_search_scope_ignores_selected_category(): void
    {
        $role = Role::query()->create([
            'slug' => Role::SLUG_MANUFACTURER,
            'name' => 'Производитель',
            'sort_order' => 0,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $profile = ManufacturerProfile::factory()->create(['user_id' => $user->id]);

        $categoryA = ProductCategory::factory()->create(['name' => 'Cat A']);
        $categoryB = ProductCategory::factory()->create(['name' => 'Cat B']);
        $inA = Product::factory()->create([
            'manufacturer_profile_id' => $profile->id,
            'category_id' => $categoryA->id,
            'name' => 'GlobalScopeItem',
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        Product::factory()->create([
            'manufacturer_profile_id' => $profile->id,
            'category_id' => $categoryB->id,
            'name' => 'Other',
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $user->load('roles');
        app(CurrentRoleService::class)->set($user, $role->id);
        $listing = new CatalogListingService(new CatalogQueryService($user), $user);

        $scoped = $listing->build($categoryB, new CatalogListingParams(
            search: 'GlobalScopeItem',
            searchScope: CatalogListingParams::SEARCH_SCOPE_CATEGORY,
        ));
        $this->assertCount(0, $scoped['products']);

        $global = $listing->build($categoryB, new CatalogListingParams(
            search: 'GlobalScopeItem',
            searchScope: CatalogListingParams::SEARCH_SCOPE_GLOBAL,
        ));
        $this->assertSame([$inA->id], $global['products']->pluck('id')->all());
    }

    public function test_price_bounds_reflect_offer_prices_for_end_company(): void
    {
        $role = Role::query()->create([
            'slug' => Role::SLUG_END_COMPANY,
            'name' => 'Конечная компания',
            'sort_order' => 0,
        ]);
        $region = Region::factory()->create(['name' => 'Москва']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['company_region' => $region->name]);

        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();

        $cheap = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $expensive = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $distCheap = $this->linkProductToDistributorInRegion($cheap, $region, $manufacturer, 'Cheap');
        $offerCheap = DistributorProduct::query()->where('distributor_profile_id', $distCheap->id)->where('source_product_id', $cheap->id)->first();
        $offerCheap->update(['retail_price' => 1500]);

        $distExp = $this->linkProductToDistributorInRegion($expensive, $region, $manufacturer, 'Exp');
        $offerExp = DistributorProduct::query()->where('distributor_profile_id', $distExp->id)->where('source_product_id', $expensive->id)->first();
        $offerExp->update(['retail_price' => 9500]);

        $user->load('roles');
        app(CurrentRoleService::class)->set($user, $role->id);
        $catalog = new CatalogQueryService($user, $region->id);
        $listing = new CatalogListingService($catalog, $user);

        $result = $listing->build($category, new CatalogListingParams());

        $this->assertSame(['min' => 1500.0, 'max' => 9500.0], $result['priceBounds']);
    }

    public function test_distributor_role_hides_distributor_structural_filter(): void
    {
        $role = Role::query()->create([
            'slug' => Role::SLUG_DISTRIBUTOR,
            'name' => 'Дистрибьютор',
            'sort_order' => 0,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $user->load('roles');
        app(CurrentRoleService::class)->set($user, $role->id);

        $listing = new CatalogListingService(new CatalogQueryService($user), $user);

        $this->assertSame(
            ['manufacturer', 'stock', 'price'],
            $listing->visibleStructuralFilters(),
        );
    }

    public function test_manufacturer_facet_counts_respect_other_filters(): void
    {
        $role = Role::query()->create([
            'slug' => Role::SLUG_END_COMPANY,
            'name' => 'Конечная компания',
            'sort_order' => 0,
        ]);
        $region = Region::factory()->create(['name' => 'Москва']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['company_region' => $region->name]);

        $manufacturerA = ManufacturerProfile::factory()->create(['short_name' => 'Mfr A']);
        $manufacturerB = ManufacturerProfile::factory()->create(['short_name' => 'Mfr B']);
        $category = ProductCategory::factory()->create();

        $productA = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturerA->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $productB = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturerB->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $distA = $this->linkProductToDistributorInRegion($productA, $region, $manufacturerA, 'Dist A');
        $this->linkProductToDistributorInRegion($productB, $region, $manufacturerB, 'Dist B');

        $user->load('roles');
        app(CurrentRoleService::class)->set($user, $role->id);
        $catalog = new CatalogQueryService($user, $region->id);
        $listing = new CatalogListingService($catalog, $user);

        $result = $listing->build($category, new CatalogListingParams(
            distributorIds: [$distA->id],
        ));

        $counts = $result['filterManufacturers']->keyBy('id')->map->facet_count;
        $this->assertSame(1, $counts[$manufacturerA->id] ?? null);
        $this->assertSame(0, $counts[$manufacturerB->id] ?? null);
    }

    public function test_normalize_sku_strips_spaces_and_uppercases(): void
    {
        $this->assertSame('AUTORJB8649', Product::normalizeSku('auto rjb-8649'));
    }

    public function test_catalog_cache_version_bumps_on_product_save(): void
    {
        Cache::forget('catalog.cache.version');
        $cache = app(CatalogCacheService::class);
        $before = $cache->version();

        Product::factory()->create([
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->assertGreaterThan($before, $cache->version());
    }

    private function linkProductToDistributorInRegion(
        Product $product,
        Region $region,
        ManufacturerProfile $manufacturer,
        string $distName,
        bool $withStock = true,
    ): DistributorProfile {
        $distributorUser = User::factory()->create();
        $distributorRole = Role::query()->firstOrCreate(
            ['slug' => Role::SLUG_DISTRIBUTOR],
            ['name' => 'Дистрибьютор', 'sort_order' => 1]
        );
        $distributorUser->roles()->attach($distributorRole->id, ['company_name' => $distName]);
        $distributorProfile = $distributorUser->getOrCreateDistributorProfile();
        $distributorProfile->update(['short_name' => $distName]);
        $distributorProfile->regions()->sync([$region->id => ['is_primary' => true]]);

        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'distributor_profile_id' => $distributorProfile->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);

        $offer = DistributorProduct::query()->create([
            'distributor_profile_id' => $distributorProfile->id,
            'source_product_id' => $product->id,
            'manufacturer_profile_id' => $manufacturer->id,
            'product_category_id' => $product->category_id,
            'name' => $product->name,
            'internal_sku' => 'TST-'.$product->id,
            'retail_price' => 1000,
            'status' => DistributorProduct::STATUS_ACTIVE,
            'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
        ]);

        if ($withStock) {
            $warehouse = DistributorWarehouse::query()->create([
                'distributor_profile_id' => $distributorProfile->id,
                'name' => 'Склад '.$distName,
                'address' => 'Адрес',
                'region_id' => $region->id,
                'is_active' => true,
            ]);
            DistributorProductStock::query()->create([
                'distributor_product_id' => $offer->id,
                'distributor_warehouse_id' => $warehouse->id,
                'quantity' => 10,
                'reserved' => 0,
            ]);
        }

        return $distributorProfile;
    }
}
