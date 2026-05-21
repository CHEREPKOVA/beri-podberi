<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->string('filter_display_type')->nullable();
            $table->string('filter_values_source', 32)->default('fixed');
            $table->boolean('filter_allow_multiple')->default(false);
            $table->unsignedSmallInteger('filter_sort_order')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->dropColumn([
                'filter_display_type',
                'filter_values_source',
                'filter_allow_multiple',
                'filter_sort_order',
            ]);
        });
    }
};
