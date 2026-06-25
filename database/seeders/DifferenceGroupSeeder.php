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
            ['code' => '1006', 'name' => 'Uang Muka Jangka Panjang'],
            ['code' => '1007', 'name' => 'Asset Tetap - Bersih'],
            ['code' => '1008', 'name' => 'Hak Pengusahaan Kereta Cepat'],
            ['code' => '1009', 'name' => 'Aset hak guna'],
            ['code' => '1010', 'name' => 'Aset Tidak Berwujud'],
            ['code' => '2001', 'name' => 'Pendapatan diterima dimuka'],
            ['code' => '2002', 'name' => 'Utang kontraktor'],
            ['code' => '2003', 'name' => 'Accounts Payable'],
            ['code' => '2004', 'name' => 'Utang Pajak'],
            ['code' => '2005', 'name' => 'Utang Lain-lain'],
            ['code' => '2006', 'name' => 'Utang retensi'],
            ['code' => '2007', 'name' => 'Hutang Jangka Panjang ke CDB'],
            ['code' => '2010', 'name' => 'Long term Debt CDS BY'],
            ['code' => '2013', 'name' => 'Akrual Bunga Cost Overrun PSBI'],
            ['code' => '2015', 'name' => 'Akrual Bunga Cost Overrun BY'],
            ['code' => '2017', 'name' => 'Akrual Bunga CDS PSBI'],
            ['code' => '7003', 'name' => 'Pendapatan Bunga'],
            ['code' => '2000A', 'name' => 'Biaya yang masih harus dibayar - Employee'],
            ['code' => '2000B', 'name' => 'Biaya yang masih harus dibayar - interest'],
            ['code' => '2000C', 'name' => 'Biaya yang masih harus dibayar - Operational'],
            ['code' => '2000D', 'name' => 'Biaya yang masih harus dibayar - Construction'],
        ];

        foreach ($groups as $group) {
            DifferenceGroup::firstOrCreate(
                ['code' => $group['code']],
                ['name' => $group['name']]
            );
        }
    }
}
