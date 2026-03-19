<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Основная информация о компании
            $table->string('full_name'); // Полное название организации
            $table->string('short_name')->nullable(); // Сокращенное название
            $table->enum('legal_form', ['ooo', 'ip', 'pao', 'ao', 'gos'])->default('ooo'); // Юридическая форма
            $table->string('inn', 12); // ИНН
            $table->string('kpp', 9)->nullable(); // КПП (нет у ИП)
            $table->string('ogrn', 15)->nullable(); // ОГРН или ОГРНИП
            
            // Адреса
            $table->string('legal_address')->nullable(); // Юридический адрес
            $table->string('actual_address')->nullable(); // Фактический адрес
            
            // Банковские реквизиты
            $table->string('bank_name')->nullable(); // Название банка
            $table->string('bik', 9)->nullable(); // БИК
            $table->string('checking_account', 20)->nullable(); // Расчётный счёт
            $table->string('correspondent_account', 20)->nullable(); // Корреспондентский счёт
            
            // Дополнительно
            $table->string('logo')->nullable(); // Путь к логотипу
            $table->text('description')->nullable(); // Краткое описание (до 1000 символов)
            
            // Флаги редактирования (какие поля заблокированы админом)
            $table->json('locked_fields')->nullable(); // Поля, которые нельзя редактировать
            
            $table->timestamps();
            
            $table->unique('user_id');
            $table->index('inn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturer_profiles');
    }
};
