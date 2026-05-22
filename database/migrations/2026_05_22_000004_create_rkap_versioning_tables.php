<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Metadata setiap versi
        Schema::create('rkap_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkap_submission_id')->constrained()->cascadeOnDelete();
            $table->integer('version_number');
            $table->foreignId('created_by')->constrained('users');
            $table->string('change_type'); // 'initial', 'revision', 'dept_revision', 'dir_revision'
            $table->text('change_reason')->nullable();
            $table->decimal('total_budget', 20, 2)->default(0);
            $table->json('snapshot_data'); // JSON snapshot lengkap rencana kerja & anggaran
            $table->timestamp('created_at')->nullable();

            $table->unique(['rkap_submission_id', 'version_number']);
        });

        // Log persetujuan/penolakan
        Schema::create('rkap_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkap_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->integer('version_number');
            $table->string('role'); // 'kepala_departemen', 'direksi', 'verifikator'
            $table->enum('action', ['approved', 'rejected', 'revision_requested']);
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        // Pembahasan/Diskusi
        Schema::create('rkap_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkap_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->integer('version_number')->nullable();
            $table->text('content');
            $table->foreignId('parent_id')->nullable()->constrained('rkap_comments')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rkap_comments');
        Schema::dropIfExists('rkap_approvals');
        Schema::dropIfExists('rkap_versions');
    }
};
