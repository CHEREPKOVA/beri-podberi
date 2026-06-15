<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64);
            $table->string('name');
            $table->string('context', 50);
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['slug', 'context']);
            $table->index(['context', 'sort_order']);
        });

        $now = now();
        $rows = [
            // Производитель
            ['slug' => 'registration_certificate', 'name' => 'Свидетельство о регистрации', 'context' => 'manufacturer_profile', 'sort_order' => 1],
            ['slug' => 'company_card', 'name' => 'Карточка предприятия', 'context' => 'manufacturer_profile', 'sort_order' => 2],
            ['slug' => 'license', 'name' => 'Лицензия', 'context' => 'manufacturer_profile', 'sort_order' => 3],
            ['slug' => 'product_certificate', 'name' => 'Сертификат продукции', 'context' => 'manufacturer_profile', 'sort_order' => 4],
            ['slug' => 'distribution_agreement', 'name' => 'Договор дистрибуции', 'context' => 'manufacturer_profile', 'sort_order' => 5],
            ['slug' => 'other', 'name' => 'Другое', 'context' => 'manufacturer_profile', 'sort_order' => 99],
            // Дистрибьютор (профиль)
            ['slug' => 'registration_certificate', 'name' => 'Свидетельство о регистрации', 'context' => 'distributor_profile', 'sort_order' => 1],
            ['slug' => 'company_card', 'name' => 'Карточка предприятия', 'context' => 'distributor_profile', 'sort_order' => 2],
            ['slug' => 'license', 'name' => 'Лицензия', 'context' => 'distributor_profile', 'sort_order' => 3],
            ['slug' => 'distribution_agreement', 'name' => 'Договор дистрибуции', 'context' => 'distributor_profile', 'sort_order' => 4],
            ['slug' => 'other', 'name' => 'Другое', 'context' => 'distributor_profile', 'sort_order' => 99],
            // Конечная компания
            ['slug' => 'charter', 'name' => 'Устав / учредительные документы', 'context' => 'end_company_profile', 'sort_order' => 1],
            ['slug' => 'company_card', 'name' => 'Карточка предприятия', 'context' => 'end_company_profile', 'sort_order' => 2],
            ['slug' => 'power_of_attorney', 'name' => 'Доверенность', 'context' => 'end_company_profile', 'sort_order' => 3],
            ['slug' => 'requisites_pdf', 'name' => 'Реквизиты (PDF)', 'context' => 'end_company_profile', 'sort_order' => 4],
            ['slug' => 'contract', 'name' => 'Договор', 'context' => 'end_company_profile', 'sort_order' => 5],
            ['slug' => 'other', 'name' => 'Прочее', 'context' => 'end_company_profile', 'sort_order' => 99],
            // Товар производителя
            ['slug' => 'certificate', 'name' => 'Сертификат', 'context' => 'product', 'sort_order' => 1],
            ['slug' => 'instruction', 'name' => 'Инструкция', 'context' => 'product', 'sort_order' => 2],
            ['slug' => 'datasheet', 'name' => 'Техническая документация', 'context' => 'product', 'sort_order' => 3],
            ['slug' => 'other', 'name' => 'Другое', 'context' => 'product', 'sort_order' => 99],
            // Товар дистрибьютора
            ['slug' => 'certificate', 'name' => 'Сертификат', 'context' => 'distributor_product', 'sort_order' => 1],
            ['slug' => 'passport', 'name' => 'Паспорт', 'context' => 'distributor_product', 'sort_order' => 2],
            ['slug' => 'instruction', 'name' => 'Инструкция', 'context' => 'distributor_product', 'sort_order' => 3],
            ['slug' => 'manufacturer', 'name' => 'Файл производителя', 'context' => 'distributor_product', 'sort_order' => 4],
            ['slug' => 'internal', 'name' => 'Внутренний документ', 'context' => 'distributor_product', 'sort_order' => 5],
            ['slug' => 'other', 'name' => 'Другое', 'context' => 'distributor_product', 'sort_order' => 99],
        ];

        DB::table('document_types')->insert(array_map(
            fn (array $row) => array_merge($row, [
                'description' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            $rows
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
