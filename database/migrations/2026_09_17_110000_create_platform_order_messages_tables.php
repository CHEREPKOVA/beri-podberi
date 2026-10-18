<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_order_messages')) {
            Schema::create('platform_order_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('platform_order_id')->constrained('platform_orders')->cascadeOnDelete();
                $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('author_role', 32);
                $table->string('type', 16)->default('user');
                $table->text('body');
                $table->string('event_code', 64)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['platform_order_id', 'created_at'], 'po_messages_order_created_idx');
            });
        }

        if (! Schema::hasTable('platform_order_message_attachments')) {
            Schema::create('platform_order_message_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('platform_order_message_id');
                $table->foreign('platform_order_message_id', 'po_msg_att_msg_fk')
                    ->references('id')
                    ->on('platform_order_messages')
                    ->cascadeOnDelete();
                $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('file_path');
                $table->string('original_name');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('platform_order_message_reads')) {
            Schema::create('platform_order_message_reads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('platform_order_id')->constrained('platform_orders')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('last_read_at');
                $table->timestamps();

                $table->unique(['platform_order_id', 'user_id'], 'po_msg_reads_order_user_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_order_message_reads');
        Schema::dropIfExists('platform_order_message_attachments');
        Schema::dropIfExists('platform_order_messages');
    }
};
