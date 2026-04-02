<?php

namespace Database\Seeders;

use App\Models\UnitType;
use Illuminate\Database\Seeder;

class UnitTypeSeeder extends Seeder
{
    /**
     * Единицы измерения для автоаксессуаров (всё на русском).
     */
    public function run(): void
    {
        $units = [
            ['name' => 'Штука', 'short_name' => 'шт.', 'code' => 'pcs'],
            ['name' => 'Упаковка', 'short_name' => 'уп.', 'code' => 'pack'],
            ['name' => 'Литр', 'short_name' => 'л', 'code' => 'l'],
            ['name' => 'Миллилитр', 'short_name' => 'мл', 'code' => 'ml'],
            ['name' => 'Килограмм', 'short_name' => 'кг', 'code' => 'kg'],
            ['name' => 'Канистра', 'short_name' => 'кан.', 'code' => 'can'],
            ['name' => 'Комплект', 'short_name' => 'компл.', 'code' => 'set'],
            ['name' => 'Коробка', 'short_name' => 'кор.', 'code' => 'box'],
            ['name' => 'Палета', 'short_name' => 'пал.', 'code' => 'pallet'],
        ];

        foreach ($units as $unit) {
            UnitType::firstOrCreate(
                ['code' => $unit['code']],
                [
                    'name' => $unit['name'],
                    'short_name' => $unit['short_name'],
                    'is_active' => true,
                ]
            );
        }
    }
}
