<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_profile_id')->constrained()->cascadeOnDelete();
            
            $table->string('name'); // Название документа
            $table->enum('type', [
                'registration_certificate', // Свидетельство о регистрации
                'company_card', // Карточка предприятия
                'license', // Лицензия
                'product_certificate', // Сертификат продукции
                'distribution_agreement', // Договор дистрибуции
                'other' // Другое
            ])->default('other');
            $table->string('file_path'); // Путь к файлу
            $table->string('original_name'); // Оригинальное имя файла
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // Размер в байтах
            $table->date('valid_until')->nullable(); // Срок действия (для лицензий/сертификатов)
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturer_documents');
    }
};
