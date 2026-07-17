<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkap_period_id')->constrained('rkap_periods')->cascadeOnDelete();
            $table->foreignId('source_bureau_id')->constrained('bureaus')->cascadeOnDelete();
            $table->foreignId('target_bureau_id')->constrained('bureaus')->cascadeOnDelete();
            $table->foreignId('source_submission_id')->constrained('rkap_submissions')->cascadeOnDelete();
            $table->foreignId('target_submission_id')->nullable()->constrained('rkap_submissions')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->text('notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->decimal('total_amount', 20, 2)->default(0);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_transfer_id')->constrained('budget_transfers')->cascadeOnDelete();
            $table->foreignId('rkap_work_plan_id')->constrained('rkap_work_plans')->cascadeOnDelete();
            $table->json('snapshot_data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_transfer_items');
        Schema::dropIfExists('budget_transfers');
    }
};
