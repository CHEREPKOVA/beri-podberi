<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название региона
            $table->string('code', 10)->nullable(); // Код региона (например, 77 для Москвы)
            $table->string('federal_district')->nullable(); // Федеральный округ
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique('name');
            $table->index('federal_district');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
