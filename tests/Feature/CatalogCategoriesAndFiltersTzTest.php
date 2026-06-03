<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductCategory;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogCategoriesAndFiltersTzTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_category_excludes_inherited_attributes_when_disabled_on_child(): void
    {
        $parent = ProductCategory::factory()->create();
        $child = ProductCategory::factory()->create(['parent_id' => $parent->id]);
        $parentAttr = ProductAttribute::factory()->forCategory($parent)->create(['slug' => 'parent-attr', 'is_active' => true]);

        $global = ProductAttribute::factory()->create([
            'product_category_id' => null,
            'slug' => 'global-attr',
            'is_active' => true,
        ]);

        $before = ProductAttribute::active()->forCategory($child->id)->pluck('slug')->sort()->values()->all();
        $this->assertContains('parent-attr', $before);
        $this->assertContains('global-attr', $before);

        $child->excludedAttributes()->sync([$parentAttr->id]);

        $after = ProductAttribute::active()->forCategory($child->id)->pluck('slug')->sort()->values()->all();
        $this->assertNotContains('parent-attr', $after);
        $this->assertContains('global-attr', $after);
    }

    public function test_get_tree_hides_role_restricted_categories_from_other_roles(): void
    {
        $roleA = Role::query()->create([
            'slug' => 'tz-test-a-'.uniqid(),
            'name' => 'TZ A',
            'sort_order' => 0,
        ]);
        $roleB = Role::query()->create([
            'slug' => 'tz-test-b-'.uniqid(),
            'name' => 'TZ B',
            'sort_order' => 0,
        ]);

        $open = ProductCategory::factory()->create([
            'name' => 'Open root',
            'slug' => 'open-root-'.uniqid(),
            'restrict_catalog_by_roles' => false,
        ]);
        $closed = ProductCategory::factory()->create([
            'name' => 'Closed root',
            'slug' => 'closed-root-'.uniqid(),
            'restrict_catalog_by_roles' => true,
        ]);
        $closed->catalogRoles()->sync([$roleA->id]);

        $treeForB = ProductCategory::getTree(false, null, $roleB);
        $treeForA = ProductCategory::getTree(false, null, $roleA);

        $slugsB = $treeForB->pluck('slug')->all();
        $this->assertContains($open->slug, $slugsB);
        $this->assertNotContains($closed->slug, $slugsB);
        $this->assertContains($closed->slug, $treeForA->pluck('slug')->all());
    }

    public function test_attribute_linked_to_multiple_categories_appears_in_each_branch(): void
    {
        $batteries = ProductCategory::factory()->create(['name' => 'Аккумуляторы TZ']);
        $fluids = ProductCategory::factory()->create(['name' => 'Масла TZ']);
        $attr = ProductAttribute::factory()->forCategories([$batteries, $fluids])->create([
            'slug' => 'multi-cat-attr',
            'is_active' => true,
        ]);

        $batterySlugs = ProductAttribute::active()->forCategory($batteries->id)->pluck('slug')->all();
        $fluidSlugs = ProductAttribute::active()->forCategory($fluids->id)->pluck('slug')->all();

        $this->assertContains('multi-cat-attr', $batterySlugs);
        $this->assertContains('multi-cat-attr', $fluidSlugs);
        $this->assertCount(2, $attr->categories);
    }

    public function test_attribute_numeric_range_filter_on_products(): void
    {
        $category = ProductCategory::factory()->create();
        $attr = ProductAttribute::factory()->create([
            'product_category_id' => $category->id,
            'type' => ProductAttribute::TYPE_NUMBER,
            'slug' => 'capacity',
            'is_filterable' => true,
            'filter_display_type' => ProductAttribute::FILTER_DISPLAY_RANGE,
        ]);

        $p1 = Product::factory()->create(['category_id' => $category->id]);
        $p2 = Product::factory()->create(['category_id' => $category->id]);
        ProductAttributeValue::query()->create(['product_id' => $p1->id, 'product_attribute_id' => $attr->id, 'value' => '50']);
        ProductAttributeValue::query()->create(['product_id' => $p2->id, 'product_attribute_id' => $attr->id, 'value' => '90']);

        $q = Product::query()->withAttributeFilters([$attr->id => ['min' => '60', 'max' => '']]);

        $this->assertEquals([$p2->id], $q->pluck('id')->all());
    }
}
