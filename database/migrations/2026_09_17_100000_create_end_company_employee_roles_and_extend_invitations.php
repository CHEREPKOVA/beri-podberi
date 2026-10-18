<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('end_company_employee_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('end_company_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('permissions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('end_company_profile_id', 'end_emp_roles_profile_idx');
        });

        Schema::table('user_invitations', function (Blueprint $table) {
            $table->foreignId('end_company_profile_id')->nullable()->after('distributor_employee_role_id')->constrained()->nullOnDelete();
            $table->foreignId('end_company_employee_role_id')->nullable()->after('end_company_profile_id')->constrained('end_company_employee_roles')->nullOnDelete();
        });

        Schema::table('platform_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_orders', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')->nullable()->after('end_company_profile_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_orders', function (Blueprint $table) {
            if (Schema::hasColumn('platform_orders', 'created_by_user_id')) {
                $table->dropConstrainedForeignId('created_by_user_id');
            }
        });

        Schema::table('user_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('end_company_employee_role_id');
            $table->dropConstrainedForeignId('end_company_profile_id');
        });

        Schema::dropIfExists('end_company_employee_roles');
    }
};
