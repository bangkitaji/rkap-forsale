<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rkap_work_plans', function (Blueprint $table) {
            // Make existing free-text fields nullable for backward compatibility
            $table->string('program_code')->nullable()->change();
            $table->string('program_name')->nullable()->change();

            // New FK references to master-data tables
            $table->foreignId('work_plan_id')
                ->nullable()
                ->after('rkap_submission_id')
                ->constrained('work_plans')
                ->nullOnDelete();

            $table->foreignId('activity_id')
                ->nullable()
                ->after('work_plan_id')
                ->constrained('activities')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rkap_work_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_id');
            $table->dropConstrainedForeignId('work_plan_id');
        });
    }
};
