<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_employee_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('permissions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('distributor_profile_id', 'dst_emp_roles_profile_idx');
        });

        Schema::table('user_invitations', function (Blueprint $table) {
            $table->foreignId('distributor_profile_id')->nullable()->after('employee_role_id')->constrained()->nullOnDelete();
            $table->foreignId('distributor_employee_role_id')->nullable()->after('distributor_profile_id')->constrained('distributor_employee_roles')->nullOnDelete();
        });

        Schema::table('distributor_contacts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('distributor_profile_id')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('is_primary');
        });
    }

    public function down(): void
    {
        Schema::table('distributor_contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('is_active');
        });

        Schema::table('user_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('distributor_employee_role_id');
            $table->dropConstrainedForeignId('distributor_profile_id');
        });

        Schema::dropIfExists('distributor_employee_roles');
    }
};
