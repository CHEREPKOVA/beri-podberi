<?php

namespace Database\Seeders;

use App\Models\ProductAttribute;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductAttributeSeeder extends Seeder
{
    /**
     * Атрибуты для каталога автоаксессуаров: аккумуляторы, зарядные устройства, масла и жидкости.
     */
    public function run(): void
    {
        $attributes = [
            [
                'name' => 'Бренд',
                'type' => 'text',
                'is_filterable' => true,
            ],
            [
                'name' => 'Страна производства',
                'type' => 'select',
                'options' => ['Россия', 'Китай', 'Германия', 'Корея', 'Япония', 'США', 'Италия', 'Франция', 'Турция', 'Другая'],
                'is_filterable' => true,
            ],
            [
                'name' => 'Гарантия (мес.)',
                'type' => 'number',
                'is_filterable' => true,
            ],
            [
                'name' => 'Напряжение (В)',
                'type' => 'select',
                'options' => ['6', '12', '24'],
                'is_filterable' => true,
            ],
            [
                'name' => 'Ёмкость (А·ч)',
                'type' => 'number',
                'is_filterable' => true,
            ],
            [
                'name' => 'Полярность',
                'type' => 'select',
                'options' => ['Прямая', 'Обратная'],
                'is_filterable' => true,
            ],
            [
                'name' => 'Тип аккумулятора',
                'type' => 'select',
                'options' => ['WET (сурьмянистый/малообслуживаемый)', 'AGM', 'EFB', 'GEL', 'Литий-ионный'],
                'is_filterable' => true,
            ],
            [
                'name' => 'Ток холодной прокрутки (А)',
                'type' => 'number',
                'is_filterable' => true,
            ],
            [
                'name' => 'Макс. ток зарядки (А)',
                'type' => 'number',
                'is_filterable' => false,
            ],
            [
                'name' => 'Тип масла',
                'type' => 'select',
                'options' => ['Синтетика', 'Полусинтетика', 'Минеральное'],
                'is_filterable' => true,
            ],
            [
                'name' => 'Вязкость (SAE)',
                'type' => 'select',
                'options' => ['0W-20', '0W-30', '5W-30', '5W-40', '10W-40', '15W-40', '20W-50', '75W-90', '80W-90', '85W-90', 'Другая'],
                'is_filterable' => true,
            ],
            [
                'name' => 'Класс API',
                'type' => 'text',
                'is_filterable' => false,
            ],
            [
                'name' => 'Объём (л)',
                'type' => 'number',
                'is_filterable' => true,
            ],
            [
                'name' => 'Класс тормозной жидкости',
                'type' => 'select',
                'options' => ['DOT 3', 'DOT 4', 'DOT 5', 'DOT 5.1'],
                'is_filterable' => true,
            ],
            [
                'name' => 'Температура замерзания (°C)',
                'type' => 'number',
                'is_filterable' => true,
            ],
            [
                'name' => 'Вес (кг)',
                'type' => 'number',
                'is_filterable' => false,
            ],
            [
                'name' => 'Новинка',
                'type' => 'boolean',
                'is_filterable' => true,
            ],
            [
                'name' => 'Хит продаж',
                'type' => 'boolean',
                'is_filterable' => true,
            ],
        ];

        foreach ($attributes as $index => $attr) {
            ProductAttribute::firstOrCreate(
                ['product_category_id' => null, 'slug' => Str::slug($attr['name'])],
                [
                    'name' => $attr['name'],
                    'type' => $attr['type'],
                    'options' => $attr['options'] ?? null,
                    'is_filterable' => $attr['is_filterable'],
                    'is_required' => false,
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );
        }
    }
}
