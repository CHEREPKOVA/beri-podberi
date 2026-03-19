<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCategorySeeder extends Seeder
{
    /**
     * Каталог производителей автоаксессуаров: аккумуляторы, зарядные устройства, масла и жидкости.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Аккумуляторы',
                'description' => 'Автомобильные аккумуляторы и АКБ',
                'children' => [
                    'Аккумуляторы для легковых автомобилей',
                    'Аккумуляторы для грузовиков',
                    'Аккумуляторы для мототехники',
                    'Аккумуляторы для ИБП и тележек',
                ],
            ],
            [
                'name' => 'Зарядные устройства для авто',
                'description' => 'Зарядные и пуско-зарядные устройства',
                'children' => [
                    'Зарядные устройства',
                    'Пуско-зарядные устройства',
                    'Держатели и кабели для зарядки',
                ],
            ],
            [
                'name' => 'Масла и жидкости',
                'description' => 'Моторные и трансмиссионные масла, жидкости',
                'children' => [
                    'Моторные масла',
                    'Трансмиссионные масла',
                    'Тормозная жидкость',
                    'Охлаждающая жидкость',
                    'Жидкость омывателя',
                ],
            ],
        ];

        $sortOrder = 0;
        foreach ($categories as $categoryData) {
            $parentSlug = Str::slug($categoryData['name']);
            $parent = ProductCategory::firstOrCreate(
                ['slug' => $parentSlug],
                [
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'] ?? null,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                ]
            );
            $sortOrder++;

            $childSortOrder = 0;
            foreach ($categoryData['children'] as $childName) {
                $childSlug = Str::slug($childName);
                if (ProductCategory::where('slug', $childSlug)->where('parent_id', '!=', $parent->id)->exists()) {
                    $childSlug = $parentSlug . '-' . $childSlug;
                }
                ProductCategory::firstOrCreate(
                    ['slug' => $childSlug],
                    [
                        'name' => $childName,
                        'parent_id' => $parent->id,
                        'sort_order' => $childSortOrder,
                        'is_active' => true,
                    ]
                );
                $childSortOrder++;
            }
        }
    }
}
