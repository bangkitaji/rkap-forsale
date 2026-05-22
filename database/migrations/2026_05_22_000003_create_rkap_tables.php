<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Periode penyusunan RKAP
        Schema::create('rkap_periods', function (Blueprint $table) {
            $table->id();
            $table->year('year');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'open', 'closed', 'finalized'])->default('draft');
            $table->date('submission_start')->nullable();
            $table->date('submission_end')->nullable();
            $table->timestamps();
        });

        // Pengajuan RKAP per Biro
        Schema::create('rkap_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkap_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bureau_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->integer('current_version')->default(1);
            $table->enum('status', [
                'draft',
                'submitted',
                'dept_review',
                'dept_approved',
                'dept_revision',
                'dir_review',
                'dir_approved',
                'dir_revision',
                'final_review',
                'final_revision',
                'approved',
            ])->default('draft');
            $table->decimal('total_budget', 20, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['rkap_period_id', 'bureau_id']);
        });

        // Rencana Kerja
        Schema::create('rkap_work_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkap_submission_id')->constrained()->cascadeOnDelete();
            $table->string('program_code', 20)->nullable();
            $table->string('program_name');
            $table->text('description')->nullable();
            $table->string('output_target')->nullable();
            $table->string('unit')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Rincian Anggaran per Rencana Kerja
        Schema::create('rkap_budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkap_work_plan_id')->constrained()->cascadeOnDelete();
            $table->string('account_code', 20)->nullable();
            $table->string('description');
            $table->string('unit')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('total_price', 20, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rkap_budget_items');
        Schema::dropIfExists('rkap_work_plans');
        Schema::dropIfExists('rkap_submissions');
        Schema::dropIfExists('rkap_periods');
    }
};
