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
            $table->foreignId('manufacturer_profile_id')->constrained()->cascadeOnDelete();
            
            $table->string('name'); // Название склада
            $table->string('address'); // Адрес
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['main', 'temporary', 'transit'])->default('main');
            // main - основной, temporary - временный, transit - транзитный
            
            $table->string('responsible_person')->nullable(); // Ответственный сотрудник
            $table->string('phone')->nullable(); // Контактный телефон
            $table->text('notes')->nullable(); // Примечание
            $table->string('working_hours')->nullable(); // График работы склада
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
