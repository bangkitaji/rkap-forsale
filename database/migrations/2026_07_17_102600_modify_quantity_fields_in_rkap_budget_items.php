<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rkap_budget_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->default(1)->change();
            $table->decimal('quantity_2', 15, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rkap_budget_items', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->change();
            $table->integer('quantity_2')->nullable()->change();
        });
    }
};
