<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_stocks', function (Blueprint $table): void {
            $table->foreignId('stock_updated_by_user_id')
                ->nullable()
                ->after('stock_updated_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_stocks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stock_updated_by_user_id');
        });
    }
};
