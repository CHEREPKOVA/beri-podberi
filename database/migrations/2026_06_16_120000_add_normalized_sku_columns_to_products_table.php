<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku_normalized', 191)->nullable()->after('sku');
            $table->string('manufacturer_sku_normalized', 191)->nullable()->after('manufacturer_sku');
            $table->string('distributor_sku_normalized', 191)->nullable()->after('distributor_sku');

            $table->index('sku_normalized');
            $table->index('manufacturer_sku_normalized');
        });

        DB::table('products')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('products')->where('id', $row->id)->update([
                    'sku_normalized' => Product::normalizeSku($row->sku),
                    'manufacturer_sku_normalized' => Product::normalizeSku($row->manufacturer_sku),
                    'distributor_sku_normalized' => Product::normalizeSku($row->distributor_sku),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sku_normalized']);
            $table->dropIndex(['manufacturer_sku_normalized']);
            $table->dropColumn([
                'sku_normalized',
                'manufacturer_sku_normalized',
                'distributor_sku_normalized',
            ]);
        });
    }
};
