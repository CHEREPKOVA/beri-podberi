<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_profile_id')->constrained()->onDelete('cascade');
            
            $table->string('name'); // Название склада
            $table->string('address'); // Адрес
            $table->foreignId('region_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('type', ['production', 'central', 'distribution'])->default('central');
            // production - производственный, central - центральный, distribution - дистрибьюторский
            
            $table->string('responsible_person')->nullable(); // Ответственный сотрудник
            $table->string('phone')->nullable(); // Контактный телефон
            $table->text('notes')->nullable(); // Примечание
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('manufacturer_profile_id');
            $table->index('region_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
