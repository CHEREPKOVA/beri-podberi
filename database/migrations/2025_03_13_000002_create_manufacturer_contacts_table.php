<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_profile_id')->constrained()->onDelete('cascade');
            
            $table->string('full_name'); // ФИО контактного лица
            $table->string('position')->nullable(); // Должность
            $table->string('email');
            $table->string('phone')->nullable();
            $table->boolean('is_primary')->default(false); // Главный контакт (нельзя удалить)
            $table->string('department')->nullable(); // Отдел (продажи, техподдержка и т.д.)
            $table->text('notes')->nullable(); // Примечания
            
            $table->timestamps();
            
            $table->index('manufacturer_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturer_contacts');
    }
};
