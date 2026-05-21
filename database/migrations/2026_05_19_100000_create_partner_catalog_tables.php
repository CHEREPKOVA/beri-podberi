<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_product_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['distributor_profile_id', 'product_category_id'], 'dist_prod_cat_unique');
        });

        Schema::create('manufacturer_distributor_partnerships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manufacturer_profile_id');
            $table->unsignedBigInteger('distributor_profile_id');
            $table->string('status', 32)->default('active');
            $table->unsignedBigInteger('added_by_user_id')->nullable();
            $table->timestamp('added_at')->nullable();
            $table->timestamps();

            $table->unique(['manufacturer_profile_id', 'distributor_profile_id'], 'mfr_dist_partnership_unique');
            $table->index('status');

            $table->foreign('manufacturer_profile_id', 'mfr_dist_p_mfr_fk')
                ->references('id')->on('manufacturer_profiles')->cascadeOnDelete();
            $table->foreign('distributor_profile_id', 'mfr_dist_p_dist_fk')
                ->references('id')->on('distributor_profiles')->cascadeOnDelete();
            $table->foreign('added_by_user_id', 'mfr_dist_p_user_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('manufacturer_distributor_exclusive_regions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manufacturer_profile_id');
            $table->unsignedBigInteger('distributor_profile_id');
            $table->unsignedBigInteger('region_id');
            $table->unsignedBigInteger('assigned_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['manufacturer_profile_id', 'region_id'], 'mfr_exclusive_region_unique');
            $table->index(['distributor_profile_id', 'manufacturer_profile_id'], 'mfr_dist_exclusive_idx');

            $table->foreign('manufacturer_profile_id', 'mfr_dist_ex_mfr_fk')
                ->references('id')->on('manufacturer_profiles')->cascadeOnDelete();
            $table->foreign('distributor_profile_id', 'mfr_dist_ex_dist_fk')
                ->references('id')->on('distributor_profiles')->cascadeOnDelete();
            $table->foreign('region_id', 'mfr_dist_ex_reg_fk')
                ->references('id')->on('regions')->cascadeOnDelete();
            $table->foreign('assigned_by_user_id', 'mfr_dist_ex_user_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('manufacturer_distributor_partnership_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manufacturer_profile_id');
            $table->unsignedBigInteger('distributor_profile_id');
            $table->string('action', 64);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['manufacturer_profile_id', 'distributor_profile_id'], 'mfr_dist_log_idx');

            $table->foreign('manufacturer_profile_id', 'mfr_dist_log_mfr_fk')
                ->references('id')->on('manufacturer_profiles')->cascadeOnDelete();
            $table->foreign('distributor_profile_id', 'mfr_dist_log_dist_fk')
                ->references('id')->on('distributor_profiles')->cascadeOnDelete();
            $table->foreign('performed_by_user_id', 'mfr_dist_log_user_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('platform_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->unsignedBigInteger('distributor_profile_id')->nullable();
            $table->unsignedBigInteger('manufacturer_profile_id')->nullable();
            $table->unsignedBigInteger('end_company_profile_id')->nullable();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status', 32)->default('new');
            $table->timestamp('ordered_at')->nullable();
            $table->timestamps();

            $table->index('distributor_profile_id');
            $table->index('end_company_profile_id');

            $table->foreign('distributor_profile_id', 'plat_ord_dist_fk')
                ->references('id')->on('distributor_profiles')->nullOnDelete();
            $table->foreign('manufacturer_profile_id', 'plat_ord_mfr_fk')
                ->references('id')->on('manufacturer_profiles')->nullOnDelete();
            $table->foreign('end_company_profile_id', 'plat_ord_ec_fk')
                ->references('id')->on('end_company_profiles')->nullOnDelete();
        });

        Schema::table('end_company_profiles', function (Blueprint $table) {
            $table->string('activity_type')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('end_company_profiles', function (Blueprint $table) {
            $table->dropColumn('activity_type');
        });

        Schema::dropIfExists('platform_orders');
        Schema::dropIfExists('manufacturer_distributor_partnership_logs');
        Schema::dropIfExists('manufacturer_distributor_exclusive_regions');
        Schema::dropIfExists('manufacturer_distributor_partnerships');
        Schema::dropIfExists('distributor_product_category');
    }
};
