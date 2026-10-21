<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('end_company_action_logs')) {
            return;
        }

        Schema::create('end_company_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('end_company_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('action', 64);
            $table->string('description', 500);
            $table->string('target_type', 64)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['end_company_profile_id', 'created_at'], 'end_co_action_logs_profile_created_idx');
            $table->index(['end_company_profile_id', 'action'], 'end_co_action_logs_profile_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('end_company_action_logs');
    }
};
