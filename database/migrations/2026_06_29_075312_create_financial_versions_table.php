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
        Schema::create('financial_versions', function (Blueprint $table) {
            $table->id('version_id');
            $table->string('name'); // e.g., CBP, Audited, Prognosa
            $table->integer('year'); // e.g., 2025, 2026
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_versions');
    }
};
