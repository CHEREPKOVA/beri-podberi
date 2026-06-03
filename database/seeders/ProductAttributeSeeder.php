<?php

namespace Database\Seeders;

use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductAttributeSeeder extends Seeder
{
    /**
     * Атрибуты для каталога автоаксессуаров: аккумуляторы, зарядные устройства, масла и жидкости.
     * Общие поля — без категории; остальные привязаны к родительской ветке каталога.
     */
    public function run(): void
    {
        $batteriesId = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('name', 'Аккумуляторы')
            ->value('id');
        $chargersId = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('name', 'Зарядные устройства для авто')
            ->value('id');
        $fluidsId = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('name', 'Масла и жидкости')
            ->value('id');

        $attributes = [
            [
                'name' => 'Бренд',
                'type' => 'text',
                'is_filterable' => true,
                'category_id' => null,
            ],
            [
                'name' => 'Страна производства',
                'type' => 'select',
                'options' => ['Россия', 'Китай', 'Германия', 'Корея', 'Япония', 'США', 'Италия', 'Франция', 'Турция', 'Другая'],
                'is_filterable' => true,
                'category_id' => null,
            ],
            [
                'name' => 'Гарантия (мес.)',
                'type' => 'number',
                'is_filterable' => true,
                'category_id' => null,
            ],
            [
                'name' => 'Вес (кг)',
                'type' => 'number',
                'is_filterable' => false,
                'category_id' => null,
            ],
            [
                'name' => 'Напряжение (В)',
                'type' => 'select',
                'options' => ['6', '12', '24'],
                'is_filterable' => true,
                'category_id' => $batteriesId,
            ],
            [
                'name' => 'Ёмкость (А·ч)',
                'type' => 'number',
                'is_filterable' => true,
                'category_id' => $batteriesId,
            ],
            [
                'name' => 'Полярность',
                'type' => 'select',
                'options' => ['Прямая', 'Обратная'],
                'is_filterable' => true,
                'category_id' => $batteriesId,
            ],
            [
                'name' => 'Тип аккумулятора',
                'type' => 'select',
                'options' => ['WET (сурьмянистый/малообслуживаемый)', 'AGM', 'EFB', 'GEL', 'Литий-ионный'],
                'is_filterable' => true,
                'category_id' => $batteriesId,
            ],
            [
                'name' => 'Ток холодной прокрутки (А)',
                'type' => 'number',
                'is_filterable' => true,
                'category_id' => $batteriesId,
            ],
            [
                'name' => 'Габариты (Д×Ш×В, мм)',
                'type' => 'text',
                'is_filterable' => false,
                'category_id' => $batteriesId,
            ],
            [
                'name' => 'Макс. ток зарядки (А)',
                'type' => 'number',
                'is_filterable' => false,
                'category_id' => $chargersId,
            ],
            [
                'name' => 'Тип масла',
                'type' => 'select',
                'options' => ['Синтетика', 'Полусинтетика', 'Минеральное'],
                'is_filterable' => true,
                'category_id' => $fluidsId,
            ],
            [
                'name' => 'Вязкость (SAE)',
                'type' => 'select',
                'options' => ['0W-20', '0W-30', '5W-30', '5W-40', '10W-40', '15W-40', '20W-50', '75W-90', '80W-90', '85W-90', 'Другая'],
                'is_filterable' => true,
                'category_id' => $fluidsId,
            ],
            [
                'name' => 'Класс API',
                'type' => 'text',
                'is_filterable' => false,
                'category_id' => $fluidsId,
            ],
            [
                'name' => 'Спецификация ACEA',
                'type' => 'text',
                'is_filterable' => true,
                'category_id' => $fluidsId,
            ],
            [
                'name' => 'Объём (л)',
                'type' => 'number',
                'is_filterable' => true,
                'category_id' => $fluidsId,
            ],
            [
                'name' => 'Класс тормозной жидкости',
                'type' => 'select',
                'options' => ['DOT 3', 'DOT 4', 'DOT 5', 'DOT 5.1'],
                'is_filterable' => true,
                'category_id' => $fluidsId,
            ],
            [
                'name' => 'Температура замерзания (°C)',
                'type' => 'number',
                'is_filterable' => true,
                'category_id' => $fluidsId,
            ],
        ];

        foreach ($attributes as $index => $attr) {
            $categoryId = $attr['category_id'];
            $slug = Str::slug($attr['name']);

            $attribute = ProductAttribute::updateOrCreate(
                ['slug' => $slug, 'product_id' => null],
                [
                    'name' => $attr['name'],
                    'type' => $attr['type'],
                    'options' => $attr['options'] ?? null,
                    'is_filterable' => $attr['is_filterable'],
                    'filter_values_source' => ProductAttribute::FILTER_VALUES_FIXED,
                    'filter_allow_multiple' => false,
                    'is_required' => false,
                    'sort_order' => $index,
                    'is_active' => true,
                    'product_category_id' => $categoryId,
                ]
            );
            $attribute->syncCatalogCategories($categoryId ? [$categoryId] : []);
        }

        // Удалить устаревшие дубликаты «глобальных» записей с тем же slug, если категория уже назначена.
        $assignedSlugs = collect($attributes)
            ->filter(fn (array $attr) => $attr['category_id'] !== null)
            ->map(fn (array $attr) => Str::slug($attr['name']))
            ->all();

        if ($assignedSlugs !== []) {
            ProductAttribute::query()
                ->whereNull('product_category_id')
                ->whereNull('product_id')
                ->whereIn('slug', $assignedSlugs)
                ->delete();
        }
    }
}
