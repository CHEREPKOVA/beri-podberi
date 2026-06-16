<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'group_key' => 'catalog',
                'key' => 'catalog.search_logging_enabled',
                'label' => 'Каталог: логировать поисковые запросы',
                'value' => '1',
                'value_type' => 'boolean',
                'description' => 'Сохранять запросы пользователей для блока «Популярные запросы».',
                'sort_order' => 50,
            ],
            [
                'group_key' => 'catalog',
                'key' => 'catalog.popular_search_limit',
                'label' => 'Каталог: число популярных запросов',
                'value' => '5',
                'value_type' => 'integer',
                'description' => 'Сколько подсказок показывать при фокусе на пустом поле поиска.',
                'sort_order' => 51,
            ],
            [
                'group_key' => 'catalog',
                'key' => 'catalog.popular_search_days',
                'label' => 'Каталог: период популярных запросов, дней',
                'value' => '30',
                'value_type' => 'integer',
                'description' => 'За какой период считать популярность поисковых запросов.',
                'sort_order' => 52,
            ],
        ];

        foreach ($settings as $setting) {
            $exists = DB::table('system_settings')->where('key', $setting['key'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('system_settings')->insert(array_merge($setting, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', [
            'catalog.search_logging_enabled',
            'catalog.popular_search_limit',
            'catalog.popular_search_days',
        ])->delete();
    }
};
