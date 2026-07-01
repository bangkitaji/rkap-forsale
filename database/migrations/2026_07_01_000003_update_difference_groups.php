<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\DifferenceGroup;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $groups = [
            ['code' => '2001', 'name' => 'Pendapatan diterima dimuka'],
            ['code' => '2000C', 'name' => 'Biaya yang masih harus dibayar - Operational'],
            ['code' => '2000A', 'name' => 'Biaya yang masih harus dibayar - Employee'],
            ['code' => '1008', 'name' => 'Hak Pengusahaan Kereta Cepat'],
            ['code' => '1007', 'name' => 'Asset Tetap - Bersih'],
            ['code' => '1009', 'name' => 'Aset hak guna'],
            ['code' => '1010', 'name' => 'Aset Tidak Berwujud'],
            ['code' => '1006', 'name' => 'Uang Muka Jangka Panjang'],
            ['code' => '7003', 'name' => 'Pendapatan Bunga'],
            ['code' => '2000B', 'name' => 'Biaya yang masih harus dibayar - interest'],
            ['code' => '2013', 'name' => 'Akrual Bunga Cost Overrun PSBI'],
            ['code' => '2015', 'name' => 'Akrual Bunga Cost Overrun BY'],
            ['code' => '2017', 'name' => 'Akrual Bunga CDS PSBI'],
            ['code' => '2010', 'name' => 'Long term Debt CDS BY'],
            ['code' => '2007', 'name' => 'Hutang Jangka Panjang ke CDB'],
            ['code' => '2005', 'name' => 'Utang Lain-lain'],
            ['code' => '2004', 'name' => 'Utang Pajak'],
            ['code' => '2002', 'name' => 'Utang kontraktor'],
            ['code' => '2000D', 'name' => 'Biaya yang masih harus dibayar - Construction'],
            ['code' => '2006', 'name' => 'Utang retensi'],
            ['code' => '2003', 'name' => 'Accounts Payable'],
            ['code' => '2009', 'name' => 'Hutang COR PSBI'],
            ['code' => '2011', 'name' => 'Hutang COR BY'],
            ['code' => '1001C', 'name' => 'Dana Dibatasi Penggunaannya CO'],
            ['code' => '2008', 'name' => 'Hutang CDS PSBI'],
        ];

        // 1. Force delete obsolete difference groups
        $codes = collect($groups)->pluck('code')->toArray();
        DifferenceGroup::whereNotIn('code', $codes)->forceDelete();

        // 2. Insert or update the new list of groups
        foreach ($groups as $group) {
            DifferenceGroup::updateOrCreate(
                ['code' => $group['code']],
                ['name' => $group['name']]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
