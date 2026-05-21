<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address');
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['main', 'regional', 'store'])->default('main');
            $table->string('responsible_person')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->string('working_hours')->nullable();
            $table->string('shipping_conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_warehouses');
    }
};
