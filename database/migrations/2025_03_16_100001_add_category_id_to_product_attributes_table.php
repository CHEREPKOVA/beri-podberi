<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->foreignId('product_category_id')->nullable()->after('id')->constrained('product_categories')->onDelete('cascade');
        });

        Schema::table('product_attributes', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['product_category_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->dropUnique(['product_category_id', 'slug']);
            $table->unique(['slug']);
        });
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->dropForeign(['product_category_id']);
        });
    }
};
