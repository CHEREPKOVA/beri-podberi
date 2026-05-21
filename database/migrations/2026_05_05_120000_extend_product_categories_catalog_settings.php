<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('shown_in_customer_catalog')->default(true);
            $table->boolean('restrict_catalog_by_roles')->default(false);
            $table->boolean('accepts_products')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn([
                'shown_in_customer_catalog',
                'restrict_catalog_by_roles',
                'accepts_products',
            ]);
        });
    }
};
