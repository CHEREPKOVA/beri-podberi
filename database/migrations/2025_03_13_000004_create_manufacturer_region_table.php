<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturer_region', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('region_id')->constrained()->onDelete('cascade');
            $table->boolean('is_primary')->default(false); // Основной регион
            $table->timestamps();
            
            $table->unique(['manufacturer_profile_id', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturer_region');
    }
};
