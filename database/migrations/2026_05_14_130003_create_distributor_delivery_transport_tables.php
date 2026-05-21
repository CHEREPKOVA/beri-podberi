<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_delivery_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_method_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['distributor_profile_id', 'delivery_method_id'], 'distributor_delivery_unique');
        });

        Schema::create('distributor_transport_company', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transport_company_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['distributor_profile_id', 'transport_company_id'], 'distributor_transport_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_transport_company');
        Schema::dropIfExists('distributor_delivery_settings');
    }
};
