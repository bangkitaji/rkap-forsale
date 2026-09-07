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
        'name' => 'Penerimaan Kas dari Pendapatan Operasional Utama',
      ],
      [
        'code' => 'CF0A3',
        'name' => 'Penerimaan Kas dari Pendapatan Lain-lain',
      ],
      [
        'code' => 'CF0B2',
        'name' => 'Pembayaran ke Pemasok (Opex)',
      ],
      [
        'code' => 'CF0B3',
        'name' => 'Pembayaran ke Karyawan',
      ],
      [
        'code' => 'CF0B10',
        'name' => 'Pendapatan Bunga (Interest Income)',
      ],
      [
        'code' => 'CF0B5',
        'name' => 'Pembayaran Bunga (Interest Expense)',
      ],
      [
        'code' => 'CF0B2A',
        'name' => 'Pembayaran ke Pemasok (Non Opex / Capex)',
      ],
      [
        'code' => 'CF0F1',
        'name' => 'Pengeluaran Kas Hak Pengusahaan & Konsesi Proyek',
      ],
      [
        'code' => 'CF0E2',
        'name' => 'Penerimaan Pinjaman Pemegang Saham A',
      ],
      [
        'code' => 'CF0E3',
        'name' => 'Penerimaan Pinjaman Pemegang Saham B',
      ],
      [
        'code' => 'CF0E11',
        'name' => 'Penarikan Dana Dibatasi Penggunaannya Pendanaan',
      ],
      [
        'code' => 'CF0E1',
        'name' => 'Penerimaan Pinjaman Sindikasi Tahap I',
      ],
      [
        'code' => 'CF0E1A',
        'name' => 'Penerimaan Pinjaman Sindikasi Mitra Tahap I',
      ],
      [
        'code' => 'CF0E4',
        'name' => 'Penerimaan Pinjaman Sindikasi Tahap II',
      ],
      [
        'code' => 'CF0E4A',
        'name' => 'Penerimaan Pinjaman Sindikasi Mitra Tahap II',
      ],
      [
        'code' => 'CF0E4B',
        'name' => 'Penerimaan Pinjaman Sindikasi Tahap III',
      ],
      [
        'code' => 'CF0E4C',
        'name' => 'Penerimaan Pinjaman Sindikasi Mitra Tahap III',
      ],
      [
        'code' => 'CF0F8',
        'name' => 'Penarikan Dana Dibatasi Penggunaannya Investasi',
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
