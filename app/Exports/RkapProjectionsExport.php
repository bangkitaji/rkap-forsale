<?php

namespace App\Exports;

use App\Models\RkapSubmission;
use App\Models\RkapPeriod;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class RkapProjectionsExport implements FromArray, WithEvents, ShouldAutoSize
{
    public function __construct(
        private readonly ?int $periodId,
        private readonly ?int $directorateId,
        private readonly ?int $departmentId,
        private readonly ?int $bureauId,
        private readonly ?array $filterStatus
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
            'Proyeksi Januari (P)', 'Proyeksi Februari (P)', 'Proyeksi Maret (P)', 
            'Proyeksi April (P)', 'Proyeksi Mei (P)', 'Proyeksi Juni (P)', 
            'Proyeksi Juli (P)', 'Proyeksi Agustus (P)', 'Proyeksi September (P)', 
            'Proyeksi Oktober (P)', 'Proyeksi November (P)', 'Proyeksi Desember (P)',
            'Total Proyeksi Akhir Tahun (P)', 'Selisih (B - P)'
        ];

        $rows[] = $headers;

        if (!$this->periodId) {
            return $rows;
        }

        $query = RkapSubmission::with([
            'bureau.department.directorate',
            'workPlans.activity.workPlan',
            'workPlans.workPlan',
            'workPlans.budgetItems.realizations',
            'workPlans.budgetItems.projections',
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

        // Apply status filtering
        $validStatuses = ['Selesai', 'Sedang Diisi', 'Belum Diisi'];
        $activeFilters = array_filter($this->filterStatus ?? [], fn($s) => in_array($s, $validStatuses));

        $filteredSubmissions = $submissions->filter(function ($sub) use ($activeFilters) {
            if (empty($activeFilters)) {
                return true;
            }

            $bureauTotalItems = 0;
            $bureauFilledItems = 0;

            foreach ($sub->workPlans as $wp) {
                foreach ($wp->budgetItems as $bi) {
                    $bureauTotalItems++;
                    $isFilled = $bi->projections->count() > 0 || (float) $bi->projection > 0;
                    if ($isFilled) {
                        $bureauFilledItems++;
                    }
                }
            }

            $percent = $bureauTotalItems > 0 ? round(($bureauFilledItems / $bureauTotalItems) * 100, 1) : 0.0;

            if ($percent === 100.0) {
                $status = 'Selesai';
            } elseif ($percent > 0.0) {
                $status = 'Sedang Diisi';
            } else {
                $status = 'Belum Diisi';
            }

            return in_array($status, $activeFilters);
        });

        $counter = 1;
        $startRowIndex = 2; // Data starts at Row 2

        foreach ($filteredSubmissions as $sub) {
            $dirName  = $sub->bureau->department->directorate->name ?? '-';
            $deptName = $sub->bureau->department->name ?? '-';
            $buroName = $sub->bureau->name ?? '-';

            foreach ($sub->workPlans as $wp) {
                $wpCode = $wp->workPlan?->code ?? $wp->activity?->workPlan?->code ?? $wp->program_code ?? '';
                $wpName = $wp->workPlan?->title ?? $wp->activity?->workPlan?->title ?? $wp->program_name ?? '';
                $activityCode = $wp->activity?->code ?? '';
                $activityName = $wp->activity?->title ?? '';

                foreach ($wp->budgetItems as $bi) {
                    $coaCode = $bi->account_code ?? '';
                    $coaDesc = $bi->description ?? '';
                    $remarks = $bi->remarks ?? '';
                    $volUnit = $bi->quantity . ' ' . $bi->unit . ($bi->unit_2 ? ' x ' . $bi->quantity_2 . ' ' . $bi->unit_2 : '');
                    $budgetTotal = (float) $bi->total_price;

                    // Projections map
                    $realizationsMap = $bi->realizations->pluck('amount', 'month')->toArray();
                    $projectionsMap  = $bi->projections->pluck('amount', 'month')->toArray();
                    $hasMonthlyProjections = count($projectionsMap) > 0;
                    $period = $sub->period;

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

                    // Add monthly projection values
                    for ($m = 1; $m <= 12; $m++) {
                        $projVal = 0.0;
                        $isClosed = $period && $period->isMonthClosed($m);
                        $hasRealization = isset($realizationsMap[$m]);

                        if ($hasRealization) {
                            $projVal = (float)$realizationsMap[$m];
                        } elseif ($isClosed) {
                            $projVal = 0.0;
                        } else {
                            if ($hasMonthlyProjections) {
                                $projVal = isset($projectionsMap[$m]) ? (float)$projectionsMap[$m] : 0.0;
                            } else {
                                $projVal = 0.0;
                            }
                        }

                        $row[] = $projVal;
                    }

                    // Total Proyeksi: sum of N to Y if monthly, or direct value if yearly
                    if ($hasMonthlyProjections) {
                        $row[] = "=SUM(N{$currentRow}:Y{$currentRow})";
                    } else {
                        $row[] = (float) $bi->projection;
                    }

                    // Selisih = Budget (M) - Total Proyeksi (Z)
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

                // Soft yellow header colors for monthly projection columns (N to Y)
                $sheet->getStyle('N1:Y1')->applyFromArray([
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFF2CC']],
                    'font' => ['color' => ['rgb' => '7F6000']],
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
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFFDF0']]
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

                $sheet->setTitle('Monitoring Proyeksi RKAP');
            },
        ];
    }
}
