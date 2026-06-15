<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCatalogProductTzTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function adminUser(): User
    {
        $adminRole = Role::query()->where('slug', Role::SLUG_ADMIN)->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->sync([$adminRole->id]);

        return $user;
    }

    public function test_admin_can_update_product_attributes_without_changing_price(): void
    {
        $admin = $this->adminUser();
        $category = ProductCategory::factory()->create(['accepts_products' => true]);
        $attribute = ProductAttribute::factory()->create([
            'product_category_id' => $category->id,
            'name' => 'Мощность',
            'type' => ProductAttribute::TYPE_TEXT,
            'is_required' => true,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price' => 1500.50,
            'description' => 'Старое описание',
        ]);

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles->first()->id])
            ->put(route('admin.catalog.products.update', $product), [
                'tab' => 'attributes',
                'name' => 'Обновлённое название',
                'category_id' => $category->id,
                'description' => 'Новое описание администратора',
                'status' => Product::STATUS_ACTIVE,
                'attributes' => [
                    $attribute->id => '100 Вт',
                ],
            ])
            ->assertRedirect(route('admin.catalog.products.edit', ['product' => $product, 'tab' => 'attributes']));

        $product->refresh();
        $this->assertSame('Обновлённое название', $product->name);
        $this->assertSame('Новое описание администратора', $product->description);
        $this->assertSame('1500.50', (string) $product->base_price);

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->id,
            'product_attribute_id' => $attribute->id,
            'value' => '100 Вт',
        ]);
    }

    public function test_admin_analog_update_rejects_incompatible_products(): void
    {
        $admin = $this->adminUser();
        $categoryA = ProductCategory::factory()->create(['accepts_products' => true]);
        $categoryB = ProductCategory::factory()->create(['accepts_products' => true]);

        $product = Product::factory()->create(['category_id' => $categoryA->id]);
        $incompatible = Product::factory()->create(['category_id' => $categoryB->id]);

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles->first()->id])
            ->put(route('admin.catalog.analogs.update', $product), [
                'analog_ids' => [$incompatible->id],
            ])
            ->assertSessionHasErrors('analog_ids');
    }

    public function test_quality_page_links_to_edit_tabs(): void
    {
        $admin = $this->adminUser();
        $category = ProductCategory::factory()->create(['accepts_products' => true]);
        $attribute = ProductAttribute::factory()->create([
            'product_category_id' => $category->id,
            'is_required' => true,
            'is_active' => true,
        ]);

        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles->first()->id])
            ->get(route('admin.catalog.quality'))
            ->assertOk()
            ->assertSee(route('admin.catalog.products.edit', ['product' => $product, 'tab' => 'attributes']), false);
    }
}
