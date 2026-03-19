<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_regional_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('region_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 12, 2);
            $table->timestamps();

            $table->unique(['product_id', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_regional_prices');
    }
};
