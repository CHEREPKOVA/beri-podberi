<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')
            ->where('key', 'timings.order_pending_hours')
            ->update([
                'label' => 'Часы ожидания заказа в статусе «Новый»',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('key', 'timings.order_pending_hours')
            ->update([
                'label' => 'Часы ожидания заказа в статусе pending',
                'updated_at' => now(),
            ]);
    }
};
