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
        Schema::table('work_plans', function (Blueprint $table) {
            $table->string('approval_status')->default('approved')->after('title');
            $table->foreignId('requested_by_bureau_id')->nullable()->after('approval_status')->constrained('bureaus')->nullOnDelete();
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->string('approval_status')->default('approved')->after('description');
            $table->foreignId('requested_by_bureau_id')->nullable()->after('approval_status')->constrained('bureaus')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_plans', function (Blueprint $table) {
            $table->dropForeign(['requested_by_bureau_id']);
            $table->dropColumn(['approval_status', 'requested_by_bureau_id']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['requested_by_bureau_id']);
            $table->dropColumn(['approval_status', 'requested_by_bureau_id']);
        });
    }
};
