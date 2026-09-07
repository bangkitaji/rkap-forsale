<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CashFlowSeeder extends Seeder
{
    public function run(): void
    {
        // Truncate existing tables in correct order or disable FK checks
        Schema::disableForeignKeyConstraints();
        DB::table('cash_flow_facts')->truncate();
        DB::table('cf_line_items')->truncate();
        DB::table('financial_versions')->truncate();
        DB::table('cf_categories')->truncate();
        Schema::enableForeignKeyConstraints();

        // 1. Seed Categories
        $categories = [
            ['category_id' => 1, 'name' => 'Arus Kas Aktivitas Operasi', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'Arus Kas Aktivitas Investasi', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 3, 'name' => 'Arus Kas Aktivitas Pendanaan', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'Rekonsiliasi Kas', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('cf_categories')->insert($categories);

        // 2. Seed Line Items (Dimension)
        $lineItems = [
            // Operasi (Category 1)
            ['item_code' => 'CF0A1B', 'category_id' => 1, 'description' => 'Penerimaan Kas dari Pendapatan Operasional Utama', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0A3',  'category_id' => 1, 'description' => 'Penerimaan Kas dari Pendapatan Lain-lain', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0B10', 'category_id' => 1, 'description' => 'Penerimaan Bunga (Interest Income)', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0B2',  'category_id' => 1, 'description' => 'Pembayaran ke Pemasok (Opex)', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0B2A', 'category_id' => 1, 'description' => 'Pembayaran ke Pemasok (Capex)', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0B2B', 'category_id' => 1, 'description' => 'Pembayaran ke Maintenance Reserve Account (MRA)', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0B3',  'category_id' => 1, 'description' => 'Pembayaran ke Karyawan', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0B5',  'category_id' => 1, 'description' => 'Pembayaran Bunga (Interest Expense)', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0B9',  'category_id' => 1, 'description' => 'Penarikan Dana Dibatasi Penggunaannya Operasi', 'created_at' => now(), 'updated_at' => now()],

            // Investasi (Category 2)
            ['item_code' => 'CF0F1',  'category_id' => 2, 'description' => 'Perolehan Hak Pengusahaan & Konsesi Proyek', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0F8',  'category_id' => 2, 'description' => 'Penarikan Dana Dibatasi Penggunaannya Investasi', 'created_at' => now(), 'updated_at' => now()],

            // Pendanaan (Category 3)
            ['item_code' => 'CF0E2',  'category_id' => 3, 'description' => 'Penerimaan Pinjaman Pemegang Saham A', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0E3',  'category_id' => 3, 'description' => 'Penerimaan Pinjaman Pemegang Saham B', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0E1',  'category_id' => 3, 'description' => 'Penerimaan Pinjaman Sindikasi Tahap I', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0E1A', 'category_id' => 3, 'description' => 'Penerimaan Pinjaman Sindikasi Mitra Tahap I', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0E4',  'category_id' => 3, 'description' => 'Penerimaan Pinjaman Sindikasi Tahap II', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0E4A', 'category_id' => 3, 'description' => 'Penerimaan Pinjaman Sindikasi Mitra Tahap II', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0E4B', 'category_id' => 3, 'description' => 'Penerimaan Pinjaman Sindikasi Tahap III', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0E4C', 'category_id' => 3, 'description' => 'Penerimaan Pinjaman Sindikasi Mitra Tahap III', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF0E11', 'category_id' => 3, 'description' => 'Penarikan Dana Dibatasi Penggunaannya Pendanaan', 'created_at' => now(), 'updated_at' => now()],

            // Rekonsiliasi (Category 4)
            ['item_code' => 'CF0D1',        'category_id' => 4, 'description' => 'Selisih Kurs', 'created_at' => now(), 'updated_at' => now()],
            ['item_code' => 'CF_BEGINNING', 'category_id' => 4, 'description' => 'Saldo Awal', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('cf_line_items')->insert($lineItems);

        // 3. Seed Financial Versions
        $versions = [
            ['version_id' => 1, 'name' => 'CBP', 'year' => 2025, 'created_at' => now(), 'updated_at' => now()],
            ['version_id' => 2, 'name' => 'Audited', 'year' => 2025, 'created_at' => now(), 'updated_at' => now()],
            ['version_id' => 3, 'name' => 'CBP', 'year' => 2026, 'created_at' => now(), 'updated_at' => now()],
            ['version_id' => 4, 'name' => 'Actual', 'year' => 2026, 'created_at' => now(), 'updated_at' => now()],
            ['version_id' => 5, 'name' => 'PROGNOSA', 'year' => 2026, 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('financial_versions')->insert($versions);

        // 4. Seed Cash Flow Facts
        $facts = [
            // Operasi
            // CF0A1B (Penerimaan Kas dari Pendapatan Operasional Utama)
            ['item_code' => 'CF0A1B', 'version_id' => 1, 'amount' => 1976683208500],
            ['item_code' => 'CF0A1B', 'version_id' => 2, 'amount' => 1828274026752],
            ['item_code' => 'CF0A1B', 'version_id' => 3, 'amount' => 2324866825000],
            ['item_code' => 'CF0A1B', 'version_id' => 4, 'amount' => 711056831968],
            ['item_code' => 'CF0A1B', 'version_id' => 5, 'amount' => 1968399300691],

            // CF0A3 (Penerimaan Kas dari Pendapatan Lain-lain)
            ['item_code' => 'CF0A3', 'version_id' => 1, 'amount' => 55423535119],
            ['item_code' => 'CF0A3', 'version_id' => 2, 'amount' => 81655291616],
            ['item_code' => 'CF0A3', 'version_id' => 3, 'amount' => 90896332934],
            ['item_code' => 'CF0A3', 'version_id' => 4, 'amount' => 35636785361],
            ['item_code' => 'CF0A3', 'version_id' => 5, 'amount' => 121287556510],

            // CF0B10 (Penerimaan Bunga)
            ['item_code' => 'CF0B10', 'version_id' => 1, 'amount' => 32254800205],
            ['item_code' => 'CF0B10', 'version_id' => 2, 'amount' => 81551996683],
            ['item_code' => 'CF0B10', 'version_id' => 3, 'amount' => 42426348432],
            ['item_code' => 'CF0B10', 'version_id' => 4, 'amount' => 1481964032],
            ['item_code' => 'CF0B10', 'version_id' => 5, 'amount' => 26230667284],

            // CF0B2 (Pembayaran ke Pemasok)
            ['item_code' => 'CF0B2', 'version_id' => 1, 'amount' => -3427111564967],
            ['item_code' => 'CF0B2', 'version_id' => 2, 'amount' => -492141168139],
            ['item_code' => 'CF0B2', 'version_id' => 3, 'amount' => -3519388733605],
            ['item_code' => 'CF0B2', 'version_id' => 4, 'amount' => -244180508638],
            ['item_code' => 'CF0B2', 'version_id' => 5, 'amount' => -3229559463802],

            // CF0B2A (Pembayaran ke Pemasok (capex))
            ['item_code' => 'CF0B2A', 'version_id' => 1, 'amount' => 0],
            ['item_code' => 'CF0B2A', 'version_id' => 2, 'amount' => 0],
            ['item_code' => 'CF0B2A', 'version_id' => 3, 'amount' => -1071811438571],
            ['item_code' => 'CF0B2A', 'version_id' => 4, 'amount' => 0],
            ['item_code' => 'CF0B2A', 'version_id' => 5, 'amount' => -1071811438571],

            // CF0B2B (Pembayaran ke MRA)
            ['item_code' => 'CF0B2B', 'version_id' => 1, 'amount' => 0],
            ['item_code' => 'CF0B2B', 'version_id' => 2, 'amount' => 0],
            ['item_code' => 'CF0B2B', 'version_id' => 3, 'amount' => -135342030000],
            ['item_code' => 'CF0B2B', 'version_id' => 4, 'amount' => 0],
            ['item_code' => 'CF0B2B', 'version_id' => 5, 'amount' => -147858626043],

            // CF0B3 (Pembayaran ke Karyawan)
            ['item_code' => 'CF0B3', 'version_id' => 1, 'amount' => -384577324354],
            ['item_code' => 'CF0B3', 'version_id' => 2, 'amount' => -311022858348],
            ['item_code' => 'CF0B3', 'version_id' => 3, 'amount' => -442972172544],
            ['item_code' => 'CF0B3', 'version_id' => 4, 'amount' => -125912879484],
            ['item_code' => 'CF0B3', 'version_id' => 5, 'amount' => -387081220559],

            // CF0B5 (Pembayaran Bunga)
            ['item_code' => 'CF0B5', 'version_id' => 1, 'amount' => -1927356335317],
            ['item_code' => 'CF0B5', 'version_id' => 2, 'amount' => -1919674403333],
            ['item_code' => 'CF0B5', 'version_id' => 3, 'amount' => -2042461456007],
            ['item_code' => 'CF0B5', 'version_id' => 4, 'amount' => -1016244953513],
            ['item_code' => 'CF0B5', 'version_id' => 5, 'amount' => -2104214031326],

            // CF0B9 (Penarikan Dana Dibatasi Pengunannya Operasi)
            ['item_code' => 'CF0B9', 'version_id' => 1, 'amount' => -235765719405],
            ['item_code' => 'CF0B9', 'version_id' => 2, 'amount' => 0],
            ['item_code' => 'CF0B9', 'version_id' => 3, 'amount' => -301101954586],
            ['item_code' => 'CF0B9', 'version_id' => 4, 'amount' => -581405159016],
            ['item_code' => 'CF0B9', 'version_id' => 5, 'amount' => -305686825195],


            // Investasi
            // CF0F1 (Perolehan Hak Pengusahaan & Konsesi Proyek)
            ['item_code' => 'CF0F1', 'version_id' => 1, 'amount' => -11779274848625],
            ['item_code' => 'CF0F1', 'version_id' => 2, 'amount' => -4775453440856],
            ['item_code' => 'CF0F1', 'version_id' => 3, 'amount' => -5718751590837],
            ['item_code' => 'CF0F1', 'version_id' => 4, 'amount' => -9169685354],
            ['item_code' => 'CF0F1', 'version_id' => 5, 'amount' => -5750146817790],

            // CF0F8 (Penarikan Dana Dibatasi Pengunannya Investasi)
            ['item_code' => 'CF0F8', 'version_id' => 1, 'amount' => 11779274848625],
            ['item_code' => 'CF0F8', 'version_id' => 2, 'amount' => 2513632292703],
            ['item_code' => 'CF0F8', 'version_id' => 3, 'amount' => 5718751590837],
            ['item_code' => 'CF0F8', 'version_id' => 4, 'amount' => 540068815790],
            ['item_code' => 'CF0F8', 'version_id' => 5, 'amount' => 5750146817790],


            // Pendanaan
            // CF0E2 (Penerimaan Pinjaman Pemegang Saham A)
            ['item_code' => 'CF0E2', 'version_id' => 1, 'amount' => 1542137001600],
            ['item_code' => 'CF0E2', 'version_id' => 2, 'amount' => 1543177707552],
            ['item_code' => 'CF0E2', 'version_id' => 3, 'amount' => 0],
            ['item_code' => 'CF0E2', 'version_id' => 4, 'amount' => 0],
            ['item_code' => 'CF0E2', 'version_id' => 5, 'amount' => 0],

            // CF0E3 (Penerimaan Pinjaman Pemegang Saham B)
            ['item_code' => 'CF0E3', 'version_id' => 1, 'amount' => 0],
            ['item_code' => 'CF0E3', 'version_id' => 2, 'amount' => 0],
            ['item_code' => 'CF0E3', 'version_id' => 3, 'amount' => 0],
            ['item_code' => 'CF0E3', 'version_id' => 4, 'amount' => 0],
            ['item_code' => 'CF0E3', 'version_id' => 5, 'amount' => 0],

            // CF0E1 (Penerimaan Pinjaman Sindikasi Tahap I)
            ['item_code' => 'CF0E1', 'version_id' => 1, 'amount' => 939704477282],
            ['item_code' => 'CF0E1', 'version_id' => 2, 'amount' => 254855216564],
            ['item_code' => 'CF0E1', 'version_id' => 3, 'amount' => 684244044320],
            ['item_code' => 'CF0E1', 'version_id' => 4, 'amount' => 0],
            ['item_code' => 'CF0E1', 'version_id' => 5, 'amount' => 684245069897],

            // CF0E1A (Penerimaan Pinjaman Sindikasi Mitra Tahap I)
            ['item_code' => 'CF0E1A', 'version_id' => 1, 'amount' => 935092429365],
            ['item_code' => 'CF0E1A', 'version_id' => 2, 'amount' => 855205646490],
            ['item_code' => 'CF0E1A', 'version_id' => 3, 'amount' => 117572846078],
            ['item_code' => 'CF0E1A', 'version_id' => 4, 'amount' => 105010891915],
            ['item_code' => 'CF0E1A', 'version_id' => 5, 'amount' => 119213999753],

            // CF0E4 (Penerimaan Pinjaman Sindikasi Tahap II)
            ['item_code' => 'CF0E4', 'version_id' => 1, 'amount' => 1246669559323],
            ['item_code' => 'CF0E4', 'version_id' => 2, 'amount' => 526727707455],
            ['item_code' => 'CF0E4', 'version_id' => 3, 'amount' => 719941851868],
            ['item_code' => 'CF0E4', 'version_id' => 4, 'amount' => 157517362442],
            ['item_code' => 'CF0E4', 'version_id' => 5, 'amount' => 719942875793],

            // CF0E4A (Penerimaan Pinjaman Sindikasi Mitra Tahap II)
            ['item_code' => 'CF0E4A', 'version_id' => 1, 'amount' => 831113039549],
            ['item_code' => 'CF0E4A', 'version_id' => 2, 'amount' => 0],
            ['item_code' => 'CF0E4A', 'version_id' => 3, 'amount' => 831113039549],
            ['item_code' => 'CF0E4A', 'version_id' => 4, 'amount' => 0],
            ['item_code' => 'CF0E4A', 'version_id' => 5, 'amount' => 831113039549],

            // CF0E4B (Penerimaan Pinjaman Sindikasi Tahap III)
            ['item_code' => 'CF0E4B', 'version_id' => 1, 'amount' => 0],
            ['item_code' => 'CF0E4B', 'version_id' => 2, 'amount' => 0],
            ['item_code' => 'CF0E4B', 'version_id' => 3, 'amount' => 1584726546739],
            ['item_code' => 'CF0E4B', 'version_id' => 4, 'amount' => 317487347418],
            ['item_code' => 'CF0E4B', 'version_id' => 5, 'amount' => 1628668448302],

            // CF0E4C (Penerimaan Pinjaman Sindikasi Mitra Tahap III)
            ['item_code' => 'CF0E4C', 'version_id' => 1, 'amount' => 0],
            ['item_code' => 'CF0E4C', 'version_id' => 2, 'amount' => 0],
            ['item_code' => 'CF0E4C', 'version_id' => 3, 'amount' => 1056484364492],
            ['item_code' => 'CF0E4C', 'version_id' => 4, 'amount' => 0],
            ['item_code' => 'CF0E4C', 'version_id' => 5, 'amount' => 1085779648581],

            // CF0E11 (Penarikan Dana Dibatasi Pengunannya Pendanaan)
            ['item_code' => 'CF0E11', 'version_id' => 1, 'amount' => -1542137001600],
            ['item_code' => 'CF0E11', 'version_id' => 2, 'amount' => 0],
            ['item_code' => 'CF0E11', 'version_id' => 3, 'amount' => 0],
            ['item_code' => 'CF0E11', 'version_id' => 4, 'amount' => 0],
            ['item_code' => 'CF0E11', 'version_id' => 5, 'amount' => 0],


            // Rekonsiliasi
            // CF0D1 (Selisih Kurs)
            ['item_code' => 'CF0D1', 'version_id' => 1, 'amount' => 0],
            ['item_code' => 'CF0D1', 'version_id' => 2, 'amount' => -3170738402],
            ['item_code' => 'CF0D1', 'version_id' => 3, 'amount' => 0],
            ['item_code' => 'CF0D1', 'version_id' => 4, 'amount' => 14728521074],
            ['item_code' => 'CF0D1', 'version_id' => 5, 'amount' => 525413236],

            // CF_BEGINNING (Saldo Awal)
            ['item_code' => 'CF_BEGINNING', 'version_id' => 1, 'amount' => 35981262417],
            ['item_code' => 'CF_BEGINNING', 'version_id' => 2, 'amount' => 35981262417],
            ['item_code' => 'CF_BEGINNING', 'version_id' => 3, 'amount' => 195621838623],
            ['item_code' => 'CF_BEGINNING', 'version_id' => 4, 'amount' => 219598539154],
            ['item_code' => 'CF_BEGINNING', 'version_id' => 5, 'amount' => 195621838623],
        ];

        // Add timestamps to facts easily
        $facts = array_map(function ($fact) {
            $fact['created_at'] = now();
            $fact['updated_at'] = now();
            return $fact;
        }, $facts);

        DB::table('cash_flow_facts')->insert($facts);
    }
}
