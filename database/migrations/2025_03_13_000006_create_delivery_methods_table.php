<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Справочник способов доставки (задается администратором)
        Schema::create('delivery_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название способа доставки
            $table->string('slug')->unique(); // self_pickup, transport_company, own_transport
            $table->text('description')->nullable();
            $table->boolean('requires_tracking')->default(false); // Требуется ли трек-номер
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_methods');
    }
};
