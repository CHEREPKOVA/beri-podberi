<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->decimal('list_unit_price', 14, 2)->nullable()->after('unit_price');
        });

        Schema::table('platform_order_items', function (Blueprint $table) {
            $table->decimal('list_unit_price', 14, 2)->nullable()->after('unit_price');
        });

        Schema::table('platform_orders', function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->after('buyer_comment');
            $table->time('delivery_time_from')->nullable()->after('delivery_date');
            $table->time('delivery_time_to')->nullable()->after('delivery_time_from');
            $table->string('delivery_vehicle_type', 64)->nullable()->after('delivery_time_to');
        });
    }

    public function down(): void
    {
        Schema::table('platform_orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_date', 'delivery_time_from', 'delivery_time_to', 'delivery_vehicle_type']);
        });

        Schema::table('platform_order_items', function (Blueprint $table) {
            $table->dropColumn('list_unit_price');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('list_unit_price');
        });
    }
};
