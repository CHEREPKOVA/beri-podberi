<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_orders', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('buyer_comment');
            $table->string('tracking_number', 128)->nullable()->after('rejection_reason');
            $table->timestamp('shipped_at')->nullable()->after('tracking_number');
            $table->string('shipping_from_warehouse')->nullable()->after('shipped_at');
            $table->timestamp('status_changed_at')->nullable()->after('shipping_from_warehouse');
            $table->unsignedBigInteger('status_changed_by_user_id')->nullable()->after('status_changed_at');
        });

        Schema::table('platform_orders', function (Blueprint $table) {
            $table->foreign('status_changed_by_user_id', 'plat_ord_status_user_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('platform_order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->string('action', 64)->nullable();
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['platform_order_id', 'created_at'], 'plat_ord_status_log_idx');
            $table->foreign('performed_by_user_id', 'plat_ord_status_log_user_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // Миграция старых статусов заказов
        DB::table('platform_orders')->where('status', 'processing')->update(['status' => 'awaiting_confirmation']);
        DB::table('platform_orders')->where('status', 'cancelled')->update(['status' => 'rejected']);

        $now = now();

        // Деактивируем старые статусы, которых нет в ТЗ
        DB::table('order_statuses')->whereIn('slug', ['processing', 'cancelled'])->update([
            'is_active' => false,
            'updated_at' => $now,
        ]);

        $statuses = [
            [
                'slug' => 'new',
                'name' => 'Новый',
                'description' => 'Заказ создан покупателем и отправлен поставщику',
                'sort_order' => 1,
                'is_terminal' => false,
            ],
            [
                'slug' => 'awaiting_confirmation',
                'name' => 'Ожидает подтверждения',
                'description' => 'Поставщик ознакомился с заказом и принимает решение',
                'sort_order' => 2,
                'is_terminal' => false,
            ],
            [
                'slug' => 'confirmed',
                'name' => 'Подтверждён',
                'description' => 'Поставщик принял заказ к исполнению',
                'sort_order' => 3,
                'is_terminal' => false,
            ],
            [
                'slug' => 'needs_approval',
                'name' => 'Ожидает подтверждения',
                'description' => 'Поставщик изменил заказ, ожидается подтверждение покупателя',
                'sort_order' => 4,
                'is_terminal' => false,
            ],
            [
                'slug' => 'rejected',
                'name' => 'Отклонён',
                'description' => 'Заказ отклонён поставщиком или покупателем при согласовании',
                'sort_order' => 5,
                'is_terminal' => true,
            ],
            [
                'slug' => 'ready_to_ship',
                'name' => 'Готов к отгрузке',
                'description' => 'Товар собран и подготовлен к отправке',
                'sort_order' => 6,
                'is_terminal' => false,
            ],
            [
                'slug' => 'shipped',
                'name' => 'Отгружен',
                'description' => 'Товар отправлен покупателю',
                'sort_order' => 7,
                'is_terminal' => false,
            ],
            [
                'slug' => 'completed',
                'name' => 'Завершён',
                'description' => 'Заказ закрыт, получение подтверждено',
                'sort_order' => 8,
                'is_terminal' => true,
            ],
        ];

        foreach ($statuses as $status) {
            $existing = DB::table('order_statuses')->where('slug', $status['slug'])->first();
            if ($existing) {
                DB::table('order_statuses')->where('id', $existing->id)->update([
                    'name' => $status['name'],
                    'description' => $status['description'],
                    'sort_order' => $status['sort_order'],
                    'is_terminal' => $status['is_terminal'],
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('order_statuses')->insert([
                    ...$status,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Переименовать старый completed если name был "Выполнен"
        DB::table('order_statuses')->where('slug', 'completed')->update([
            'name' => 'Завершён',
            'description' => 'Заказ закрыт, получение подтверждено',
            'is_terminal' => true,
            'is_active' => true,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('platform_orders')->where('status', 'awaiting_confirmation')->update(['status' => 'processing']);
        DB::table('platform_orders')->whereIn('status', [
            'confirmed', 'needs_approval', 'rejected', 'ready_to_ship', 'shipped',
        ])->update(['status' => 'processing']);

        Schema::dropIfExists('platform_order_status_logs');

        Schema::table('platform_orders', function (Blueprint $table) {
            $table->dropForeign('plat_ord_status_user_fk');
            $table->dropColumn([
                'rejection_reason',
                'tracking_number',
                'shipped_at',
                'shipping_from_warehouse',
                'status_changed_at',
                'status_changed_by_user_id',
            ]);
        });
    }
};
