<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('company_types')->insert([
            ['slug' => 'manufacturer', 'name' => 'Производитель (завод)', 'description' => 'Управление товарами и заказами от дистрибьюторов', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'distributor', 'name' => 'Дистрибьютор', 'description' => 'Закупка у производителей, заказы от компаний', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'end_company', 'name' => 'Конечная компания (Магазин / СТО)', 'description' => 'Заказы у дистрибьюторов', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_types');
    }
};
