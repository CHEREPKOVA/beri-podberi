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
            ],
            [
                'name' => 'Деловые Линии',
                'slug' => 'dellin',
                'website' => 'https://www.dellin.ru',
                'tracking_url' => 'https://www.dellin.ru/tracker/?docid=',
            ],
            [
                'name' => 'ПЭК',
                'slug' => 'pek',
                'website' => 'https://pecom.ru',
                'tracking_url' => 'https://pecom.ru/services-and-tariffs/tracking/?code=',
            ],
            [
                'name' => 'Байкал Сервис',
                'slug' => 'baikal',
                'website' => 'https://www.baikalsr.ru',
                'tracking_url' => 'https://www.baikalsr.ru/tracking/',
            ],
            [
                'name' => 'Энергия',
                'slug' => 'energy',
                'website' => 'https://nrg-tk.ru',
                'tracking_url' => 'https://nrg-tk.ru/client/tracking/',
            ],
            [
                'name' => 'Желдорэкспедиция',
                'slug' => 'jde',
                'website' => 'https://www.jde.ru',
                'tracking_url' => 'https://www.jde.ru/tracking/',
            ],
            [
                'name' => 'КИТ',
                'slug' => 'kit',
                'website' => 'https://tkkit.ru',
                'tracking_url' => 'https://tkkit.ru/tracking/',
            ],
            [
                'name' => 'Boxberry',
                'slug' => 'boxberry',
                'website' => 'https://boxberry.ru',
                'tracking_url' => 'https://boxberry.ru/tracking-page?id=',
            ],
            [
                'name' => 'DPD',
                'slug' => 'dpd',
                'website' => 'https://www.dpd.ru',
                'tracking_url' => 'https://www.dpd.ru/ols/trace.do?id=',
            ],
            [
                'name' => 'Почта России',
                'slug' => 'pochta',
                'website' => 'https://www.pochta.ru',
                'tracking_url' => 'https://www.pochta.ru/tracking#',
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
