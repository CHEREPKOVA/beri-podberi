<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('working_hours')->nullable()->after('notes');
        });

        // Переход с старых типов на ТЗ: основной, временный, транзитный
        DB::statement("ALTER TABLE warehouses MODIFY type VARCHAR(50) NOT NULL DEFAULT 'main'");

        $map = [
            'production' => 'temporary',
            'central' => 'main',
            'distribution' => 'transit',
        ];
        foreach ($map as $old => $new) {
            DB::table('warehouses')->where('type', $old)->update(['type' => $new]);
        }

        DB::statement("ALTER TABLE warehouses MODIFY type ENUM('main','temporary','transit') NOT NULL DEFAULT 'main'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE warehouses MODIFY type ENUM('production','central','distribution') NOT NULL DEFAULT 'central'");

        $map = [
            'main' => 'central',
            'temporary' => 'production',
            'transit' => 'distribution',
        ];
        foreach ($map as $old => $new) {
            DB::table('warehouses')->where('type', $old)->update(['type' => $new]);
        }

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('working_hours');
        });
    }
};
