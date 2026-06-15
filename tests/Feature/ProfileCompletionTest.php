<?php

namespace Tests\Feature;

use App\Models\DistributorProfile;
use App\Models\EndCompanyDeliveryAddress;
use App\Models\EndCompanyProfile;
use App\Models\ManufacturerProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\DistributorProfileCompletionService;
use App\Services\EndCompanyProfileCompletionService;
use App\Services\ManufacturerProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_distributor_completion_tracks_partner_catalog_readiness(): void
    {
        $profile = $this->createDistributorProfile();

        $summary = app(DistributorProfileCompletionService::class)->summary($profile);

        $this->assertSame('warning', $summary['notice']['type']);
        $this->assertFalse($summary['is_complete']);

        $region = Region::factory()->create();
        $category = ProductCategory::factory()->create(['accepts_products' => true]);

        $profile->regions()->sync([$region->id => ['is_primary' => true]]);
        $profile->productCategories()->sync([$category->id]);
        $profile->contacts()->create([
            'full_name' => 'Менеджер',
            'email' => 'manager@test.com',
            'is_primary' => true,
        ]);

        $summary = app(DistributorProfileCompletionService::class)->summary($profile->fresh());

        $this->assertSame('info', $summary['notice']['type']);
        $this->assertTrue($summary['is_complete']);
    }

    public function test_manufacturer_completion_tracks_catalog_readiness(): void
    {
        $profile = ManufacturerProfile::factory()->create([
            'legal_address' => 'г. Москва',
            'short_name' => 'Завод',
        ]);

        $summary = app(ManufacturerProfileCompletionService::class)->summary($profile);

        $this->assertSame('warning', $summary['notice']['type']);
        $this->assertFalse($summary['is_complete']);

        $profile->contacts()->create([
            'full_name' => 'Менеджер',
            'email' => 'manager@test.com',
            'is_primary' => true,
        ]);
        $region = Region::factory()->create();
        $profile->regions()->sync([$region->id => ['is_primary' => true]]);
        Product::factory()->create(['manufacturer_profile_id' => $profile->id]);

        $summary = app(ManufacturerProfileCompletionService::class)->summary($profile->fresh());

        $this->assertSame('info', $summary['notice']['type']);
        $this->assertTrue($summary['is_complete']);
    }

    public function test_end_company_completion_tracks_order_readiness(): void
    {
        $profile = EndCompanyProfile::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'ООО «АвтоСервис»',
            'short_name' => 'АвтоСервис',
            'activity_type' => 'СТО',
            'inn' => '7700000002',
            'legal_address' => 'г. Москва',
        ]);

        $summary = app(EndCompanyProfileCompletionService::class)->summary($profile);

        $this->assertSame('warning', $summary['notice']['type']);
        $this->assertFalse($summary['is_complete']);

        $profile->contacts()->create([
            'full_name' => 'Менеджер',
            'email' => 'manager@test.com',
            'is_primary' => true,
        ]);
        $region = Region::factory()->create();
        EndCompanyDeliveryAddress::create([
            'end_company_profile_id' => $profile->id,
            'name' => 'Основной',
            'address' => 'г. Москва, ул. Центральная, 1',
            'region_id' => $region->id,
            'is_default' => true,
        ]);

        $summary = app(EndCompanyProfileCompletionService::class)->summary($profile->fresh());

        $this->assertSame('info', $summary['notice']['type']);
        $this->assertTrue($summary['is_complete']);
    }

    private function createDistributorProfile(): DistributorProfile
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'slug' => Role::SLUG_DISTRIBUTOR,
            'name' => 'Дистрибьютор',
            'sort_order' => 0,
        ]);
        $user->roles()->attach($role->id, ['company_name' => 'Тест Опт']);

        return DistributorProfile::create([
            'user_id' => $user->id,
            'full_name' => 'ООО «Тест Опт»',
            'short_name' => 'Тест Опт',
            'inn' => '7700000001',
            'legal_form' => DistributorProfile::LEGAL_FORM_OOO,
            'legal_address' => 'г. Москва',
        ]);
    }
}
