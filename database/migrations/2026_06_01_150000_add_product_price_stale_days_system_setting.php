<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('system_settings')
            ->where('key', 'timings.product_price_stale_days')
            ->exists();

        if ($exists) {
            return;
        }

        $now = now();

        DB::table('system_settings')->insert([
            'group_key' => 'timings',
            'key' => 'timings.product_price_stale_days',
            'label' => 'Срок устаревания цены товара (дней)',
            'value' => '30',
            'value_type' => 'integer',
            'description' => 'Товары с ценой, не обновлявшейся дольше этого срока (или без даты обновления цены), попадают в фильтр «Требуют обновления» на номенклатуре производителя.',
            'sort_order' => 3,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('key', 'timings.product_price_stale_days')
            ->delete();
    }
};
