<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Direktorat
        Schema::create('directorates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Departemen
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('directorate_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_verifier')->default(false); // Departemen verifikator RKAP?
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Biro
        Schema::create('bureaus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bureaus');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('directorates');
    }
};
