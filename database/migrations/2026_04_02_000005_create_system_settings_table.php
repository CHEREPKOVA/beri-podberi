<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 50);
            $table->string('key', 100)->unique();
            $table->string('label');
            $table->string('value')->nullable();
            $table->string('value_type', 20)->default('string');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['group_key', 'sort_order']);
        });

        $now = now();

        DB::table('system_settings')->insert([
            ['group_key' => 'notifications', 'key' => 'notifications.email_enabled', 'label' => 'Email-уведомления включены', 'value' => '1', 'value_type' => 'boolean', 'description' => 'Глобальный флаг отправки уведомлений на email.', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['group_key' => 'notifications', 'key' => 'notifications.sms_enabled', 'label' => 'SMS-уведомления включены', 'value' => '0', 'value_type' => 'boolean', 'description' => 'Глобальный флаг отправки SMS-уведомлений.', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['group_key' => 'timings', 'key' => 'timings.order_pending_hours', 'label' => 'Часы ожидания заказа в статусе pending', 'value' => '24', 'value_type' => 'integer', 'description' => 'По истечении периода система может напоминать ответственным.', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['group_key' => 'timings', 'key' => 'timings.claim_response_hours', 'label' => 'Часы на ответ по претензии', 'value' => '48', 'value_type' => 'integer', 'description' => 'SLA по первичному ответу на претензию.', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['group_key' => 'limits', 'key' => 'limits.max_failed_logins', 'label' => 'Максимум неудачных входов', 'value' => '5', 'value_type' => 'integer', 'description' => 'После превышения лимита учётная запись может быть временно заблокирована.', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['group_key' => 'display', 'key' => 'display.default_page_size', 'label' => 'Размер страницы по умолчанию', 'value' => '25', 'value_type' => 'integer', 'description' => 'Количество строк в списках по умолчанию.', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['group_key' => 'security', 'key' => 'security.password_min_length', 'label' => 'Минимальная длина пароля', 'value' => '8', 'value_type' => 'integer', 'description' => 'Базовое требование к паролям пользователей.', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
