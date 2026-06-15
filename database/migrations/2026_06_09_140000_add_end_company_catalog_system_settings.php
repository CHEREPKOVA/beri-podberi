<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $settings = [
            [
                'group_key' => 'catalog',
                'key' => 'catalog.end_company_require_distributor_price',
                'label' => 'КК: требовать цену дистрибьютора для показа товара',
                'value' => '1',
                'value_type' => 'boolean',
                'description' => 'Товар конечной компании попадает в каталог только если у дистрибьютора в регионе задана отпускная цена.',
                'sort_order' => 1,
            ],
            [
                'group_key' => 'catalog',
                'key' => 'catalog.end_company_require_regional_stock',
                'label' => 'КК: требовать остаток на складе дистрибьютора в регионе',
                'value' => '0',
                'value_type' => 'boolean',
                'description' => 'Если включено, товар без остатка на складах дистрибьютора в регионе КК не показывается как доступный.',
                'sort_order' => 2,
            ],
            [
                'group_key' => 'catalog',
                'key' => 'catalog.end_company_show_unavailable_products',
                'label' => 'КК: показывать товары «Недоступно в вашем регионе»',
                'value' => '0',
                'value_type' => 'boolean',
                'description' => 'Показывать в каталоге товары с дистрибьютором в регионе, но без цены/остатка (без цены и остатков, без заказа).',
                'sort_order' => 3,
            ],
            [
                'group_key' => 'catalog',
                'key' => 'catalog.end_company_show_unavailable_analogs',
                'label' => 'КК: показывать аналоги «Недоступно в вашем регионе»',
                'value' => '0',
                'value_type' => 'boolean',
                'description' => 'Показывать связанные аналоги, которые есть в каталоге, но недоступны для заказа в регионе КК.',
                'sort_order' => 4,
            ],
        ];

        foreach ($settings as $setting) {
            $exists = DB::table('system_settings')->where('key', $setting['key'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('system_settings')->insert([
                ...$setting,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', [
            'catalog.end_company_require_distributor_price',
            'catalog.end_company_require_regional_stock',
            'catalog.end_company_show_unavailable_products',
            'catalog.end_company_show_unavailable_analogs',
        ])->delete();
    }
};
