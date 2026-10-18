<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('region_id')
                ->nullable()
                ->after('distributor_product_id')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('distributor_warehouse_id')
                ->nullable()
                ->after('region_id')
                ->constrained('distributor_warehouses')
                ->nullOnDelete();

            $table->dropUnique(['cart_id', 'distributor_product_id']);
            $table->unique(['cart_id', 'distributor_product_id', 'region_id'], 'cart_items_cart_offer_region_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_cart_offer_region_unique');
            $table->dropConstrainedForeignId('distributor_warehouse_id');
            $table->dropConstrainedForeignId('region_id');
            $table->unique(['cart_id', 'distributor_product_id']);
        });
    }
};
