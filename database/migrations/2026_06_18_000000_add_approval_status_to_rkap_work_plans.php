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
        Schema::table('rkap_work_plans', function (Blueprint $table) {
            $table->string('approval_status')->default('pending')->after('sort_order');
            $table->text('revision_notes')->nullable()->after('approval_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rkap_work_plans', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'revision_notes']);
        });
    }
};
