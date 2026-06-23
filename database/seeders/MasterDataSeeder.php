<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. Truncate tables in reverse dependency order
        $tables = ['activity_coa', 'activities', 'coas', 'work_plans', 'coa_groups', 'report_groups'];
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        Schema::enableForeignKeyConstraints();

        $dataDir = database_path('seeders/data');

        // Helper function to seed in chunks from json file
        $seedTable = function ($tableName) use ($dataDir) {
            $filePath = "$dataDir/$tableName.json";
            if (!File::exists($filePath)) {
                $this->command->warn("Seeder file not found: $filePath");
                return;
            }

            $jsonData = json_decode(File::get($filePath), true);
            if (!is_array($jsonData)) {
                $this->command->error("Invalid JSON format in $filePath");
                return;
            }

            $chunks = array_chunk($jsonData, 100);
            foreach ($chunks as $chunk) {
                DB::table($tableName)->insert($chunk);
            }

            $this->command->info("Seeded table: $tableName | Count: " . count($jsonData));
        };

        // 2. Seed tables in dependency order
        $seedTable('report_groups');
        $seedTable('coa_groups');
        $seedTable('work_plans');
        $seedTable('coas');
        $seedTable('activities');
        $seedTable('activity_coa');

        // 3. Reset PostgreSQL sequences to avoid primary key out-of-sync unique constraint violations
        if (DB::getDriverName() === 'pgsql') {
            $tables = ['report_groups', 'coa_groups', 'work_plans', 'coas', 'activities'];
            foreach ($tables as $table) {
                if (Schema::hasColumn($table, 'id')) {
                    DB::statement("SELECT setval(pg_get_serial_sequence('$table', 'id'), COALESCE(MAX(id), 1)) FROM $table");
                }
            }
        }
    }
}
