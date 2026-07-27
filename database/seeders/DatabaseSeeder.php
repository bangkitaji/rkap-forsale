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
        $this->call([
            RoleAndUserSeeder::class,
            RkapSeeder::class,
            SatuanSeeder::class,
            CashflowGroupSeeder::class,
            DifferenceGroupSeeder::class,
            MasterDataSeeder::class,
            CoaCfTypeMappingSeeder::class,
            CoaProfitLossMappingSeeder::class,
            CashflowReportGroupMappingSeeder::class,
            CashFlowSeeder::class,
            CdsGroupSeeder::class,
        ]);
    }
}
