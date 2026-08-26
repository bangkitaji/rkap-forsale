<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rkap_trend_justifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkap_work_plan_id')->constrained('rkap_work_plans')->cascadeOnDelete();
            $table->foreignId('current_period_id')->constrained('rkap_periods')->cascadeOnDelete();
            $table->foreignId('proposal_period_id')->constrained('rkap_periods')->cascadeOnDelete();
            $table->text('justification_deviation_projection')->nullable();
            $table->text('justification_deviation_proposal')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rkap_work_plan_id', 'current_period_id', 'proposal_period_id'], 'trend_justification_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rkap_trend_justifications');
    }
};
