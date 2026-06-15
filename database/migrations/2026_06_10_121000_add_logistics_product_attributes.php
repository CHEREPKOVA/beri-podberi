<?php

use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = [
            ['name' => 'Вес, кг', 'slug' => 'ves-kg', 'sort_order' => 200],
            ['name' => 'Объём, л', 'slug' => 'obem-l', 'sort_order' => 201],
            ['name' => 'Количество на паллете', 'slug' => 'kolichestvo-na-pallete', 'sort_order' => 202],
            ['name' => 'Рядность паллет', 'slug' => 'ryadnost-pallet', 'sort_order' => 203],
            ['name' => 'Количество в упаковке', 'slug' => 'kolichestvo-v-upakovke', 'sort_order' => 204],
        ];

        $categoryIds = ProductCategory::query()
            ->where('accepts_products', true)
            ->pluck('id');

        foreach ($categoryIds as $index => $categoryId) {
            foreach ($definitions as $def) {
                ProductAttribute::query()->updateOrCreate(
                    [
                        'slug' => $def['slug'],
                        'product_category_id' => $categoryId,
                    ],
                    [
                        'name' => $def['name'],
                        'type' => ProductAttribute::TYPE_TEXT,
                        'is_filterable' => false,
                        'filter_values_source' => ProductAttribute::FILTER_VALUES_FIXED,
                        'filter_allow_multiple' => false,
                        'is_required' => false,
                        'sort_order' => $def['sort_order'] + $index,
                        'is_active' => true,
                        'product_id' => null,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        ProductAttribute::query()
            ->whereNull('product_id')
            ->whereIn('slug', [
                'ves-kg',
                'obem-l',
                'kolichestvo-na-pallete',
                'ryadnost-pallet',
                'kolichestvo-v-upakovke',
            ])
            ->delete();
    }
};
