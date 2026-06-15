<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_statuses', function (Blueprint $table) {
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

        DB::table('claim_statuses')->insert([
            ['slug' => 'new', 'name' => 'Новая', 'description' => 'Претензия зарегистрирована', 'sort_order' => 1, 'is_terminal' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'in_review', 'name' => 'На рассмотрении', 'description' => 'Претензия передана ответственному', 'sort_order' => 2, 'is_terminal' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'awaiting_response', 'name' => 'Ожидает ответа', 'description' => 'Ожидается ответ контрагента', 'sort_order' => 3, 'is_terminal' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'resolved', 'name' => 'Урегулирована', 'description' => 'Претензия урегулирована', 'sort_order' => 4, 'is_terminal' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'rejected', 'name' => 'Отклонена', 'description' => 'Претензия отклонена', 'sort_order' => 5, 'is_terminal' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'closed', 'name' => 'Закрыта', 'description' => 'Претензия закрыта без урегулирования', 'sort_order' => 6, 'is_terminal' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_statuses');
    }
};
