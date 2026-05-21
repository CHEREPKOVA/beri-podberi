<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('end_company_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('full_name');
            $table->string('short_name')->nullable();
            $table->enum('legal_form', ['ooo', 'ip', 'pao', 'ao', 'gos'])->default('ooo');
            $table->string('inn', 12);
            $table->string('kpp', 9)->nullable();
            $table->string('ogrn', 15)->nullable();
            $table->string('legal_address')->nullable();
            $table->string('actual_address')->nullable();
            $table->string('director_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bik', 9)->nullable();
            $table->string('checking_account', 20)->nullable();
            $table->string('correspondent_account', 20)->nullable();
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->json('locked_fields')->nullable();

            $table->boolean('integration_edi_enabled')->default(false);
            $table->string('integration_webhook_url')->nullable();
            $table->text('integration_comment')->nullable();

            $table->timestamps();

            $table->unique('user_id');
            $table->index('inn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('end_company_profiles');
    }
};
