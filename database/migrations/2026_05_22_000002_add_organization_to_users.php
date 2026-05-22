<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('bureau_id')->nullable()->after('remember_token')->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('bureau_id')->constrained()->nullOnDelete();
            $table->foreignId('directorate_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->string('position')->nullable()->after('directorate_id'); // Jabatan
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['bureau_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['directorate_id']);
            $table->dropColumn(['bureau_id', 'department_id', 'directorate_id', 'position']);
        });
    }
};
