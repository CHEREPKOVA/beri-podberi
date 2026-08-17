<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Policies\ProductPolicy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_manufacturer_can_manage_own_product_only(): void
    {
        $ownerRole = Role::query()->where('slug', Role::SLUG_MANUFACTURER)->firstOrFail();
        $otherRole = Role::query()->where('slug', Role::SLUG_MANUFACTURER)->firstOrFail();

        $owner = User::factory()->create();
        $owner->roles()->sync([$ownerRole->id]);
        $ownerProfile = $owner->getOrCreateManufacturerProfile();

        $other = User::factory()->create();
        $other->roles()->sync([$otherRole->id]);
        $otherProfile = $other->getOrCreateManufacturerProfile();

        $ownProduct = Product::query()->create([
            'manufacturer_profile_id' => $ownerProfile->id,
            'name' => 'Свой товар',
            'sku' => 'OWN-1',
            'status' => Product::STATUS_DRAFT,
        ]);

        $foreignProduct = Product::query()->create([
            'manufacturer_profile_id' => $otherProfile->id,
            'name' => 'Чужой товар',
            'sku' => 'FOREIGN-1',
            'status' => Product::STATUS_DRAFT,
        ]);

        $policy = new ProductPolicy;

        $this->assertTrue($policy->manage($owner, $ownProduct));
        $this->assertFalse($policy->manage($owner, $foreignProduct));
    }
}
