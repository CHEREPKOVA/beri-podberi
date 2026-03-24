<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->string('company_type')->nullable()->after('company_name');
            $table->string('company_status')->default('active')->after('company_type');
            $table->string('company_region')->nullable()->after('company_status');
            $table->string('company_legal_name')->nullable()->after('company_region');
            $table->string('company_contact_email')->nullable()->after('company_legal_name');
            $table->string('company_contact_phone')->nullable()->after('company_contact_email');
            $table->json('company_params')->nullable()->after('company_contact_phone');

            $table->index(['company_name', 'company_type'], 'role_user_company_name_type_idx');
            $table->index('company_status', 'role_user_company_status_idx');
            $table->index('company_region', 'role_user_company_region_idx');
        });

        DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('roles.slug', ['manufacturer', 'distributor', 'end_company'])
            ->update([
                'role_user.company_type' => DB::raw('roles.slug'),
                'role_user.company_status' => 'active',
            ]);
    }

    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropIndex('role_user_company_name_type_idx');
            $table->dropIndex('role_user_company_status_idx');
            $table->dropIndex('role_user_company_region_idx');

            $table->dropColumn([
                'company_type',
                'company_status',
                'company_region',
                'company_legal_name',
                'company_contact_email',
                'company_contact_phone',
                'company_params',
            ]);
        });
    }
};
