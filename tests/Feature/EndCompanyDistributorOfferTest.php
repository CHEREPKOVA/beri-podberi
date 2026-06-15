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
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Catalog\EndCompanyDistributorOfferService;
use App\Services\CurrentRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndCompanyDistributorOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_uses_distributor_retail_price_and_regional_stock(): void
    {
        $region = Region::factory()->create(['name' => 'Москва']);
        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();

        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'base_price' => 9999,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $distributorUser = User::factory()->create();
        $distributorRole = Role::query()->create([
            'slug' => Role::SLUG_DISTRIBUTOR,
            'name' => 'Дистрибьютор',
            'sort_order' => 1,
        ]);
        $distributorUser->roles()->attach($distributorRole->id, ['company_name' => 'Dist']);
        $distributorProfile = $distributorUser->getOrCreateDistributorProfile();
        $distributorProfile->regions()->sync([$region->id => ['is_primary' => true]]);

        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'distributor_profile_id' => $distributorProfile->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);

        $warehouse = DistributorWarehouse::query()->create([
            'distributor_profile_id' => $distributorProfile->id,
            'name' => 'Склад Москва',
            'address' => 'Москва',
            'region_id' => $region->id,
            'type' => DistributorWarehouse::TYPE_MAIN,
            'is_active' => true,
        ]);

        $distProduct = DistributorProduct::query()->create([
            'distributor_profile_id' => $distributorProfile->id,
            'source_product_id' => $product->id,
            'manufacturer_profile_id' => $manufacturer->id,
            'product_category_id' => $category->id,
            'name' => $product->name,
            'internal_sku' => 'D-OFFER-1',
            'retail_price' => 4500.50,
            'status' => DistributorProduct::STATUS_ACTIVE,
            'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
        ]);

        DistributorProductStock::query()->create([
            'distributor_product_id' => $distProduct->id,
            'distributor_warehouse_id' => $warehouse->id,
            'quantity' => 12,
            'reserved' => 2,
            'stock_updated_at' => now(),
        ]);

        $service = new EndCompanyDistributorOfferService($region->id);
        $summary = $service->summaryForProduct($product);

        $this->assertEqualsWithDelta(4500.50, (float) $summary['display_price'], 0.001);
        $this->assertSame(10, $summary['available_stock']);
        $this->assertCount(1, $summary['stock_rows']);
        $this->assertEqualsWithDelta(4500.50, (float) $summary['stock_rows']->first()['retail_price'], 0.001);
    }

    public function test_enrich_products_sets_listing_price_from_distributor(): void
    {
        $region = Region::factory()->create(['name' => 'Казань']);
        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'base_price' => 8000,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $distributorUser = User::factory()->create();
        $distributorRole = Role::query()->create([
            'slug' => Role::SLUG_DISTRIBUTOR,
            'name' => 'Дистрибьютор',
            'sort_order' => 1,
        ]);
        $distributorUser->roles()->attach($distributorRole->id);
        $profile = $distributorUser->getOrCreateDistributorProfile();
        $profile->regions()->sync([$region->id => ['is_primary' => true]]);

        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'distributor_profile_id' => $profile->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);

        DistributorProduct::query()->create([
            'distributor_profile_id' => $profile->id,
            'source_product_id' => $product->id,
            'manufacturer_profile_id' => $manufacturer->id,
            'product_category_id' => $category->id,
            'name' => $product->name,
            'internal_sku' => 'D-OFFER-2',
            'retail_price' => 3200,
            'status' => DistributorProduct::STATUS_ACTIVE,
            'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
        ]);

        $enriched = (new EndCompanyDistributorOfferService($region->id))
            ->enrichProducts(collect([$product]))
            ->first();

        $this->assertSame('3200', $enriched->distributor_display_price);
    }

    public function test_product_without_distributor_price_is_hidden_when_price_required(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'catalog.end_company_require_distributor_price'],
            ['group_key' => 'catalog', 'label' => 'price', 'value' => '1', 'value_type' => 'boolean', 'sort_order' => 1, 'is_active' => true],
        );

        $region = Region::factory()->create(['name' => 'Самара']);
        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();
        $visible = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $hidden = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $role = Role::query()->create([
            'slug' => Role::SLUG_END_COMPANY,
            'name' => 'Конечная компания',
            'sort_order' => 0,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['company_region' => $region->name]);

        $distUser = User::factory()->create();
        $distRole = Role::query()->create([
            'slug' => Role::SLUG_DISTRIBUTOR,
            'name' => 'Дистрибьютор',
            'sort_order' => 1,
        ]);
        $distUser->roles()->attach($distRole->id);
        $profile = $distUser->getOrCreateDistributorProfile();
        $profile->regions()->sync([$region->id => ['is_primary' => true]]);

        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'distributor_profile_id' => $profile->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);

        foreach ([$visible, $hidden] as $index => $product) {
            DistributorProduct::query()->create([
                'distributor_profile_id' => $profile->id,
                'source_product_id' => $product->id,
                'manufacturer_profile_id' => $manufacturer->id,
                'product_category_id' => $category->id,
                'name' => $product->name,
                'internal_sku' => 'D-NO-PRICE-'.$product->id,
                'retail_price' => $index === 0 ? 1500 : null,
                'status' => DistributorProduct::STATUS_ACTIVE,
                'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
            ]);
        }

        app(CurrentRoleService::class)->set($user, $role->id);
        $ids = (new CatalogQueryService($user))->visibleProductsQuery()->pluck('id')->all();

        $this->assertContains($visible->id, $ids);
        $this->assertNotContains($hidden->id, $ids);
    }

    public function test_unavailable_product_is_listed_when_setting_enabled(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'catalog.end_company_require_distributor_price'],
            ['group_key' => 'catalog', 'label' => 'price', 'value' => '1', 'value_type' => 'boolean', 'sort_order' => 1, 'is_active' => true],
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'catalog.end_company_show_unavailable_products'],
            ['group_key' => 'catalog', 'label' => 'unavailable', 'value' => '1', 'value_type' => 'boolean', 'sort_order' => 2, 'is_active' => true],
        );

        $region = Region::factory()->create(['name' => 'Тула']);
        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $role = Role::query()->create([
            'slug' => Role::SLUG_END_COMPANY,
            'name' => 'Конечная компания',
            'sort_order' => 0,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['company_region' => $region->name]);

        $distUser = User::factory()->create();
        $distRole = Role::query()->create([
            'slug' => Role::SLUG_DISTRIBUTOR,
            'name' => 'Дистрибьютор',
            'sort_order' => 1,
        ]);
        $distUser->roles()->attach($distRole->id);
        $profile = $distUser->getOrCreateDistributorProfile();
        $profile->regions()->sync([$region->id => ['is_primary' => true]]);

        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'distributor_profile_id' => $profile->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);

        DistributorProduct::query()->create([
            'distributor_profile_id' => $profile->id,
            'source_product_id' => $product->id,
            'manufacturer_profile_id' => $manufacturer->id,
            'product_category_id' => $category->id,
            'name' => $product->name,
            'internal_sku' => 'D-UNAVAIL',
            'retail_price' => null,
            'status' => DistributorProduct::STATUS_ACTIVE,
            'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
        ]);

        app(CurrentRoleService::class)->set($user, $role->id);
        $catalog = new CatalogQueryService($user);
        $ids = $catalog->visibleProductsQuery()->pluck('id')->all();
        $enriched = $catalog->distributorOffers()->enrichProducts(
            $catalog->visibleProductsQuery()->get()
        )->first();

        $this->assertContains($product->id, $ids);
        $this->assertTrue($enriched->unavailable_in_region);
        $this->assertFalse($enriched->is_purchasable);
    }
}
