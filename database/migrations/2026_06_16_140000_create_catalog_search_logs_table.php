<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role_slug', 64)->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->string('query', 255);
            $table->string('query_normalized', 255)->index();
            $table->unsignedInteger('results_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['role_slug', 'region_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_search_logs');
    }
};
