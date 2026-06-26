<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rkap_work_plans', function (Blueprint $table) {
            $table->boolean('added_by_verifier')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('rkap_work_plans', function (Blueprint $table) {
            $table->dropColumn('added_by_verifier');
        });
    }
};
