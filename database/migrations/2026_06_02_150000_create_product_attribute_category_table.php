<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['product_attribute_id', 'product_category_id'],
                'product_attribute_category_unique'
            );
        });

        $rows = DB::table('product_attributes')
            ->whereNotNull('product_category_id')
            ->orderBy('id')
            ->get(['id', 'product_category_id']);

        foreach ($rows as $row) {
            DB::table('product_attribute_category')->insertOrIgnore([
                'product_attribute_id' => $row->id,
                'product_category_id' => $row->product_category_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_category');
    }
};
