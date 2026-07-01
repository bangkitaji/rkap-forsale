<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\CashflowGroup;
use App\Models\ReportGroup;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $cashflowGroups = [
            ['code' => 'CF0A1B', 'name' => 'Penerimaan Pelanggan Farebox'],
            ['code' => 'CF0A3', 'name' => 'Penerimaan Pelanggan Non Farebox'],
            ['code' => 'CF0B2', 'name' => 'Pembayaran Pemasok'],
            ['code' => 'CF0B3', 'name' => 'Pembayaran ke Karyawan'],
            ['code' => 'CF0B10', 'name' => 'Interest Income'],
            ['code' => 'CF0B5', 'name' => 'Pembayaran bunga'],
            ['code' => 'CF0B2A', 'name' => 'Pembayaran ke Pemasok (non opex)'],
            ['code' => 'CF0F1', 'name' => 'HAK PENGUSAHAAN KERETA CEPAT CF'],
            ['code' => 'CF0E2', 'name' => 'Penerimaan Dana CO PSBI'],
            ['code' => 'CF0E3', 'name' => 'Penerimaan Dana CO BY'],
            ['code' => 'CF0E11', 'name' => 'Penarikan Dana Dibatasi Pengunannya Pendanaan'],
            ['code' => 'CF0E1', 'name' => 'Penerimaan Dana CDS PSBI 2024'],
            ['code' => 'CF0E1A', 'name' => 'Penerimaan Dana CDS BY 2024'],
            ['code' => 'CF0E4', 'name' => 'Penerimaan Dana CDS PSBI 2025'],
            ['code' => 'CF0E4A', 'name' => 'Penerimaan Dana CDS BY 2025'],
            ['code' => 'CF0E4B', 'name' => 'Penerimaan Dana CDS PSBI 2026'],
            ['code' => 'CF0E4C', 'name' => 'Penerimaan Dana CDS BY 2026'],
            ['code' => 'CF0F8', 'name' => 'Penarikan Dana Dibatasi Pengunannya Investasi'],
        ];

        $mappings = [
            'CF0A1B' => 'CF0001',
            'CF0A3'  => 'CF0001',
            'CF0B2'  => 'CF0001',
            'CF0B3'  => 'CF0001',
            'CF0B10' => 'CF0001',
            'CF0B5'  => 'CF0001',
            'CF0B2A' => 'CF0001',
            'CF0F1'  => 'CF0002',
            'CF0E2'  => 'CF0003',
            'CF0E3'  => 'CF0003',
            'CF0E11' => 'CF0003',
            'CF0E1'  => 'CF0003',
            'CF0E1A' => 'CF0003',
            'CF0E4'  => 'CF0003',
            'CF0E4A' => 'CF0003',
            'CF0E4B' => 'CF0003',
            'CF0E4C' => 'CF0003',
            'CF0F8'  => 'CF0002',
        ];

        // 1. Delete cashflow groups that are not in the new list
        $newCodes = collect($cashflowGroups)->pluck('code')->toArray();
        CashflowGroup::whereNotIn('code', $newCodes)->forceDelete();

        // 2. Insert or update the new cashflow groups
        foreach ($cashflowGroups as $group) {
            $rgCode = $mappings[$group['code']] ?? null;
            $reportGroupId = null;
            if ($rgCode) {
                $reportGroup = ReportGroup::where('code', $rgCode)->first();
                if ($reportGroup) {
                    $reportGroupId = $reportGroup->id;
                }
            }

            CashflowGroup::updateOrCreate(
                ['code' => $group['code']],
                [
                    'name' => $group['name'],
                    'report_group_id' => $reportGroupId,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data correction migrations generally do not need a reverse mechanism,
        // or we could restore old ones if needed.
    }
};
