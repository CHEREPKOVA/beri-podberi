<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_orders', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('shipped_at');
            $table->text('completion_notes')->nullable()->after('received_at');
        });

        Schema::table('platform_order_items', function (Blueprint $table) {
            $table->unsignedInteger('pack_quantity')->nullable()->after('quantity');
            $table->unsignedInteger('min_order_quantity')->nullable()->after('pack_quantity');
        });

        Schema::create('platform_order_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploader_role', 32);
            $table->string('name');
            $table->string('type', 64);
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['platform_order_id', 'type']);
            $table->index(['platform_order_id', 'uploader_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_order_documents');

        Schema::table('platform_order_items', function (Blueprint $table) {
            $table->dropColumn(['pack_quantity', 'min_order_quantity']);
        });

        Schema::table('platform_orders', function (Blueprint $table) {
            $table->dropColumn(['received_at', 'completion_notes']);
        });
    }
};
