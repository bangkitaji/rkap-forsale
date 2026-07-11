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
        Schema::table('rkap_submissions', function (Blueprint $table) {
            $table->index(['rkap_period_id', 'status', 'bureau_id'], 'idx_submissions_period_status_bureau');
        });

        Schema::table('rkap_budget_items', function (Blueprint $table) {
            $table->index('rkap_work_plan_id', 'idx_budget_items_work_plan');
            $table->index('account_code', 'idx_budget_items_account_code');
        });

        Schema::table('rkap_work_plans', function (Blueprint $table) {
            $table->index('rkap_submission_id', 'idx_work_plans_submission');
        });

        Schema::table('coas', function (Blueprint $table) {
            $table->index(['coa_group_id', 'deleted_at'], 'idx_coas_group_deleted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coas', function (Blueprint $table) {
            $table->dropIndex('idx_coas_group_deleted');
        });

        Schema::table('rkap_work_plans', function (Blueprint $table) {
            $table->dropIndex('idx_work_plans_submission');
        });

        Schema::table('rkap_budget_items', function (Blueprint $table) {
            $table->dropIndex('idx_budget_items_account_code');
            $table->dropIndex('idx_budget_items_work_plan');
        });

        Schema::table('rkap_submissions', function (Blueprint $table) {
            $table->dropIndex('idx_submissions_period_status_bureau');
        });
    }
};
