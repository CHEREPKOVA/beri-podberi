<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_orders', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable()->after('status_changed_by_user_id');
            $table->unsignedBigInteger('paused_by_user_id')->nullable()->after('paused_at');
            $table->text('pause_reason')->nullable()->after('paused_by_user_id');
            $table->boolean('has_active_claim')->default(false)->after('pause_reason');
            $table->boolean('has_integration_error')->default(false)->after('has_active_claim');
        });

        Schema::table('platform_orders', function (Blueprint $table) {
            $table->foreign('paused_by_user_id', 'plat_ord_paused_user_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->index(['paused_at'], 'plat_ord_paused_at_idx');
            $table->index(['has_active_claim', 'has_integration_error'], 'plat_ord_problem_flags_idx');
        });
    }

    public function down(): void
    {
        Schema::table('platform_orders', function (Blueprint $table) {
            $table->dropForeign('plat_ord_paused_user_fk');
            $table->dropIndex('plat_ord_paused_at_idx');
            $table->dropIndex('plat_ord_problem_flags_idx');
            $table->dropColumn([
                'paused_at',
                'paused_by_user_id',
                'pause_reason',
                'has_active_claim',
                'has_integration_error',
            ]);
        });
    }
};
