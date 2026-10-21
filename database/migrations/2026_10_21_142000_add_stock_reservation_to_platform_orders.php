<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_orders', function (Blueprint $table) {
            $table->boolean('stock_reserved')->default(false)->after('shipping_comment');
            $table->json('stock_reservation')->nullable()->after('stock_reserved');
        });
    }

    public function down(): void
    {
        Schema::table('platform_orders', function (Blueprint $table) {
            $table->dropColumn(['stock_reserved', 'stock_reservation']);
        });
    }
};
