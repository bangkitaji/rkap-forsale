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
        'code' => 'CF0B10',
        'name' => 'Interest Income',
      ],
      [
        'code' => 'CF0B2',
        'name' => 'Pembayaran Pemasok',
      ],
      [
        'code' => 'CF0B2A',
        'name' => 'Pembayaran ke Pemasok (non opex)',
      ],
      [
        'code' => 'CF0B3',
        'name' => 'Pembayaran ke Karyawan',
      ],
      [
        'code' => 'CF0B5',
        'name' => 'Pembayaran bunga',
      ],
      [
        'code' => 'CF0F1',
        'name' => 'Hak Pengusahaan Kereta Cepat CF',
      ],
    ];

    foreach ($cashflowGroups as $group) {
      CashflowGroup::firstOrCreate(
        ['code' => $group['code']],
        ['name' => $group['name']]
      );
    }
  }
}
