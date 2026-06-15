<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federal_districts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $districts = [
            'Центральный',
            'Северо-Западный',
            'Южный',
            'Северо-Кавказский',
            'Приволжский',
            'Уральский',
            'Сибирский',
            'Дальневосточный',
        ];

        DB::table('federal_districts')->insert(array_map(
            fn (string $name, int $index) => [
                'name' => $name,
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $districts,
            array_keys($districts)
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('federal_districts');
    }
};
