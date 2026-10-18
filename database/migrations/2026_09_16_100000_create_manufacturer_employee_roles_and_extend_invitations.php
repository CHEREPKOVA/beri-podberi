<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturer_employee_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('permissions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('manufacturer_profile_id', 'mfr_emp_roles_profile_idx');
        });

        Schema::table('user_invitations', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('inviter_id')->constrained('users')->nullOnDelete();
            $table->foreignId('manufacturer_profile_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('employee_role_id')->nullable()->after('manufacturer_profile_id')->constrained('manufacturer_employee_roles')->nullOnDelete();
            $table->json('warehouse_ids')->nullable()->after('permissions');
            $table->timestamp('sent_at')->nullable()->after('expires_at');
            $table->timestamp('cancelled_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_role_id');
            $table->dropConstrainedForeignId('manufacturer_profile_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['warehouse_ids', 'sent_at', 'cancelled_at']);
        });

        Schema::dropIfExists('manufacturer_employee_roles');
    }
};
