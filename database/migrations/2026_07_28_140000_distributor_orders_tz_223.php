<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_orders', function (Blueprint $table) {
            $table->string('source', 32)->default('lk')->after('order_number');
            $table->foreignId('responsible_contact_id')->nullable()->after('distributor_profile_id')
                ->constrained('distributor_contacts')->nullOnDelete();
        });

        Schema::table('platform_order_items', function (Blueprint $table) {
            $table->foreignId('distributor_warehouse_id')->nullable()->after('product_id')
                ->constrained('distributor_warehouses')->nullOnDelete();
        });

        $now = now();
        $existing = DB::table('order_statuses')->where('slug', 'in_work')->first();
        if ($existing) {
            DB::table('order_statuses')->where('id', $existing->id)->update([
                'name' => 'В работе',
                'description' => 'Заказ передан на комплектование склада',
                'sort_order' => 35,
                'is_terminal' => false,
                'is_active' => true,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('order_statuses')->insert([
                'slug' => 'in_work',
                'name' => 'В работе',
                'description' => 'Заказ передан на комплектование склада',
                'sort_order' => 35,
                'is_terminal' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Сдвигаем sort_order для ready/shipped/completed после in_work
        DB::table('order_statuses')->where('slug', 'ready_to_ship')->update(['sort_order' => 60, 'updated_at' => $now]);
        DB::table('order_statuses')->where('slug', 'shipped')->update(['sort_order' => 70, 'updated_at' => $now]);
        DB::table('order_statuses')->where('slug', 'completed')->update(['sort_order' => 80, 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('order_statuses')->where('slug', 'in_work')->delete();

        Schema::table('platform_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('distributor_warehouse_id');
        });

        Schema::table('platform_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_contact_id');
            $table->dropColumn('source');
        });
    }
};
