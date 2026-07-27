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
        Schema::table('cashflow_groups', function (Blueprint $table) {
            $table->foreignId('cds_group_id')->nullable()->constrained('cds_groups')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashflow_groups', function (Blueprint $table) {
            $table->dropForeign(['cds_group_id']);
            $table->dropColumn('cds_group_id');
        });
    }
};
