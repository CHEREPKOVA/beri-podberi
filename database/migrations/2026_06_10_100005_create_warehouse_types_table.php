<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('applies_to', 20)->default('both');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('warehouse_types')->insert([
            ['slug' => 'main', 'name' => 'Основной', 'description' => 'Главный склад компании', 'applies_to' => 'both', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'temporary', 'name' => 'Временный', 'description' => 'Временный склад производителя', 'applies_to' => 'manufacturer', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'transit', 'name' => 'Транзитный', 'description' => 'Транзитный склад производителя', 'applies_to' => 'manufacturer', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'regional', 'name' => 'Региональный', 'description' => 'Региональный склад дистрибьютора', 'applies_to' => 'distributor', 'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'store', 'name' => 'Склад-магазин', 'description' => 'Розничный склад дистрибьютора', 'applies_to' => 'distributor', 'sort_order' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_types');
    }
};
