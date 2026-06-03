<?php

use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $batteriesId = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('name', 'Аккумуляторы')
            ->value('id');
        $fluidsId = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('name', 'Масла и жидкости')
            ->value('id');

        $definitions = [
            [
                'name' => 'Габариты (Д×Ш×В, мм)',
                'slug' => 'gabarity-dshv-mm',
                'type' => 'text',
                'is_filterable' => false,
                'product_category_id' => $batteriesId,
            ],
            [
                'name' => 'Спецификация ACEA',
                'slug' => 'specifikaciya-acea',
                'type' => 'text',
                'is_filterable' => true,
                'product_category_id' => $fluidsId,
            ],
        ];

        foreach ($definitions as $index => $def) {
            if ($def['product_category_id'] === null) {
                continue;
            }

            ProductAttribute::query()->updateOrCreate(
                [
                    'slug' => $def['slug'],
                    'product_category_id' => $def['product_category_id'],
                ],
                [
                    'name' => $def['name'],
                    'type' => $def['type'],
                    'is_filterable' => $def['is_filterable'],
                    'filter_values_source' => ProductAttribute::FILTER_VALUES_FIXED,
                    'filter_allow_multiple' => false,
                    'is_required' => false,
                    'sort_order' => 100 + $index,
                    'is_active' => true,
                    'product_id' => null,
                ]
            );
        }
    }

    public function down(): void
    {
        ProductAttribute::query()
            ->whereNull('product_id')
            ->whereIn('slug', ['gabarity-dshv-mm', 'specifikaciya-acea'])
            ->delete();
    }
};
