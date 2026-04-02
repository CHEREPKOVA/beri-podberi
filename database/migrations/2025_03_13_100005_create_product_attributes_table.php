<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->enum('type', ['text', 'number', 'select', 'boolean'])->default('text');
            $table->json('options')->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_filterable');
            $table->unique(['product_category_id', 'slug'], 'product_attributes_category_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
    }
};
