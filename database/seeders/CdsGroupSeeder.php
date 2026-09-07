<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CdsGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['code' => 'CDS001', 'name' => 'Penerimaan Kas dari Pendapatan Utama'],
            ['code' => 'CDS002', 'name' => 'Penerimaan Kas dari Pendapatan Lain-lain'],
            ['code' => 'CDS003', 'name' => 'Penerimaan Bunga (Interest Income)'],
            ['code' => 'CDS004', 'name' => 'Beban Pegawai / Personel'],
            ['code' => 'CDS005', 'name' => 'Beban Listrik & Utilitas'],
            ['code' => 'CDS006', 'name' => 'Beban Pemeliharaan & Sarana'],
            ['code' => 'CDS007', 'name' => 'Beban Umum & Operasional (Overhead)'],
            ['code' => 'CDS008', 'name' => 'Biaya Keuangan & Bunga (Financing Cost)'],
            ['code' => 'CDS009', 'name' => 'Pengeluaran Modal (Capex)'],
        ];

        foreach ($data as $item) {
            \App\Models\CdsGroup::updateOrCreate(
                ['code' => $item['code']],
                ['name' => $item['name']]
            );
        }
    }
}
