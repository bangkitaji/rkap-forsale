<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Sheet 3: "Petunjuk" — instructions for filling the template.
 */
class RkapSubmissionTemplateInstructionSheet implements FromArray, WithTitle, WithEvents, ShouldAutoSize
{
    public function title(): string
    {
        return 'Petunjuk';
    }

    public function array(): array
    {
        return [
            ['PETUNJUK PENGISIAN TEMPLATE UPLOAD MASSAL RKAP'],
            [''],
            ['CARA PENGGUNAAN:'],
            ['1. Buka sheet "Data Pengajuan" untuk mengisi data pengajuan RKAP.'],
            ['2. Buka sheet "Referensi" untuk melihat daftar kode Program Kerja, Kegiatan, dan COA yang tersedia.'],
            ['3. Salin (copy-paste) kode dari sheet Referensi ke sheet Data Pengajuan.'],
            ['4. Isi semua kolom yang bertanda (wajib) pada baris petunjuk (baris ke-2).'],
            ['5. Hapus baris contoh (baris ke-3 berwarna biru) sebelum mengupload.'],
            ['6. Simpan file dan upload melalui halaman Upload Massal RKAP.'],
            [''],
            ['ATURAN PENGISIAN:'],
            [''],
            ['Kolom Utama:'],
            ['- work_plan_code    : Kode Program Kerja (wajib). Harus ada di master data dan berstatus "approved".'],
            ['- work_plan_name    : Nama Program Kerja (diabaikan saat import, hanya untuk referensi Anda).'],
            ['- activity_code     : Kode Kegiatan (wajib). Harus ada di master data, berstatus "approved", dan merupakan anak dari work_plan_code.'],
            ['- activity_name     : Nama Kegiatan (diabaikan saat import, hanya untuk referensi Anda).'],
            ['- coa_code          : Kode COA/Akun (wajib). Harus ada di master data.'],
            ['- coa_name          : Nama COA (diabaikan saat import, hanya untuk referensi Anda).'],
            ['- unit              : Satuan utama (opsional). Contoh: Pkt, Org, Unit, Set.'],
            ['- quantity          : Volume utama (wajib, minimal lebih besar dari 0, bisa desimal).'],
            ['- unit_2            : Satuan kedua (opsional). Contoh: Bulan, Hari. Kosongkan jika tidak ada volume kedua.'],
            ['- quantity_2        : Volume kedua (opsional, bisa desimal). Hanya diisi jika unit_2 diisi.'],
            ['- unit_price        : Harga satuan dalam Rupiah (wajib, minimal 0). Gunakan angka tanpa titik/koma pemisah ribuan.'],
            ['- remarks           : Keterangan tambahan (opsional).'],
            [''],
            ['Kolom Distribusi Bulanan (m1 – m12):'],
            ['- m1 = Januari, m2 = Februari, ..., m12 = Desember.'],
            ['- Wajib diisi. Total m1+m2+...+m12 HARUS SAMA DENGAN total item (quantity × quantity_2 × unit_price).'],
            ['- Jika quantity_2 kosong, total = quantity × unit_price.'],
            ['- Gunakan angka bulat tanpa desimal.'],
            [''],
            ['Kolom Rencana Kas Keluar (co1 – co12):'],
            ['- co1 = Januari, co2 = Februari, ..., co12 = Desember.'],
            ['- Wajib diisi. Total co1+co2+...+co12 TIDAK BOLEH MELEBIHI total item.'],
            ['- Total kas keluar harus lebih besar dari 0.'],
            [''],
            ['PENGELOMPOKAN DATA:'],
            ['- Beberapa baris dengan work_plan_code + activity_code yang SAMA akan otomatis dikelompokkan menjadi satu kegiatan.'],
            ['- Setiap baris mewakili satu budget item (satu COA) dalam kegiatan tersebut.'],
            ['- Satu kegiatan bisa memiliki banyak COA/budget item.'],
            [''],
            ['CONTOH:'],
            ['Jika Program Kerja "WP001" memiliki Kegiatan "ACT001" dengan 2 COA:'],
            ['  Baris 1: WP001 | ... | ACT001 | ... | 511111 | ... | 1 | 500000 | 500000 | 0 | ... | 500000 | 0 | ...'],
            ['  Baris 2: WP001 | ... | ACT001 | ... | 521111 | ... | 1 | 300000 | 300000 | 0 | ... | 300000 | 0 | ...'],
            ['Kedua baris akan dikelompokkan menjadi 1 kegiatan dengan 2 budget item.'],
            [''],
            ['CATATAN PENTING:'],
            ['- Jangan mengubah baris header (baris 1).'],
            ['- Baris kosong akan diabaikan.'],
            ['- File harus berformat .xlsx atau .xls.'],
            ['- Ukuran file maksimal 2 MB.'],
            ['- Data yang diupload akan disimpan sebagai DRAFT. Anda dapat mengedit dan submit untuk review melalui halaman form pengajuan.'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Title
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'color' => ['argb' => 'FF2F5496'],
                    ],
                ]);

                // Section headers
                $sectionRows = [3, 11, 13, 27, 31, 35, 39, 47];
                foreach ($sectionRows as $r) {
                    $sheet->getStyle("A{$r}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 12,
                            'color' => ['argb' => 'FF333333'],
                        ],
                    ]);
                }

                $sheet->getColumnDimension('A')->setWidth(120);
            },
        ];
    }
}
