<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DifferenceGroup;

class DifferenceGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            ['code' => '2001', 'name' => 'Pendapatan diterima dimuka'],
            ['code' => '2000C', 'name' => 'Biaya yang masih harus dibayar - Operational'],
            ['code' => '2000A', 'name' => 'Biaya yang masih harus dibayar - Employee'],
            ['code' => '1008', 'name' => 'Hak Pengusahaan & Konsesi Proyek'],
            ['code' => '1007', 'name' => 'Asset Tetap - Bersih'],
            ['code' => '1009', 'name' => 'Aset hak guna'],
            ['code' => '1010', 'name' => 'Aset Tidak Berwujud'],
            ['code' => '1006', 'name' => 'Uang Muka Jangka Panjang'],
            ['code' => '7003', 'name' => 'Pendapatan Bunga'],
            ['code' => '2000B', 'name' => 'Biaya yang masih harus dibayar - interest'],
            ['code' => '2013', 'name' => 'Akrual Bunga Pinjaman Sindikasi A'],
            ['code' => '2015', 'name' => 'Akrual Bunga Pinjaman Sindikasi B'],
            ['code' => '2017', 'name' => 'Akrual Bunga Pinjaman Investasi A'],
            ['code' => '2010', 'name' => 'Pinjaman Jangka Panjang Mitra'],
            ['code' => '2007', 'name' => 'Pinjaman Jangka Panjang Bank Sindikasi'],
            ['code' => '2005', 'name' => 'Utang Lain-lain'],
            ['code' => '2004', 'name' => 'Utang Pajak'],
            ['code' => '2002', 'name' => 'Utang kontraktor'],
            ['code' => '2000D', 'name' => 'Biaya yang masih harus dibayar - Construction'],
            ['code' => '2006', 'name' => 'Utang retensi'],
            ['code' => '2003', 'name' => 'Accounts Payable'],
            ['code' => '2009', 'name' => 'Utang Pembiayaan Tambahan A'],
            ['code' => '2011', 'name' => 'Utang Pembiayaan Tambahan B'],
            ['code' => '1001C', 'name' => 'Dana Dibatasi Penggunaannya Escrow'],
            ['code' => '2008', 'name' => 'Utang Pembiayaan Investasi A'],
        ];

        $codes = collect($groups)->pluck('code')->toArray();
        DifferenceGroup::whereNotIn('code', $codes)->forceDelete();

        foreach ($groups as $group) {
            DifferenceGroup::updateOrCreate(
                ['code' => $group['code']],
                ['name' => $group['name']]
            );
        }
    }
}
