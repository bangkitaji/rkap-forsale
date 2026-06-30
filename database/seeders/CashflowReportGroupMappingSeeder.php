<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CashflowGroup;
use App\Models\ReportGroup;

class CashflowReportGroupMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mappings = [
            'CF0A1B' => 'CF0001',
            'CF0A3'  => 'CF0001',
            'CF0B10' => 'CF0001',
            'CF0B2'  => 'CF0001',
            'CF0B2A' => 'CF0001',
            'CF0B2B' => 'CF0001',
            'CF0B3'  => 'CF0001',
            'CF0B5'  => 'CF0001',
            'CF0B9'  => 'CF0001',
            'CF0F1'  => 'CF0002',
            'CF0F8'  => 'CF0002',
            'CF0E2'  => 'CF0003',
            'CF0E3'  => 'CF0003',
            'CF0E1'  => 'CF0003',
            'CF0E1A' => 'CF0003',
            'CF0E4'  => 'CF0003',
            'CF0E4A' => 'CF0003',
            'CF0E4B' => 'CF0003',
            'CF0E4C' => 'CF0003',
            'CF0E11' => 'CF0003',
            'CF0D1'  => 'CF0004',
        ];

        foreach ($mappings as $cfCode => $rgCode) {
            $reportGroup = ReportGroup::where('code', $rgCode)->first();
            if (!$reportGroup) {
                continue;
            }

            // Find or create cashflow group
            $cashflowGroup = CashflowGroup::firstOrCreate(
                ['code' => $cfCode],
                ['name' => $cfCode] // Use code as default name if creating
            );

            // Map it
            $cashflowGroup->update([
                'report_group_id' => $reportGroup->id,
            ]);
        }
    }
}
