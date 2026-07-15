<?php

namespace App\Exports;

use App\Models\Activity;
use App\Models\Coa;
use App\Models\WorkPlan;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Sheet 1: "Data Pengajuan" — the main data entry sheet.
 *
 * Columns: work_plan_code, work_plan_name, activity_code, activity_name,
 *          coa_code, coa_name, unit, quantity, unit_2, quantity_2,
 *          unit_price, remarks, m1–m12, co1–co12
 */
class RkapSubmissionTemplateDataSheet implements FromArray, WithTitle, WithEvents, ShouldAutoSize
{
    protected ?int $periodId;
    protected ?int $bureauId;

    public function __construct(?int $periodId = null, ?int $bureauId = null)
    {
        $this->periodId = $periodId;
        $this->bureauId = $bureauId;
    }

    public function title(): string
    {
        return 'Data Pengajuan';
    }

    public function array(): array
    {
        $row1 = ['bureau_id', $this->bureauId];
        $row2 = ['rkap_period_id', $this->periodId];
        $row3 = [];

        for ($i = 2; $i < 36; $i++) {
            $row1[] = '';
            $row2[] = '';
            $row3[] = '';
        }

        $headers = [
            'work_plan_code',
            'work_plan_name',
            'activity_code',
            'activity_name',
            'coa_code',
            'coa_name',
            'unit',
            'quantity',
            'unit_2',
            'quantity_2',
            'unit_price',
            'remarks',
        ];

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        foreach ($months as $i => $m) {
            $headers[] = 'm' . ($i + 1);
        }
        foreach ($months as $i => $m) {
            $headers[] = 'co' . ($i + 1);
        }

        $hints = [
            '(wajib, kode program kerja)',
            '(otomatis, referensi)',
            '(wajib, kode kegiatan)',
            '(otomatis, referensi)',
            '(wajib, kode COA)',
            '(otomatis, referensi)',
            '(opsional, satuan utama)',
            '(wajib, volume ≥ 1)',
            '(opsional, satuan kedua)',
            '(opsional, volume kedua)',
            '(wajib, harga satuan Rp)',
            '(opsional, keterangan)',
        ];

        foreach ($months as $m) {
            $hints[] = "(wajib, distribusi $m)";
        }
        foreach ($months as $m) {
            $hints[] = "(wajib, kas keluar $m)";
        }

        // Sample row using first available data
        $sampleWp = WorkPlan::where('approval_status', 'approved')->orderBy('code')->first();
        $sampleActivity = $sampleWp
            ? Activity::where('work_plan_id', $sampleWp->id)->where('approval_status', 'approved')->orderBy('code')->first()
            : null;
        $sampleCoa = $sampleActivity
            ? $sampleActivity->coas()->orderBy('code')->first()
            : Coa::orderBy('code')->first();

        $sampleRow = [
            $sampleWp->code ?? 'WP001',
            $sampleWp->title ?? 'Nama Program Kerja',
            $sampleActivity->code ?? 'ACT001',
            $sampleActivity->title ?? 'Nama Kegiatan',
            $sampleCoa->code ?? '511111',
            $sampleCoa->title ?? 'Nama COA',
            'Pkt',
            1,
            '',
            '',
            1000000,
            'Contoh keterangan',
        ];

        // Monthly distribution: put all in month 1 for sample
        $sampleRow[] = 1000000; // m1
        for ($i = 2; $i <= 12; $i++) {
            $sampleRow[] = 0;
        }
        // Cash out: same as monthly for sample
        $sampleRow[] = 1000000; // co1
        for ($i = 2; $i <= 12; $i++) {
            $sampleRow[] = 0;
        }

        return [$row1, $row2, $row3, $headers, $hints, $sampleRow];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'AJ'; // Column AJ = co12 (36th column)
                $totalCols = 36;

                // Style metadata rows
                $sheet->getStyle('A1:B2')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => 'FF595959'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFF2F2F2'],
                    ],
                ]);

                // Header row styling (row 4)
                $headerRange = "A4:{$lastCol}4";
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => 'FFFFFFFF'],
                        'size' => 11,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF2F5496'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Hint row styling (row 5)
                $hintRange = "A5:{$lastCol}5";
                $sheet->getStyle($hintRange)->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'color' => ['argb' => 'FF808080'],
                        'size' => 9,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFF2F2F2'],
                    ],
                ]);

                // Sample row styling (row 6)
                $sampleRange = "A6:{$lastCol}6";
                $sheet->getStyle($sampleRange)->applyFromArray([
                    'font' => [
                        'color' => ['argb' => 'FF4472C4'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFDCE6F1'],
                    ],
                ]);

                // Add border to data area
                $dataRange = "A4:{$lastCol}6";
                $sheet->getStyle($dataRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFD9D9D9'],
                        ],
                    ],
                ]);

                // Color code monthly columns (M–X = col 13–24) with light green
                $mStart = 'M';
                $mEnd = 'X';
                $sheet->getStyle("{$mStart}4:{$mEnd}4")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF548235'],
                    ],
                ]);

                // Color code cash out columns (Y–AJ = col 25–36) with light orange
                $coStart = 'Y';
                $coEnd = 'AJ';
                $sheet->getStyle("{$coStart}4:{$coEnd}4")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFBF8F00'],
                    ],
                ]);

                // Number format for currency columns
                $currencyCols = ['K']; // unit_price
                foreach ($currencyCols as $col) {
                    $sheet->getStyle("{$col}6:{$col}1000")->getNumberFormat()
                        ->setFormatCode('#,##0');
                }

                // Number format for monthly and cash out columns
                for ($c = 13; $c <= 36; $c++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                    $sheet->getStyle("{$colLetter}6:{$colLetter}1000")->getNumberFormat()
                        ->setFormatCode('#,##0');
                }

                // Freeze header rows (freeze pane under row 5, so headers A4 and A5 are always frozen)
                $sheet->freezePane('A6');

                // Set specific column widths for the reference columns
                $sheet->getColumnDimension('A')->setWidth(18); // work_plan_code
                $sheet->getColumnDimension('B')->setWidth(30); // work_plan_name
                $sheet->getColumnDimension('C')->setWidth(18); // activity_code
                $sheet->getColumnDimension('D')->setWidth(30); // activity_name
                $sheet->getColumnDimension('E')->setWidth(15); // coa_code
                $sheet->getColumnDimension('F')->setWidth(30); // coa_name
            },
        ];
    }
}
