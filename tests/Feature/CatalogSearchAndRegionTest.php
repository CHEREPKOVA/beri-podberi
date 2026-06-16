<?php

namespace Tests\Feature;

use App\Models\EndCompanyDeliveryAddress;
use App\Models\EndCompanyProfile;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\ManufacturerProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Catalog\CatalogEmptyStateService;
use App\Services\Catalog\CatalogListingParams;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Catalog\CatalogRegionService;
use App\Services\CurrentRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSearchAndRegionTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_suggest_returns_matching_product(): void
    {
        [$user, $product] = $this->createManufacturerCatalogProduct('SuggestUniqueAlpha');

        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        $response = $this->actingAs($user)
            ->withSession(['current_role_id' => $user->roles->first()->id])
            ->getJson(route('manufacturer.catalog.search.suggest', ['q' => 'SuggestUniqueAlpha']));

        $response->assertOk();
        $response->assertJsonPath('products.0.name', fn (string $name): bool => str_contains($name, 'SuggestUniqueAlpha') && str_contains($name, '<mark'));
    }

    public function test_buyer_search_suggest_returns_products_for_distributor_role(): void
    {
        [$user, $product, $region] = $this->createDistributorPartnerCatalogProduct('BuyerSuggestBattery');

        $distributorRole = $user->roles()->where('slug', Role::SLUG_DISTRIBUTOR)->first();
        $this->assertNotNull($distributorRole);

        app(CurrentRoleService::class)->set($user, $distributorRole->id);

        $response = $this->actingAs($user)
            ->withSession([
                'current_role_id' => $distributorRole->id,
                'catalog_region_id' => $region->id,
            ])
            ->getJson(route('buyer.catalog.search.suggest', [
                'q' => 'BuyerSuggest',
                'search_scope' => CatalogListingParams::SEARCH_SCOPE_GLOBAL,
            ]));

        $response->assertOk();
        $response->assertJsonPath('products.0.name', fn (string $name): bool => str_contains($name, 'BuyerSuggest') && str_contains($name, '<mark'));
    }

    public function test_search_suggest_returns_popular_queries_when_query_is_short(): void
    {
        [$user, $product] = $this->createManufacturerCatalogProduct('PopularAlpha');

        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);

        app(\App\Services\Catalog\CatalogSearchLogService::class)->log(
            $user,
            $user->roles->first()->slug,
            null,
            'PopularAlpha',
            3,
        );

        $response = $this->actingAs($user)
            ->withSession(['current_role_id' => $user->roles->first()->id])
            ->getJson(route('manufacturer.catalog.search.suggest', ['q' => '']));

        $response->assertOk();
        $response->assertJsonPath('popular.0.query', 'PopularAlpha');
    }

    public function test_search_suggest_respects_configured_suggest_limit(): void
    {
        [$user, $product] = $this->createManufacturerCatalogProduct('SuggestLimitBase');
        $profileId = $product->manufacturer_profile_id;
        $categoryId = $product->category_id;

        foreach (range(1, 6) as $i) {
            Product::factory()->create([
                'manufacturer_profile_id' => $profileId,
                'category_id' => $categoryId,
                'name' => 'SuggestLimit '.$i,
                'show_in_catalog' => true,
                'status' => Product::STATUS_ACTIVE,
            ]);
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => 'catalog.search_suggest_limit'],
            [
                'group_key' => 'catalog',
                'label' => 'Catalog suggest limit',
                'value_type' => 'integer',
                'value' => '3',
                'is_active' => true,
                'sort_order' => 999,
            ],
        );

        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        $response = $this->actingAs($user)
            ->withSession(['current_role_id' => $user->roles->first()->id])
            ->getJson(route('manufacturer.catalog.search.suggest', ['q' => 'SuggestLimit']));

        $response->assertOk();
        $this->assertCount(3, $response->json('products'));
    }

    public function test_empty_state_suggests_categories_for_global_search(): void
    {
        [$user, $product] = $this->createManufacturerCatalogProduct('EmptyStateWidget');
        $category = $product->category;

        app(CurrentRoleService::class)->set($user, $user->roles->first()->id);
        $catalog = new CatalogQueryService($user);

        $empty = (new CatalogEmptyStateService($catalog))->build(
            null,
            new CatalogListingParams(search: 'EmptyStateWidget', searchScope: CatalogListingParams::SEARCH_SCOPE_GLOBAL),
            'manufacturer.catalog.index',
            'manufacturer.catalog.show',
        );

        $this->assertTrue($empty['show_reset']);
        $this->assertSame($category->slug, $empty['suggested_categories'][0]['slug'] ?? null);
    }

    public function test_catalog_region_service_uses_session_override_for_end_company(): void
    {
        $role = Role::query()->create([
            'slug' => Role::SLUG_END_COMPANY,
            'name' => 'Конечная компания',
            'sort_order' => 0,
        ]);
        $regionA = Region::factory()->create(['name' => 'Москва']);
        $regionB = Region::factory()->create(['name' => 'Казань']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $profile = EndCompanyProfile::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Тест',
            'inn' => '7700000011',
        ]);
        foreach ([$regionA, $regionB] as $i => $region) {
            EndCompanyDeliveryAddress::query()->create([
                'end_company_profile_id' => $profile->id,
                'name' => 'Адрес '.$region->name,
                'address' => 'ул. Тест, 1',
                'region_id' => $region->id,
                'is_default' => $i === 0,
            ]);
        }

        $user->load(['roles', 'endCompanyProfile.deliveryAddresses']);
        app(CurrentRoleService::class)->set($user, $role->id);
        $service = new CatalogRegionService();

        $this->assertSame($regionA->id, $service->resolveRegionId($user));

        $this->assertTrue($service->setRegionId($user, $regionB->id));
        $this->assertSame($regionB->id, $service->resolveRegionId($user));
    }

    public function test_set_region_endpoint_updates_catalog_context(): void
    {
        $role = Role::query()->create([
            'slug' => Role::SLUG_END_COMPANY,
            'name' => 'Конечная компания',
            'sort_order' => 0,
        ]);
        $regionA = Region::factory()->create(['name' => 'Москва']);
        $regionB = Region::factory()->create(['name' => 'СПб']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['company_region' => $regionA->name]);

        $profile = EndCompanyProfile::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Тест',
            'inn' => '7700000012',
        ]);
        EndCompanyDeliveryAddress::query()->create([
            'end_company_profile_id' => $profile->id,
            'name' => 'Склад СПб',
            'address' => 'Невский, 1',
            'region_id' => $regionB->id,
            'is_default' => false,
        ]);
        EndCompanyDeliveryAddress::query()->create([
            'end_company_profile_id' => $profile->id,
            'name' => 'Склад Мск',
            'address' => 'Тверская, 1',
            'region_id' => $regionA->id,
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['current_role_id' => $role->id])
            ->postJson(route('buyer.catalog.region'), ['region_id' => $regionB->id])
            ->assertOk();

        $this->assertSame($regionB->id, (new CatalogRegionService())->resolveRegionId($user->fresh()));
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function createManufacturerCatalogProduct(string $name): array
    {
        $role = Role::query()->create([
            'slug' => Role::SLUG_MANUFACTURER,
            'name' => 'Производитель',
            'sort_order' => 0,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $profile = ManufacturerProfile::factory()->create(['user_id' => $user->id]);
        $category = ProductCategory::factory()->create(['name' => 'Категория '.$name]);
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $profile->id,
            'category_id' => $category->id,
            'name' => $name,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $user->load('roles');

        return [$user, $product];
    }

    /**
     * @return array{0: User, 1: Product, 2: Region}
     */
    private function createDistributorPartnerCatalogProduct(string $name): array
    {
        $region = Region::factory()->create(['name' => 'Регион '.$name]);
        $manufacturerRole = Role::query()->create([
            'slug' => Role::SLUG_MANUFACTURER,
            'name' => 'Производитель',
            'sort_order' => 0,
        ]);
        $distributorRole = Role::query()->create([
            'slug' => Role::SLUG_DISTRIBUTOR,
            'name' => 'Дистрибьютор',
            'sort_order' => 1,
        ]);
        $manufacturerUser = User::factory()->create();
        $manufacturerUser->roles()->attach($manufacturerRole->id);
        $manufacturer = ManufacturerProfile::factory()->create(['user_id' => $manufacturerUser->id]);
        $manufacturer->regions()->sync([$region->id]);

        $distributorUser = User::factory()->create();
        $distributorUser->roles()->attach($distributorRole->id, ['company_name' => 'Дист '.$name]);
        $distributorProfile = $distributorUser->getOrCreateDistributorProfile();
        $distributorProfile->regions()->sync([$region->id => ['is_primary' => true]]);

        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'distributor_profile_id' => $distributorProfile->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);

        $category = ProductCategory::factory()->create(['name' => 'Категория '.$name]);
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'name' => $name,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $product->availableRegions()->sync([$region->id]);
        $distributorUser->load('roles');

        return [$distributorUser, $product, $region];
    }
}
