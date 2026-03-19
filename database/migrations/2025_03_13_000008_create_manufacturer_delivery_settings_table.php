<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Настройки доставки производителя
        Schema::create('manufacturer_delivery_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('delivery_method_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['manufacturer_profile_id', 'delivery_method_id'], 'manufacturer_delivery_unique');
        });

        // Связь производителя с транспортными компаниями
        Schema::create('manufacturer_transport_company', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('transport_company_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['manufacturer_profile_id', 'transport_company_id'], 'manufacturer_transport_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturer_transport_company');
        Schema::dropIfExists('manufacturer_delivery_settings');
    }
};
