<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturer_discount_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 32);
            $table->string('status', 16)->default('inactive');
            $table->decimal('discount_percent', 8, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->decimal('min_order_amount', 12, 2)->nullable();
            $table->unsignedInteger('min_quantity')->nullable();
            $table->boolean('require_all_conditions')->default(true);
            $table->boolean('apply_to_all_products')->default(true);
            $table->boolean('apply_only_active_products')->default(true);
            $table->boolean('only_new_distributors')->default(false);
            $table->string('audience_type', 32)->default('all');
            $table->json('category_ids')->nullable();
            $table->json('excluded_category_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->json('region_ids')->nullable();
            $table->json('audience_region_ids')->nullable();
            $table->json('distributor_ids')->nullable();
            $table->json('company_types')->nullable();
            $table->json('tiers')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_indefinite')->default(true);
            $table->boolean('auto_deactivate')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['manufacturer_profile_id', 'status'], 'mfr_disc_sets_profile_status_idx');
            $table->index(['manufacturer_profile_id', 'type'], 'mfr_disc_sets_profile_type_idx');
        });

        Schema::create('manufacturer_discount_set_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_set_id')->constrained('manufacturer_discount_sets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 32);
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('discount_set_id', 'mfr_disc_set_logs_set_idx');
        });

        Schema::create('manufacturer_promo_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('discount_type', 16);
            $table->decimal('discount_value', 12, 2);
            $table->string('status', 16)->default('inactive');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('per_user_limit')->default(0);
            $table->decimal('min_order_amount', 12, 2)->nullable();
            $table->boolean('apply_to_all_products')->default(true);
            $table->json('category_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->json('region_ids')->nullable();
            $table->json('company_ids')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('code', 'mfr_promo_codes_code_uq');
            $table->index(['manufacturer_profile_id', 'status'], 'mfr_promo_codes_profile_status_idx');
        });

        Schema::create('manufacturer_promo_code_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained('manufacturer_promo_codes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('platform_order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->timestamp('used_at')->useCurrent();

            $table->index(['promo_code_id', 'user_id'], 'mfr_promo_usages_code_user_idx');
        });

        Schema::table('platform_orders', function (Blueprint $table) {
            $table->foreignId('discount_set_id')->nullable()->after('total_amount')->constrained('manufacturer_discount_sets')->nullOnDelete();
            $table->foreignId('promo_code_id')->nullable()->after('discount_set_id')->constrained('manufacturer_promo_codes')->nullOnDelete();
            $table->string('promo_code')->nullable()->after('promo_code_id');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('promo_code');
            $table->string('discount_label')->nullable()->after('discount_amount');
        });

        Schema::table('platform_order_items', function (Blueprint $table) {
            $table->foreignId('discount_set_id')->nullable()->after('line_total')->constrained('manufacturer_discount_sets')->nullOnDelete();
            $table->string('discount_label')->nullable()->after('discount_set_id');
        });

        Schema::table('distributor_purchase_carts', function (Blueprint $table) {
            $table->json('promo_codes')->nullable()->after('distributor_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('distributor_purchase_carts', function (Blueprint $table) {
            $table->dropColumn('promo_codes');
        });

        Schema::table('platform_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_set_id');
            $table->dropColumn('discount_label');
        });

        Schema::table('platform_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_code_id');
            $table->dropConstrainedForeignId('discount_set_id');
            $table->dropColumn(['promo_code', 'discount_amount', 'discount_label']);
        });

        Schema::dropIfExists('manufacturer_promo_code_usages');
        Schema::dropIfExists('manufacturer_promo_codes');
        Schema::dropIfExists('manufacturer_discount_set_logs');
        Schema::dropIfExists('manufacturer_discount_sets');
    }
};
