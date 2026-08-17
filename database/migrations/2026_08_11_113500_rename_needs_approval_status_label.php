<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_statuses')) {
            return;
        }

        $now = now();

        DB::table('order_statuses')->where('slug', 'needs_approval')->update([
            'name' => 'Ожидает подтверждения',
            'description' => 'Поставщик изменил заказ, ожидается подтверждение покупателя',
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_statuses')) {
            return;
        }

        $now = now();

        DB::table('order_statuses')->where('slug', 'needs_approval')->update([
            'name' => 'Требует согласования',
            'description' => 'Поставщик изменил заказ, требуется подтверждение покупателя',
            'updated_at' => $now,
        ]);
    }
};
