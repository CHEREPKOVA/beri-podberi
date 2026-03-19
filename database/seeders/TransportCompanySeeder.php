<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransportCompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'name' => 'СДЭК',
                'slug' => 'cdek',
                'website' => 'https://www.cdek.ru',
                'tracking_url' => 'https://www.cdek.ru/ru/tracking?order_id=',
                'sort_order' => 1,
            ],
            [
                'name' => 'Деловые Линии',
                'slug' => 'dellin',
                'website' => 'https://www.dellin.ru',
                'tracking_url' => 'https://www.dellin.ru/tracker/?docid=',
                'sort_order' => 2,
            ],
            [
                'name' => 'ПЭК',
                'slug' => 'pek',
                'website' => 'https://pecom.ru',
                'tracking_url' => 'https://pecom.ru/services-and-tariffs/tracking/?code=',
                'sort_order' => 3,
            ],
            [
                'name' => 'Байкал Сервис',
                'slug' => 'baikal',
                'website' => 'https://www.baikalsr.ru',
                'tracking_url' => 'https://www.baikalsr.ru/tracking/',
                'sort_order' => 4,
            ],
            [
                'name' => 'Энергия',
                'slug' => 'energy',
                'website' => 'https://nrg-tk.ru',
                'tracking_url' => 'https://nrg-tk.ru/client/tracking/',
                'sort_order' => 5,
            ],
            [
                'name' => 'Желдорэкспедиция',
                'slug' => 'jde',
                'website' => 'https://www.jde.ru',
                'tracking_url' => 'https://www.jde.ru/tracking/',
                'sort_order' => 6,
            ],
            [
                'name' => 'КИТ',
                'slug' => 'kit',
                'website' => 'https://tkkit.ru',
                'tracking_url' => 'https://tkkit.ru/tracking/',
                'sort_order' => 7,
            ],
            [
                'name' => 'Boxberry',
                'slug' => 'boxberry',
                'website' => 'https://boxberry.ru',
                'tracking_url' => 'https://boxberry.ru/tracking-page?id=',
                'sort_order' => 8,
            ],
            [
                'name' => 'DPD',
                'slug' => 'dpd',
                'website' => 'https://www.dpd.ru',
                'tracking_url' => 'https://www.dpd.ru/ols/trace.do?id=',
                'sort_order' => 9,
            ],
            [
                'name' => 'Почта России',
                'slug' => 'pochta',
                'website' => 'https://www.pochta.ru',
                'tracking_url' => 'https://www.pochta.ru/tracking#',
                'sort_order' => 10,
            ],
        ];

        $now = now();

        foreach ($companies as $company) {
            DB::table('transport_companies')->insert([
                ...$company,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
