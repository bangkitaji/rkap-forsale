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
        Schema::create('rkap_activity_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkap_work_plan_id')->constrained('rkap_work_plans')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('file_type');
            $table->bigInteger('file_size');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rkap_activity_files');
    }
};
