<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('manufacturer_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('unit_type_id')->nullable()->constrained('unit_types')->nullOnDelete();

            $table->string('name');
            $table->string('internal_sku')->index();
            $table->string('manufacturer_sku')->nullable();
            $table->string('brand')->nullable();
            $table->string('barcode')->nullable()->index();
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->string('country_of_origin')->nullable();
            $table->integer('pack_quantity')->nullable();
            $table->integer('min_order_quantity')->nullable()->default(1);

            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('retail_price', 12, 2)->nullable();
            $table->timestamp('price_updated_at')->nullable();

            $table->string('status', 32)->default('hidden');
            $table->string('sync_source', 32)->default('manual');
            $table->timestamp('synced_at')->nullable();
            $table->boolean('manufacturer_archived')->default(false);
            $table->boolean('managed_by_1c')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['distributor_profile_id', 'internal_sku'], 'dist_prod_internal_sku_unique');
            $table->index(['distributor_profile_id', 'status']);
            $table->index('updated_at');
        });

        Schema::create('distributor_product_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('distributor_product_id');
            $table->unsignedBigInteger('distributor_warehouse_id');
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->timestamp('stock_updated_at')->nullable();
            $table->unsignedBigInteger('stock_updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['distributor_product_id', 'distributor_warehouse_id'], 'dist_prod_stock_wh_unique');

            $table->foreign('distributor_product_id', 'dist_prod_stock_prod_fk')
                ->references('id')->on('distributor_products')->cascadeOnDelete();
            $table->foreign('distributor_warehouse_id', 'dist_prod_stock_wh_fk')
                ->references('id')->on('distributor_warehouses')->cascadeOnDelete();
            $table->foreign('stock_updated_by_user_id', 'dist_prod_stock_user_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('distributor_product_price_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('distributor_product_id');
            $table->string('price_type', 32);
            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('new_price', 12, 2);
            $table->text('comment')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['distributor_product_id', 'created_at'], 'dist_prod_price_hist_idx');

            $table->foreign('distributor_product_id', 'dist_prod_price_hist_prod_fk')
                ->references('id')->on('distributor_products')->cascadeOnDelete();
            $table->foreign('changed_by_user_id', 'dist_prod_price_hist_user_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('distributor_product_change_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('distributor_product_id');
            $table->string('action', 64);
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['distributor_product_id', 'created_at'], 'dist_prod_change_log_idx');

            $table->foreign('distributor_product_id', 'dist_prod_change_log_prod_fk')
                ->references('id')->on('distributor_products')->cascadeOnDelete();
            $table->foreign('performed_by_user_id', 'dist_prod_change_log_user_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('distributor_product_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('distributor_product_id');
            $table->string('name');
            $table->string('type', 32)->default('other');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->foreign('distributor_product_id', 'dist_prod_doc_prod_fk')
                ->references('id')->on('distributor_products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_product_documents');
        Schema::dropIfExists('distributor_product_change_logs');
        Schema::dropIfExists('distributor_product_price_histories');
        Schema::dropIfExists('distributor_product_stocks');
        Schema::dropIfExists('distributor_products');
    }
};
