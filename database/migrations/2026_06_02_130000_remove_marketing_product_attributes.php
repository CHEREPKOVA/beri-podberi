<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $attributeIds = DB::table('product_attributes')
            ->whereNull('product_id')
            ->whereIn('name', ['Новинка', 'Хит продаж'])
            ->pluck('id');

        if ($attributeIds->isEmpty()) {
            return;
        }

        DB::table('product_attribute_values')
            ->whereIn('product_attribute_id', $attributeIds)
            ->delete();

        DB::table('product_category_excluded_attributes')
            ->whereIn('product_attribute_id', $attributeIds)
            ->delete();

        DB::table('product_attributes')
            ->whereIn('id', $attributeIds)
            ->delete();
    }

    public function down(): void
    {
        // Намеренно не восстанавливаем — свойства не входят в ТЗ.
    }
};
