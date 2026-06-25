<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Satuan;

class SatuanSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $satuans = [
      [
        'name' => 'Pcs',
        'description' => 'Pieces - Satuan barang fisik kecil terhitung',
      ],
      [
        'name' => 'Unit',
        'description' => 'Unit - Satuan peralatan, perangkat keras, mesin, atau kendaraan',
      ],
      [
        'name' => 'Buah',
        'description' => 'Buah - Satuan umum untuk barang fisik',
      ],
      [
        'name' => 'Set',
        'description' => 'Set - Satuan barang berkelompok/paket komplit',
      ],
      [
        'name' => 'Lot',
        'description' => 'Lot - Kelompok barang/jasa pengadaan',
      ],
      [
        'name' => 'Paket',
        'description' => 'Paket - Satu kesatuan pekerjaan atau layanan jasa',
      ],
      [
        'name' => 'm',
        'description' => 'Meter - Satuan panjang untuk kabel, pipa, atau bahan baku',
      ],
      [
        'name' => 'm²',
        'description' => 'Meter Persegi - Satuan luas untuk pekerjaan pengecatan, lantai, atau dinding',
      ],
      [
        'name' => 'm³',
        'description' => 'Meter Kubik - Satuan volume untuk galian tanah, cor beton, atau air',
      ],
      [
        'name' => 'Kg',
        'description' => 'Kilogram - Satuan berat',
      ],
      [
        'name' => 'L',
        'description' => 'Liter - Satuan volume cairan atau cat',
      ],
      [
        'name' => 'Orang',
        'description' => 'Jumlah orang',
      ],
      [
        'name' => 'Hari',
        'description' => 'Jumlah hari kerja atau durasi waktu',
      ],
      [
        'name' => 'Bulan',
        'description' => 'Bulan - Satuan durasi waktu bulanan (gaji, sewa, dll)',
      ],
      [
        'name' => 'Tahun',
        'description' => 'Tahun - Satuan durasi waktu tahunan (lisensi, kontrak)',
      ],
      [
        'name' => 'Box',
        'description' => 'Box - Satuan kemasan kotak atau kardus',
      ],
      [
        'name' => 'Roll',
        'description' => 'Roll - Satuan gulungan kabel, kertas, atau plastik',
      ],
    ];

    foreach ($satuans as $satuan) {
      Satuan::firstOrCreate(
        ['name' => $satuan['name']],
        ['description' => $satuan['description']]
      );
    }
  }
}
