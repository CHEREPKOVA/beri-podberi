<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            if (! Schema::hasColumn('cart_items', 'region_id')) {
                $table->foreignId('region_id')
                    ->nullable()
                    ->after('distributor_product_id')
                    ->constrained()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('cart_items', 'distributor_warehouse_id')) {
                $table->foreignId('distributor_warehouse_id')
                    ->nullable()
                    ->after('region_id')
                    ->constrained('distributor_warehouses')
                    ->nullOnDelete();
            }
        });

        $indexes = $this->indexNames('cart_items');

        // MySQL keeps using the unique index for the cart_id FK; add a
        // dedicated index before dropping the composite unique key.
        if (! in_array('cart_items_cart_id_index', $indexes, true)
            && ! in_array('cart_items_cart_id_foreign', $indexes, true)) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->index('cart_id', 'cart_items_cart_id_index');
            });
        }

        $indexes = $this->indexNames('cart_items');

        Schema::table('cart_items', function (Blueprint $table) use ($indexes) {
            if (in_array('cart_items_cart_id_distributor_product_id_unique', $indexes, true)) {
                $table->dropUnique('cart_items_cart_id_distributor_product_id_unique');
            }

            if (! in_array('cart_items_cart_offer_region_unique', $indexes, true)) {
                $table->unique(
                    ['cart_id', 'distributor_product_id', 'region_id'],
                    'cart_items_cart_offer_region_unique'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_cart_offer_region_unique');
            $table->dropConstrainedForeignId('distributor_warehouse_id');
            $table->dropConstrainedForeignId('region_id');
            $table->unique(['cart_id', 'distributor_product_id']);
        });
    }

    /**
     * @return list<string>
     */
    private function indexNames(string $table): array
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->pluck('Key_name')
            ->unique()
            ->values()
            ->all();
    }
};
