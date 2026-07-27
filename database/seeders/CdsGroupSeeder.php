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
            ['code' => 'CDS001', 'name' => 'Cash received from farebox'],
            ['code' => 'CDS002', 'name' => 'Cash received from non farebox'],
            ['code' => 'CDS003', 'name' => 'Cash received interest income'],
            ['code' => 'CDS004', 'name' => 'Personel'],
            ['code' => 'CDS005', 'name' => 'Listrik'],
            ['code' => 'CDS006', 'name' => 'Maintenance'],
            ['code' => 'CDS007', 'name' => 'Overhead'],
            ['code' => 'CDS008', 'name' => 'Financing Cost'],
            ['code' => 'CDS009', 'name' => 'Capex'],
        ];

        foreach ($data as $item) {
            \App\Models\CdsGroup::updateOrCreate(
                ['code' => $item['code']],
                ['name' => $item['name']]
            );
        }
    }
}
