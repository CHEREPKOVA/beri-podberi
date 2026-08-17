<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_orders', function (Blueprint $table) {
            $table->foreignId('delivery_method_id')->nullable()->after('end_company_profile_id')->constrained()->nullOnDelete();
            $table->foreignId('transport_company_id')->nullable()->after('delivery_method_id')->constrained()->nullOnDelete();
            $table->foreignId('end_company_delivery_address_id')->nullable()->after('transport_company_id')
                ->constrained('end_company_delivery_addresses')->nullOnDelete();
            $table->text('buyer_comment')->nullable()->after('end_company_delivery_address_id');
        });

        Schema::create('platform_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('distributor_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku', 128)->nullable();
            $table->string('manufacturer_name')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->index('platform_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_order_items');

        Schema::table('platform_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('end_company_delivery_address_id');
            $table->dropConstrainedForeignId('transport_company_id');
            $table->dropConstrainedForeignId('delivery_method_id');
            $table->dropColumn('buyer_comment');
        });
    }
};
