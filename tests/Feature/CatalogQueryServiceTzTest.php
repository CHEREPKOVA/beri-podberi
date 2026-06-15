<?php

namespace Tests\Feature;

use App\Models\DistributorProduct;
use App\Models\DistributorProfile;
use App\Models\EndCompanyDeliveryAddress;
use App\Models\EndCompanyProfile;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\ManufacturerProfile;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\CatalogQueryService;
use App\Services\CurrentRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogQueryServiceTzTest extends TestCase
{
    use RefreshDatabase;

    public function test_distributor_catalog_lists_only_partner_manufacturer_products(): void
    {
        $distributorRole = Role::query()->create([
            'slug' => Role::SLUG_DISTRIBUTOR,
            'name' => 'Дистрибьютор',
            'sort_order' => 0,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($distributorRole->id, [
            'company_name' => 'Dist Co',
            'company_region' => 'Москва',
        ]);
        $distributorProfile = $user->getOrCreateDistributorProfile();

        $partnerManufacturer = ManufacturerProfile::factory()->create();
        $otherManufacturer = ManufacturerProfile::factory()->create();

        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $partnerManufacturer->id,
            'distributor_profile_id' => $distributorProfile->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);

        $category = ProductCategory::factory()->create();
        $partnerProduct = Product::factory()->create([
            'manufacturer_profile_id' => $partnerManufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        Product::factory()->create([
            'manufacturer_profile_id' => $otherManufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        app(CurrentRoleService::class)->set($user, $distributorRole->id);
        $ids = (new CatalogQueryService($user))->visibleProductsQuery()->pluck('id')->all();

        $this->assertSame([$partnerProduct->id], $ids);
    }

    public function test_end_company_catalog_lists_only_products_from_distributors_in_region(): void
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

        $visibleProduct = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $hiddenProduct = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->linkProductToDistributorInRegion($visibleProduct, $region, $manufacturer);

        $user->load('roles');
        app(CurrentRoleService::class)->set($user, $role->id);
        $ids = (new CatalogQueryService($user))->visibleProductsQuery()->pluck('id')->all();

        $this->assertContains($visibleProduct->id, $ids);
        $this->assertNotContains($hiddenProduct->id, $ids);
    }

    public function test_end_company_region_falls_back_to_default_delivery_address(): void
    {
        $role = Role::query()->create([
            'slug' => Role::SLUG_END_COMPANY,
            'name' => 'Конечная компания',
            'sort_order' => 0,
        ]);
        $region = Region::factory()->create(['name' => 'Москва']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $profile = EndCompanyProfile::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Тестовая компания',
            'inn' => '7700000099',
        ]);
        EndCompanyDeliveryAddress::query()->create([
            'end_company_profile_id' => $profile->id,
            'name' => 'Склад',
            'address' => 'г. Москва, ул. Примерная, 1',
            'region_id' => $region->id,
            'is_default' => true,
        ]);

        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();
        $visibleProduct = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $this->linkProductToDistributorInRegion($visibleProduct, $region, $manufacturer);

        $user->load(['roles', 'endCompanyProfile.deliveryAddresses']);
        app(CurrentRoleService::class)->set($user, $role->id);

        $this->assertSame($region->id, $user->currentCompanyRegionId());
        $ids = (new CatalogQueryService($user))->visibleProductsQuery()->pluck('id')->all();
        $this->assertContains($visibleProduct->id, $ids);
    }

    public function test_visible_analogs_shown_without_attribute_values_when_linked_and_in_catalog(): void
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

        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $analog = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $product->analogs()->attach($analog->id);
        $analog->analogs()->attach($product->id);

        $this->linkProductToDistributorInRegion($product, $region, $manufacturer);
        $this->linkProductToDistributorInRegion($analog, $region, $manufacturer);

        $user->load('roles');
        app(CurrentRoleService::class)->set($user, $role->id);
        $catalog = new CatalogQueryService($user);

        $this->assertTrue($catalog->hasVisibleAnalogs($product));
        $this->assertSame([$analog->id], $catalog->resolveVisibleAnalogs($product)->pluck('id')->all());
    }

    public function test_category_tree_hides_branches_without_visible_products(): void
    {
        $role = Role::query()->create([
            'slug' => Role::SLUG_END_COMPANY,
            'name' => 'Конечная компания',
            'sort_order' => 0,
        ]);
        $region = Region::factory()->create(['name' => 'Санкт-Петербург']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['company_region' => $region->name]);

        $rootWithProducts = ProductCategory::factory()->create(['name' => 'With products']);
        $emptyRoot = ProductCategory::factory()->create(['name' => 'Empty root']);
        $manufacturer = ManufacturerProfile::factory()->create();

        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $rootWithProducts->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $this->linkProductToDistributorInRegion($product, $region, $manufacturer);

        $user->load('roles');
        app(CurrentRoleService::class)->set($user, $role->id);
        $tree = (new CatalogQueryService($user))->categoryTree();
        $slugs = $tree->pluck('slug')->all();

        $this->assertContains($rootWithProducts->slug, $slugs);
        $this->assertNotContains($emptyRoot->slug, $slugs);
    }

    public function test_in_category_includes_products_with_additional_category(): void
    {
        $mainCategory = ProductCategory::factory()->create(['name' => 'Main cat']);
        $extraCategory = ProductCategory::factory()->create(['name' => 'Extra cat']);
        $manufacturer = ManufacturerProfile::factory()->create();

        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $mainCategory->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $product->additionalCategories()->attach($extraCategory->id);

        $found = Product::query()
            ->visibleInCatalog()
            ->inCategory($extraCategory->id)
            ->pluck('id')
            ->all();

        $this->assertContains($product->id, $found);
    }

    public function test_product_search_matches_attribute_values(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Unrelated name',
            'sku' => 'SKU-UNIQUE-999',
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $attr = ProductAttribute::factory()->create([
            'product_category_id' => $category->id,
            'slug' => 'capacity-ah',
        ]);
        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'product_attribute_id' => $attr->id,
            'value' => 'ColdCrankUniqueValue',
        ]);

        $found = Product::query()->visibleInCatalog()->search('ColdCrankUniqueValue')->pluck('id')->all();
        $this->assertContains($product->id, $found);
    }

    private function linkProductToDistributorInRegion(
        Product $product,
        Region $region,
        ManufacturerProfile $manufacturer,
    ): DistributorProfile {
        $distributorUser = User::factory()->create();
        $distributorRole = Role::query()->firstOrCreate(
            ['slug' => Role::SLUG_DISTRIBUTOR],
            ['name' => 'Дистрибьютор', 'sort_order' => 1]
        );
        $distributorUser->roles()->attach($distributorRole->id, ['company_name' => 'Test Dist']);
        $distributorProfile = $distributorUser->getOrCreateDistributorProfile();
        $distributorProfile->regions()->sync([$region->id => ['is_primary' => true]]);

        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'distributor_profile_id' => $distributorProfile->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);

        DistributorProduct::query()->create([
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

        return $distributorProfile;
    }
}
