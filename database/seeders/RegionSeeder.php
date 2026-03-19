<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            // Центральный федеральный округ
            ['name' => 'Белгородская область', 'code' => '31', 'federal_district' => 'Центральный'],
            ['name' => 'Брянская область', 'code' => '32', 'federal_district' => 'Центральный'],
            ['name' => 'Владимирская область', 'code' => '33', 'federal_district' => 'Центральный'],
            ['name' => 'Воронежская область', 'code' => '36', 'federal_district' => 'Центральный'],
            ['name' => 'Ивановская область', 'code' => '37', 'federal_district' => 'Центральный'],
            ['name' => 'Калужская область', 'code' => '40', 'federal_district' => 'Центральный'],
            ['name' => 'Костромская область', 'code' => '44', 'federal_district' => 'Центральный'],
            ['name' => 'Курская область', 'code' => '46', 'federal_district' => 'Центральный'],
            ['name' => 'Липецкая область', 'code' => '48', 'federal_district' => 'Центральный'],
            ['name' => 'Москва', 'code' => '77', 'federal_district' => 'Центральный'],
            ['name' => 'Московская область', 'code' => '50', 'federal_district' => 'Центральный'],
            ['name' => 'Орловская область', 'code' => '57', 'federal_district' => 'Центральный'],
            ['name' => 'Рязанская область', 'code' => '62', 'federal_district' => 'Центральный'],
            ['name' => 'Смоленская область', 'code' => '67', 'federal_district' => 'Центральный'],
            ['name' => 'Тамбовская область', 'code' => '68', 'federal_district' => 'Центральный'],
            ['name' => 'Тверская область', 'code' => '69', 'federal_district' => 'Центральный'],
            ['name' => 'Тульская область', 'code' => '71', 'federal_district' => 'Центральный'],
            ['name' => 'Ярославская область', 'code' => '76', 'federal_district' => 'Центральный'],

            // Северо-Западный федеральный округ
            ['name' => 'Республика Карелия', 'code' => '10', 'federal_district' => 'Северо-Западный'],
            ['name' => 'Республика Коми', 'code' => '11', 'federal_district' => 'Северо-Западный'],
            ['name' => 'Архангельская область', 'code' => '29', 'federal_district' => 'Северо-Западный'],
            ['name' => 'Вологодская область', 'code' => '35', 'federal_district' => 'Северо-Западный'],
            ['name' => 'Калининградская область', 'code' => '39', 'federal_district' => 'Северо-Западный'],
            ['name' => 'Ленинградская область', 'code' => '47', 'federal_district' => 'Северо-Западный'],
            ['name' => 'Мурманская область', 'code' => '51', 'federal_district' => 'Северо-Западный'],
            ['name' => 'Новгородская область', 'code' => '53', 'federal_district' => 'Северо-Западный'],
            ['name' => 'Псковская область', 'code' => '60', 'federal_district' => 'Северо-Западный'],
            ['name' => 'Санкт-Петербург', 'code' => '78', 'federal_district' => 'Северо-Западный'],
            ['name' => 'Ненецкий автономный округ', 'code' => '83', 'federal_district' => 'Северо-Западный'],

            // Южный федеральный округ
            ['name' => 'Республика Адыгея', 'code' => '01', 'federal_district' => 'Южный'],
            ['name' => 'Республика Калмыкия', 'code' => '08', 'federal_district' => 'Южный'],
            ['name' => 'Республика Крым', 'code' => '82', 'federal_district' => 'Южный'],
            ['name' => 'Краснодарский край', 'code' => '23', 'federal_district' => 'Южный'],
            ['name' => 'Астраханская область', 'code' => '30', 'federal_district' => 'Южный'],
            ['name' => 'Волгоградская область', 'code' => '34', 'federal_district' => 'Южный'],
            ['name' => 'Ростовская область', 'code' => '61', 'federal_district' => 'Южный'],
            ['name' => 'Севастополь', 'code' => '92', 'federal_district' => 'Южный'],

            // Северо-Кавказский федеральный округ
            ['name' => 'Республика Дагестан', 'code' => '05', 'federal_district' => 'Северо-Кавказский'],
            ['name' => 'Республика Ингушетия', 'code' => '06', 'federal_district' => 'Северо-Кавказский'],
            ['name' => 'Кабардино-Балкарская Республика', 'code' => '07', 'federal_district' => 'Северо-Кавказский'],
            ['name' => 'Карачаево-Черкесская Республика', 'code' => '09', 'federal_district' => 'Северо-Кавказский'],
            ['name' => 'Республика Северная Осетия — Алания', 'code' => '15', 'federal_district' => 'Северо-Кавказский'],
            ['name' => 'Чеченская Республика', 'code' => '20', 'federal_district' => 'Северо-Кавказский'],
            ['name' => 'Ставропольский край', 'code' => '26', 'federal_district' => 'Северо-Кавказский'],

            // Приволжский федеральный округ
            ['name' => 'Республика Башкортостан', 'code' => '02', 'federal_district' => 'Приволжский'],
            ['name' => 'Республика Марий Эл', 'code' => '12', 'federal_district' => 'Приволжский'],
            ['name' => 'Республика Мордовия', 'code' => '13', 'federal_district' => 'Приволжский'],
            ['name' => 'Республика Татарстан', 'code' => '16', 'federal_district' => 'Приволжский'],
            ['name' => 'Удмуртская Республика', 'code' => '18', 'federal_district' => 'Приволжский'],
            ['name' => 'Чувашская Республика', 'code' => '21', 'federal_district' => 'Приволжский'],
            ['name' => 'Пермский край', 'code' => '59', 'federal_district' => 'Приволжский'],
            ['name' => 'Кировская область', 'code' => '43', 'federal_district' => 'Приволжский'],
            ['name' => 'Нижегородская область', 'code' => '52', 'federal_district' => 'Приволжский'],
            ['name' => 'Оренбургская область', 'code' => '56', 'federal_district' => 'Приволжский'],
            ['name' => 'Пензенская область', 'code' => '58', 'federal_district' => 'Приволжский'],
            ['name' => 'Самарская область', 'code' => '63', 'federal_district' => 'Приволжский'],
            ['name' => 'Саратовская область', 'code' => '64', 'federal_district' => 'Приволжский'],
            ['name' => 'Ульяновская область', 'code' => '73', 'federal_district' => 'Приволжский'],

            // Уральский федеральный округ
            ['name' => 'Курганская область', 'code' => '45', 'federal_district' => 'Уральский'],
            ['name' => 'Свердловская область', 'code' => '66', 'federal_district' => 'Уральский'],
            ['name' => 'Тюменская область', 'code' => '72', 'federal_district' => 'Уральский'],
            ['name' => 'Челябинская область', 'code' => '74', 'federal_district' => 'Уральский'],
            ['name' => 'Ханты-Мансийский автономный округ — Югра', 'code' => '86', 'federal_district' => 'Уральский'],
            ['name' => 'Ямало-Ненецкий автономный округ', 'code' => '89', 'federal_district' => 'Уральский'],

            // Сибирский федеральный округ
            ['name' => 'Республика Алтай', 'code' => '04', 'federal_district' => 'Сибирский'],
            ['name' => 'Республика Тыва', 'code' => '17', 'federal_district' => 'Сибирский'],
            ['name' => 'Республика Хакасия', 'code' => '19', 'federal_district' => 'Сибирский'],
            ['name' => 'Алтайский край', 'code' => '22', 'federal_district' => 'Сибирский'],
            ['name' => 'Красноярский край', 'code' => '24', 'federal_district' => 'Сибирский'],
            ['name' => 'Иркутская область', 'code' => '38', 'federal_district' => 'Сибирский'],
            ['name' => 'Кемеровская область', 'code' => '42', 'federal_district' => 'Сибирский'],
            ['name' => 'Новосибирская область', 'code' => '54', 'federal_district' => 'Сибирский'],
            ['name' => 'Омская область', 'code' => '55', 'federal_district' => 'Сибирский'],
            ['name' => 'Томская область', 'code' => '70', 'federal_district' => 'Сибирский'],

            // Дальневосточный федеральный округ
            ['name' => 'Республика Бурятия', 'code' => '03', 'federal_district' => 'Дальневосточный'],
            ['name' => 'Республика Саха (Якутия)', 'code' => '14', 'federal_district' => 'Дальневосточный'],
            ['name' => 'Забайкальский край', 'code' => '75', 'federal_district' => 'Дальневосточный'],
            ['name' => 'Камчатский край', 'code' => '41', 'federal_district' => 'Дальневосточный'],
            ['name' => 'Приморский край', 'code' => '25', 'federal_district' => 'Дальневосточный'],
            ['name' => 'Хабаровский край', 'code' => '27', 'federal_district' => 'Дальневосточный'],
            ['name' => 'Амурская область', 'code' => '28', 'federal_district' => 'Дальневосточный'],
            ['name' => 'Магаданская область', 'code' => '49', 'federal_district' => 'Дальневосточный'],
            ['name' => 'Сахалинская область', 'code' => '65', 'federal_district' => 'Дальневосточный'],
            ['name' => 'Еврейская автономная область', 'code' => '79', 'federal_district' => 'Дальневосточный'],
            ['name' => 'Чукотский автономный округ', 'code' => '87', 'federal_district' => 'Дальневосточный'],
        ];

        $now = now();
        $order = 1;

        foreach ($regions as $region) {
            DB::table('regions')->insert([
                'name' => $region['name'],
                'code' => $region['code'],
                'federal_district' => $region['federal_district'],
                'sort_order' => $order++,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
