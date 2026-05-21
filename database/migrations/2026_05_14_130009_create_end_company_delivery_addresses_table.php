<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('end_company_delivery_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('end_company_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address');
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact_person')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('working_hours')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('end_company_delivery_addresses');
    }
};
