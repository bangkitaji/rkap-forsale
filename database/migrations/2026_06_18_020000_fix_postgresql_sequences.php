<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $tables = ['coa_groups', 'work_plans', 'coas', 'activities', 'satuans'];
            foreach ($tables as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'id')) {
                    DB::statement("SELECT setval(pg_get_serial_sequence('$table', 'id'), COALESCE(MAX(id), 1)) FROM $table");
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
