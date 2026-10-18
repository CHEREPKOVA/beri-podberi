<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('distributor_discount_sets')) {
            Schema::create('distributor_discount_sets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('distributor_profile_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('type', 32);
                $table->string('value_kind', 16)->default('percent');
                $table->string('status', 16)->default('inactive');
                $table->decimal('discount_percent', 8, 2)->nullable();
                $table->decimal('discount_amount', 12, 2)->nullable();
                $table->decimal('min_order_amount', 12, 2)->nullable();
                $table->unsignedInteger('min_quantity')->nullable();
                $table->json('warehouse_ids')->nullable();
                $table->boolean('do_not_stack')->default(false);
                $table->unsignedInteger('priority')->default(0);
                $table->string('source', 16)->default('manual');
                $table->boolean('apply_to_all_products')->default(false);
                $table->json('category_ids')->nullable();
                $table->json('brands')->nullable();
                $table->json('product_ids')->nullable();
                $table->string('audience_type', 32)->default('all');
                $table->json('company_ids')->nullable();
                $table->json('region_ids')->nullable();
                $table->json('company_types')->nullable();
                $table->date('starts_at')->nullable();
                $table->date('ends_at')->nullable();
                $table->boolean('is_indefinite')->default(true);
                $table->boolean('auto_deactivate')->default(true);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['distributor_profile_id', 'status'], 'dst_disc_sets_profile_status_idx');
                $table->index(['distributor_profile_id', 'type'], 'dst_disc_sets_profile_type_idx');
            });
        }

        if (! Schema::hasTable('distributor_discount_set_logs')) {
            Schema::create('distributor_discount_set_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('discount_set_id')->constrained('distributor_discount_sets')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 32);
                $table->json('changes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('discount_set_id', 'dst_disc_set_logs_set_idx');
            });
        }

        if (! Schema::hasColumn('platform_orders', 'distributor_discount_set_id')) {
            Schema::table('platform_orders', function (Blueprint $table) {
                $column = $table->foreignId('distributor_discount_set_id')->nullable();
                if (Schema::hasColumn('platform_orders', 'discount_set_id')) {
                    $column->after('discount_set_id');
                }
                $column->constrained('distributor_discount_sets')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('platform_order_items', 'distributor_discount_set_id')) {
            Schema::table('platform_order_items', function (Blueprint $table) {
                $column = $table->foreignId('distributor_discount_set_id')->nullable();
                if (Schema::hasColumn('platform_order_items', 'discount_set_id')) {
                    $column->after('discount_set_id');
                }
                $column->constrained('distributor_discount_sets')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('platform_order_items', 'distributor_discount_set_id')) {
            Schema::table('platform_order_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('distributor_discount_set_id');
            });
        }
        if (Schema::hasColumn('platform_orders', 'distributor_discount_set_id')) {
            Schema::table('platform_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('distributor_discount_set_id');
            });
        }
        Schema::dropIfExists('distributor_discount_set_logs');
        Schema::dropIfExists('distributor_discount_sets');
    }
};
