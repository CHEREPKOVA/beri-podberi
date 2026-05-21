<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_category_excluded_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_category_id');
            $table->unsignedBigInteger('product_attribute_id');
            $table->timestamps();

            $table->unique(
                ['product_category_id', 'product_attribute_id'],
                'pc_excl_attr_cat_attr_uq'
            );

            $table->foreign('product_category_id', 'pc_excl_attr_cat_fk')
                ->references('id')->on('product_categories')->cascadeOnDelete();
            $table->foreign('product_attribute_id', 'pc_excl_attr_attr_fk')
                ->references('id')->on('product_attributes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category_excluded_attributes');
    }
};
