<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_company_delivery_addresses', function (Blueprint $table) {
            $table->foreignId('contact_id')
                ->nullable()
                ->after('region_id')
                ->constrained('end_company_contacts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('end_company_delivery_addresses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
        });
    }
};

