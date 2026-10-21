<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_orders', function (Blueprint $table) {
            $table->text('shipping_comment')->nullable()->after('shipping_from_warehouse');
        });
    }

    public function down(): void
    {
        Schema::table('platform_orders', function (Blueprint $table) {
            $table->dropColumn('shipping_comment');
        });
    }
};
