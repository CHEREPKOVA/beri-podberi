<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Справочник транспортных компаний (задается администратором)
        Schema::create('transport_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название ТК
            $table->string('slug')->unique();
            $table->string('website')->nullable();
            $table->string('tracking_url')->nullable(); // URL для отслеживания
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_companies');
    }
};
