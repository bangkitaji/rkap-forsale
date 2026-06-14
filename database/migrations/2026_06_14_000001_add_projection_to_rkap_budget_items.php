<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rkap_budget_items', function (Blueprint $table) {
            $table->decimal('projection', 18, 2)->default(0)->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('rkap_budget_items', function (Blueprint $table) {
            $table->dropColumn('projection');
        });
    }
};
