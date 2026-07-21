<?php

namespace App\Exports;

use App\Models\RkapSubmission;
use App\Models\RkapPeriod;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class RkapRealizationsExport implements FromArray, WithEvents, ShouldAutoSize
{
    public function __construct(
        private readonly ?int $periodId,
        private readonly ?int $directorateId,
        private readonly ?int $departmentId,
        private readonly ?int $bureauId,
        private readonly ?string $search
    ) {}

    public function array(): array
    {
        $rows = [];

        // Single header row
        $headers = [
            'No', 'Direktorat', 'Departemen', 'Biro (Unit Kerja)', 
            'Kode Program', 'Nama Program Kerja', 
            'Kode Kegiatan', 'Nama Kegiatan', 
            'Kode Akun (COA)', 'Deskripsi COA', 
            'Remarks / Detail Belanja', 'Volume & Satuan',
            'Total Anggaran RKAP (B)',
            'Realisasi Januari (R)', 'Realisasi Februari (R)', 'Realisasi Maret (R)', 
            'Realisasi April (R)', 'Realisasi Mei (R)', 'Realisasi Juni (R)', 
            'Realisasi Juli (R)', 'Realisasi Agustus (R)', 'Realisasi September (R)', 
            'Realisasi Oktober (R)', 'Realisasi November (R)', 'Realisasi Desember (R)',
            'Total Realisasi YTD (R)', 'Selisih Anggaran vs Realisasi (B - R)'
        ];

        $rows[] = $headers;

        if (!$this->periodId) {
            return $rows;
        }

        // Fetch submissions using realization filter logic
        $query = RkapSubmission::with([
            'bureau.department.directorate',
            'workPlans.activity.workPlan',
            'workPlans.workPlan',
            'workPlans.budgetItems.realizations',
            'workPlans.budgetItems.monthlies',
            'period'
        ])
            ->where('rkap_period_id', $this->periodId)
            ->where('status', 'approved');

        if ($this->bureauId) {
            $query->where('bureau_id', $this->bureauId);
        } elseif ($this->departmentId) {
            $query->whereHas('bureau', fn($q) => $q->where('department_id', $this->departmentId));
        } elseif ($this->directorateId) {
            $query->whereHas('bureau.department', fn($q) => $q->where('directorate_id', $this->directorateId));
        }

        $submissions = $query->get();

        $counter = 1;
        $startRowIndex = 2; // Data starts at Row 2

        foreach ($submissions as $sub) {
            $dirName  = $sub->bureau->department->directorate->name ?? '-';
            $deptName = $sub->bureau->department->name ?? '-';
            $buroName = $sub->bureau->name ?? '-';

            foreach ($sub->workPlans as $wp) {
                $wpCode = $wp->workPlan?->code ?? $wp->activity?->workPlan?->code ?? $wp->program_code ?? '';
                $wpName = $wp->workPlan?->title ?? $wp->activity?->workPlan?->title ?? $wp->program_name ?? '';
                $activityCode = $wp->activity?->code ?? '';
                $activityName = $wp->activity?->title ?? '';

                foreach ($wp->budgetItems as $bi) {
                    // Match text search filter if defined
                    if ($this->search) {
                        $s = strtolower($this->search);
                        $matches = str_contains(strtolower($bi->account_code), $s) ||
                                   str_contains(strtolower($bi->description), $s) ||
                                   str_contains(strtolower($dirName), $s) ||
                                   str_contains(strtolower($deptName), $s) ||
                                   str_contains(strtolower($buroName), $s);
                        if (!$matches) {
                            continue;
                        }
                    }

                    $coaCode = $bi->account_code ?? '';
                    $coaDesc = $bi->description ?? '';
                    $remarks = $bi->remarks ?? '';
                    $volUnit = $bi->quantity . ' ' . $bi->unit . ($bi->unit_2 ? ' x ' . $bi->quantity_2 . ' ' . $bi->unit_2 : '');
                    $budgetTotal = (float) $bi->total_price;

                    // Realizations map
                    $realizationsMap = $bi->realizations->pluck('amount', 'month')->toArray();

                    $row = [
                        $counter++,
                        $dirName,
                        $deptName,
                        $buroName,
                        $wpCode,
                        $wpName,
                        $activityCode,
                        $activityName,
                        $coaCode,
                        $coaDesc,
                        $remarks,
                        $volUnit,
                        $budgetTotal,
                    ];

                    $currentRow = $startRowIndex + $counter - 2;

                    // Add monthly realization values
                    for ($m = 1; $m <= 12; $m++) {
                        $realVal = isset($realizationsMap[$m]) ? (float)$realizationsMap[$m] : 0.0;
                        $row[] = $realVal;
                    }

                    // Total Realisasi: sum of N to Y
                    $row[] = "=SUM(N{$currentRow}:Y{$currentRow})";

                    // Selisih = Budget (M) - Total Realisasi (Z)
                    $row[] = "=M{$currentRow}-Z{$currentRow}";

                    $rows[] = $row;
                }
            }
        }

        $lastDataRow = $startRowIndex + $counter - 2;

        if ($lastDataRow >= 2) {
            $totalRow = [
                'TOTAL',
                '', '', '', '', '', '', '', '', '', '', '', 
                "=SUM(M2:M{$lastDataRow})"
            ];
            for ($c = 14; $c <= 27; $c++) {
                $colLetter = Coordinate::stringFromColumnIndex($c);
                $totalRow[] = "=SUM({$colLetter}2:{$colLetter}{$lastDataRow})";
            }
            $rows[] = $totalRow;
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                // Style the single header row
                $sheet->getStyle('A1:AA1')->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'color' => ['rgb' => 'FFFFFF'],
                        'size'  => 10,
                    ],
                    'fill' => [
                        'fillType'   => 'solid',
                        'startColor' => ['rgb' => '1F385C'], // Premium Dark Blue
                    ],
                    'alignment' => [
                        'horizontal' => 'center',
                        'vertical'   => 'center',
                        'wrapText'   => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => 'thin',
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Soft green header colors for monthly realization columns (N to Y)
                $sheet->getStyle('N1:Y1')->applyFromArray([
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'D5EAD8']],
                    'font' => ['color' => ['rgb' => '1E4620']],
                ]);

                $highestRow = $sheet->getHighestRow();

                // Style data cells
                if ($highestRow >= 2) {
                    $sheet->getStyle("A2:AA{$highestRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => 'thin',
                                'color'       => ['rgb' => 'E0E0E0'],
                            ],
                        ],
                        'alignment' => [
                            'vertical' => 'center',
                        ],
                    ]);

                    // Format columns
                    $sheet->getStyle("M2:M{$highestRow}")
                        ->getNumberFormat()->setFormatCode('#,##0');

                    for ($c = 14; $c <= 25; $c++) {
                        $col = Coordinate::stringFromColumnIndex($c);
                        $sheet->getStyle("{$col}2:{$col}{$highestRow}")
                            ->getNumberFormat()->setFormatCode('#,##0;-#,##0;0');
                        $sheet->getStyle("{$col}2:{$col}{$highestRow}")
                            ->getAlignment()->setHorizontal('right');
                    }

                    $sheet->getStyle("Z2:AA{$highestRow}")
                        ->getNumberFormat()->setFormatCode('#,##0;-#,##0;0');
                    $sheet->getStyle("Z2:AA{$highestRow}")->getFont()->setBold(true);

                    // Add subtle background color to columns
                    $sheet->getStyle("M2:M{$highestRow}")->applyFromArray([
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F2F4F7']]
                    ]);
                    $sheet->getStyle("Z2:Z{$highestRow}")->applyFromArray([
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EAF7EB']]
                    ]);
                    $sheet->getStyle("AA2:AA{$highestRow}")->applyFromArray([
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FDF2F2']]
                    ]);

                    // Style the TOTAL row specifically
                    $sheet->getStyle("A{$highestRow}:AA{$highestRow}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                        ],
                        'borders' => [
                            'top' => [
                                'borderStyle' => 'thin',
                                'color' => ['rgb' => '000000'],
                            ],
                            'bottom' => [
                                'borderStyle' => 'double',
                                'color' => ['rgb' => '000000'],
                            ],
                        ],
                        'fill' => [
                            'fillType' => 'solid',
                            'startColor' => ['rgb' => 'EAECEF'],
                        ],
                    ]);
                }

                $sheet->getRowDimension(1)->setRowHeight(32);

                // Freeze panes at column N
                $sheet->freezePane('N2');

                $sheet->setTitle('Monitoring Realisasi RKAP');
            },
        ];
    }
}
