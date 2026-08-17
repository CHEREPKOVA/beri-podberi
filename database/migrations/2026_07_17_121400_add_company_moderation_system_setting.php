<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'registration.require_company_moderation'],
            [
                'group_key' => 'registration',
                'label' => 'Требуется модерация новых аккаунтов',
                'value' => '0',
                'value_type' => 'boolean',
                'description' => 'Если включено, после регистрации компания получает статус «Ожидает одобрения» и не может войти в систему до активации администратором.',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('key', 'registration.require_company_moderation')
            ->delete();
    }
};
