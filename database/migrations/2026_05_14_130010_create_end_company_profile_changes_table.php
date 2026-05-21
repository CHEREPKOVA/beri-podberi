<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('end_company_profile_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('end_company_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('section', 64);
            $table->string('summary', 500);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['end_company_profile_id', 'created_at'], 'ecc_prof_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('end_company_profile_changes');
    }
};
