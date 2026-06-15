<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_terminal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('order_statuses')->insert([
            ['slug' => 'new', 'name' => 'Новый', 'description' => 'Заказ создан и ожидает обработки', 'sort_order' => 1, 'is_terminal' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'processing', 'name' => 'В обработке', 'description' => 'Заказ принят в работу', 'sort_order' => 2, 'is_terminal' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'completed', 'name' => 'Выполнен', 'description' => 'Заказ успешно завершён', 'sort_order' => 3, 'is_terminal' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'cancelled', 'name' => 'Отменён', 'description' => 'Заказ отменён', 'sort_order' => 4, 'is_terminal' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_statuses');
    }
};
