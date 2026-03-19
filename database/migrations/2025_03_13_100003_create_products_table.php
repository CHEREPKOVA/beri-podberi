<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->onDelete('set null');
            $table->foreignId('unit_type_id')->nullable()->constrained('unit_types')->onDelete('set null');

            $table->string('name');
            $table->string('sku')->index();
            $table->text('description')->nullable();
            $table->string('video_url')->nullable();
            $table->integer('min_order_quantity')->nullable();

            $table->decimal('base_price', 12, 2)->nullable();

            $table->string('manufacturer_sku')->nullable();
            $table->string('distributor_sku')->nullable();
            $table->string('ean')->nullable();
            $table->string('barcode')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('storage_conditions')->nullable();
            $table->text('transport_conditions')->nullable();
            $table->string('instruction_url')->nullable();

            $table->enum('status', ['active', 'hidden', 'draft'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('show_in_catalog')->default(false);

            $table->string('sync_source')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->boolean('is_modified')->default(false);
            $table->timestamp('price_updated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['manufacturer_profile_id', 'sku']);
            $table->index('status');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
