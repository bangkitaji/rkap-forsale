<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rkap_work_plans', function (Blueprint $table) {
            $table->boolean('is_past_period_payment')->default(false)->after('added_by_verifier');
            $table->foreignId('past_period_id')
                  ->nullable()
                  ->after('is_past_period_payment')
                  ->constrained('rkap_periods')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rkap_work_plans', function (Blueprint $table) {
            $table->dropForeign(['past_period_id']);
            $table->dropColumn(['is_past_period_payment', 'past_period_id']);
        });
    }
};
