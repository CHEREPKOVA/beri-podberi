<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->string('company_type')->nullable();
            $table->string('company_status')->default('active');
            $table->string('company_region')->nullable();
            $table->string('company_legal_name')->nullable();
            $table->string('company_contact_email')->nullable();
            $table->string('company_contact_phone')->nullable();
            $table->json('company_params')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
            $table->index(['company_name', 'company_type'], 'role_user_company_name_type_idx');
            $table->index('company_status', 'role_user_company_status_idx');
            $table->index('company_region', 'role_user_company_region_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
