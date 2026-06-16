<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributor_profiles', function (Blueprint $table): void {
            $table->string('zero_stock_behavior', 20)
                ->default('on_order')
                ->after('delivery_notes');
        });
    }

    public function down(): void
    {
        Schema::table('distributor_profiles', function (Blueprint $table): void {
            $table->dropColumn('zero_stock_behavior');
        });
    }
};

