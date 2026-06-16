<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_product_regional_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_product_id');
            $table->foreignId('region_id');
            $table->decimal('price', 12, 2);
            $table->timestamps();

            $table->foreign('distributor_product_id', 'dprp_dist_prod_fk')
                ->references('id')
                ->on('distributor_products')
                ->cascadeOnDelete();
            $table->foreign('region_id', 'dprp_region_fk')
                ->references('id')
                ->on('regions')
                ->cascadeOnDelete();

            $table->unique(['distributor_product_id', 'region_id'], 'dist_prod_regional_price_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_product_regional_prices');
    }
};
