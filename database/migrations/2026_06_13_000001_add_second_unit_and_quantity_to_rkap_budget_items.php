<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rkap_budget_items', function (Blueprint $table) {
            $table->string('unit_2')->nullable()->after('quantity');
            $table->integer('quantity_2')->nullable()->after('unit_2');
        });
    }

    public function down(): void
    {
        Schema::table('rkap_budget_items', function (Blueprint $table) {
            $table->dropColumn(['unit_2', 'quantity_2']);
        });
    }
};
