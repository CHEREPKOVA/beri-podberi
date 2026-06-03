<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('product_attributes')
            ->whereNull('product_id')
            ->whereIn('name', ['Новинка', 'Хит продаж'])
            ->update([
                'is_filterable' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('product_attributes')
            ->whereNull('product_id')
            ->whereIn('name', ['Новинка', 'Хит продаж'])
            ->whereNull('product_id')
            ->update([
                'is_filterable' => true,
                'updated_at' => now(),
            ]);
    }
};
