<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_orders', function (Blueprint $table) {
            $table->foreignId('manufacturer_responsible_contact_id')->nullable()
                ->after('responsible_contact_id')
                ->constrained('manufacturer_contacts')
                ->nullOnDelete();
        });

        Schema::create('platform_order_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('creator_role', 32)->default('manufacturer');
            $table->string('reason', 64);
            $table->text('description')->nullable();
            $table->foreignId('claim_status_id')->nullable()->constrained('claim_statuses')->nullOnDelete();
            $table->string('status_slug', 64)->nullable();
            $table->timestamps();

            $table->index(['platform_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_order_claims');

        Schema::table('platform_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manufacturer_responsible_contact_id');
        });
    }
};
