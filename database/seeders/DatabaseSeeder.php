<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Core seeders (always required for every company installation)
        $this->call([
            RoleAndUserSeeder::class,
            RkapSeeder::class,
            SatuanSeeder::class,
            CoaProfitLossMappingSeeder::class,
        ]);

        // Optional / Sample dataset seeders (useful for demo/development environments)
        if (config('rkap.seed_sample_data', false)) {
            $this->command->info('Seeding sample datasets and mappings (RKAP_SEED_SAMPLE_DATA=true)...');
            $this->call([
                CashflowGroupSeeder::class,
                DifferenceGroupSeeder::class,
                MasterDataSeeder::class,
                CoaCfTypeMappingSeeder::class,
                CashflowReportGroupMappingSeeder::class,
                CashFlowSeeder::class,
                CdsGroupSeeder::class,
            ]);
        } else {
            $this->command->info('Sample datasets skipped (RKAP_SEED_SAMPLE_DATA=false). Clean baseline ready.');
        }

        // Optional / Demo showcase seeder
        if (config('rkap.seed_demo_data', false)) {
            $this->command->info('Seeding full demo showcase dataset (RKAP_SEED_DEMO_DATA=true)...');
            $this->call(DemoSeeder::class);
        }
    }
}
