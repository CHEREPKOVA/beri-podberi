<?php

namespace Database\Seeders;

use App\Models\DeliveryMethod;
use Illuminate\Database\Seeder;

class DeliveryMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Самовывоз',
                'slug' => 'self_pickup',
                'description' => 'Самостоятельный вывоз товара со склада производителя',
                'requires_tracking' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Доставка транспортной компанией',
                'slug' => 'transport_company',
                'description' => 'Доставка через транспортную компанию с возможностью отслеживания',
                'requires_tracking' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Доставка собственным транспортом',
                'slug' => 'own_transport',
                'description' => 'Доставка силами производителя',
                'requires_tracking' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($methods as $method) {
            DeliveryMethod::updateOrCreate(
                ['slug' => $method['slug']],
                [
                    ...$method,
                    'is_active' => true,
                ]
            );
        }
    }
}
