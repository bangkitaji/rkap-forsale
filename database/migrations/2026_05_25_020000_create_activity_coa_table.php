<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('activity_coa', function (Blueprint $table) {
      $table->id();

      $table->foreignId('activity_id')
        ->constrained('activities')
        ->cascadeOnDelete();

      $table->foreignId('coa_id')
        ->constrained('coas')
        ->cascadeOnDelete();

      $table->timestamps();

      $table->unique(['activity_id', 'coa_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('activity_coa');
  }
};
