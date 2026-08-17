<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('platform_orders', 'order_channel')) {
            Schema::table('platform_orders', function (Blueprint $table) {
                $table->string('order_channel', 40)
                    ->default('end_company_distributor')
                    ->after('source');
                $table->index('order_channel');
            });
        }

        if (! Schema::hasColumn('platform_orders', 'distributor_warehouse_id')) {
            Schema::table('platform_orders', function (Blueprint $table) {
                $table->foreignId('distributor_warehouse_id')
                    ->nullable()
                    ->after('end_company_delivery_address_id')
                    ->constrained('distributor_warehouses')
                    ->nullOnDelete();
            });
        }

        DB::table('platform_orders')
            ->where(function ($q): void {
                $q->whereNull('order_channel')->orWhere('order_channel', '');
            })
            ->update(['order_channel' => 'end_company_distributor']);

        if (! Schema::hasTable('distributor_purchase_carts')) {
            Schema::create('distributor_purchase_carts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('distributor_profile_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique('distributor_profile_id');
            });
        }

        if (! Schema::hasTable('distributor_purchase_cart_items')) {
            Schema::create('distributor_purchase_cart_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cart_id');
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 14, 2)->nullable();
                $table->timestamps();

                $table->foreign('cart_id', 'dist_purchase_cart_fk')
                    ->references('id')
                    ->on('distributor_purchase_carts')
                    ->cascadeOnDelete();
                $table->unique(['cart_id', 'product_id'], 'dist_purchase_cart_product_unique');
                $table->index('product_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_purchase_cart_items');
        Schema::dropIfExists('distributor_purchase_carts');

        Schema::table('platform_orders', function (Blueprint $table) {
            if (Schema::hasColumn('platform_orders', 'distributor_warehouse_id')) {
                $table->dropConstrainedForeignId('distributor_warehouse_id');
            }
            if (Schema::hasColumn('platform_orders', 'order_channel')) {
                $table->dropIndex(['order_channel']);
                $table->dropColumn('order_channel');
            }
        });
    }
};
