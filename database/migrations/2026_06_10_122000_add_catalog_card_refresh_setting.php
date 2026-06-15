<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'catalog.product_card_refresh_seconds'],
            [
                'group_key' => 'catalog',
                'label' => 'Интервал автообновления карточки товара (сек.)',
                'value' => '60',
                'value_type' => 'integer',
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('key', 'catalog.product_card_refresh_seconds')
            ->delete();
    }
};
