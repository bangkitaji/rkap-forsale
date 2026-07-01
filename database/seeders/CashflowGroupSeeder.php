<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CashflowGroup;

class CashflowGroupSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $cashflowGroups = [
      [
        'code' => 'CF0A1B',
        'name' => 'Penerimaan Pelanggan Farebox',
      ],
      [
        'code' => 'CF0A3',
        'name' => 'Penerimaan Pelanggan Non Farebox',
      ],
      [
        'code' => 'CF0B2',
        'name' => 'Pembayaran Pemasok',
      ],
      [
        'code' => 'CF0B3',
        'name' => 'Pembayaran ke Karyawan',
      ],
      [
        'code' => 'CF0B10',
        'name' => 'Interest Income',
      ],
      [
        'code' => 'CF0B5',
        'name' => 'Pembayaran bunga',
      ],
      [
        'code' => 'CF0B2A',
        'name' => 'Pembayaran ke Pemasok (non opex)',
      ],
      [
        'code' => 'CF0F1',
        'name' => 'HAK PENGUSAHAAN KERETA CEPAT CF',
      ],
      [
        'code' => 'CF0E2',
        'name' => 'Penerimaan Dana CO PSBI',
      ],
      [
        'code' => 'CF0E3',
        'name' => 'Penerimaan Dana CO BY',
      ],
      [
        'code' => 'CF0E11',
        'name' => 'Penarikan Dana Dibatasi Pengunannya Pendanaan',
      ],
      [
        'code' => 'CF0E1',
        'name' => 'Penerimaan Dana CDS PSBI 2024',
      ],
      [
        'code' => 'CF0E1A',
        'name' => 'Penerimaan Dana CDS BY 2024',
      ],
      [
        'code' => 'CF0E4',
        'name' => 'Penerimaan Dana CDS PSBI 2025',
      ],
      [
        'code' => 'CF0E4A',
        'name' => 'Penerimaan Dana CDS BY 2025',
      ],
      [
        'code' => 'CF0E4B',
        'name' => 'Penerimaan Dana CDS PSBI 2026',
      ],
      [
        'code' => 'CF0E4C',
        'name' => 'Penerimaan Dana CDS BY 2026',
      ],
      [
        'code' => 'CF0F8',
        'name' => 'Penarikan Dana Dibatasi Pengunannya Investasi',
      ],
    ];

    $codes = collect($cashflowGroups)->pluck('code')->toArray();
    CashflowGroup::whereNotIn('code', $codes)->forceDelete();

    foreach ($cashflowGroups as $group) {
      CashflowGroup::updateOrCreate(
        ['code' => $group['code']],
        ['name' => $group['name']]
      );
    }
  }
}
