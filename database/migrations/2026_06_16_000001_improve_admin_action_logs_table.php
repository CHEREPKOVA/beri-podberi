<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_action_logs', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
        });

        Schema::table('admin_action_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('admin_id')->nullable()->change();
            $table->string('admin_name')->nullable()->after('admin_id');
            $table->string('required_permission')->nullable()->after('action');
        });

        Schema::table('admin_action_logs', function (Blueprint $table) {
            $table->foreign('admin_id')->references('id')->on('users')->nullOnDelete();
            $table->index('admin_id');
            $table->index('created_at');
        });

        DB::table('admin_action_logs')
            ->whereNull('admin_name')
            ->whereNotNull('admin_id')
            ->orderBy('id')
            ->chunkById(200, function ($logs): void {
                foreach ($logs as $log) {
                    $name = DB::table('users')->where('id', $log->admin_id)->value('name');
                    if ($name !== null) {
                        DB::table('admin_action_logs')->where('id', $log->id)->update(['admin_name' => $name]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('admin_action_logs', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('admin_action_logs', function (Blueprint $table) {
            $table->dropColumn(['admin_name', 'required_permission']);
            $table->unsignedBigInteger('admin_id')->nullable(false)->change();
        });

        Schema::table('admin_action_logs', function (Blueprint $table) {
            $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
