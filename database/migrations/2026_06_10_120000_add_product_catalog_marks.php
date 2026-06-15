<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('mark_is_new')->default(false)->after('show_in_catalog');
            $table->boolean('mark_on_sale')->default(false)->after('mark_is_new');
            $table->boolean('mark_discontinued')->default(false)->after('mark_on_sale');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['mark_is_new', 'mark_on_sale', 'mark_discontinued']);
        });
    }
};
