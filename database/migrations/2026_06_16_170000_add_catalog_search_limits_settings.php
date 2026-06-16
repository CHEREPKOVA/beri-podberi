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
                'key' => 'catalog.search_min_query_length',
                'label' => 'Каталог: минимальная длина поискового запроса',
                'value' => '2',
                'value_type' => 'integer',
                'description' => 'Минимум символов, после которого отправляется запрос подсказок.',
                'sort_order' => 53,
            ],
            [
                'group_key' => 'catalog',
                'key' => 'catalog.search_suggest_limit',
                'label' => 'Каталог: лимит подсказок в блоке',
                'value' => '5',
                'value_type' => 'integer',
                'description' => 'Максимум элементов в каждом блоке подсказок поиска.',
                'sort_order' => 54,
            ],
        ];

        foreach ($settings as $setting) {
            if (DB::table('system_settings')->where('key', $setting['key'])->exists()) {
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
            'catalog.search_min_query_length',
            'catalog.search_suggest_limit',
        ])->delete();
    }
};
