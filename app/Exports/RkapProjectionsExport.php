<?php

namespace App\Exports;

use App\Models\RkapSubmission;
use App\Models\RkapPeriod;
use App\Models\Setting;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class RkapProjectionsExport implements FromArray, WithEvents
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

        // Pre-compute closed months ONCE (avoids N×12 Setting::get queries)
        $period = RkapPeriod::find($this->periodId);
        $closedMonths = [];
        if ($period) {
            $closingDay = (int) Setting::get('rkap_closing_day', 0);
            if ($closingDay >= 1) {
                $now = now();
                for ($m = 1; $m <= 12; $m++) {
                    $nextMonth = \Carbon\Carbon::create($period->year, $m, 1)->addMonth();
                    $dayToUse = min($closingDay, $nextMonth->daysInMonth);
                    $closingDate = $nextMonth->day($dayToUse)->endOfDay();
                    $closedMonths[$m] = $now->greaterThan($closingDate);
                }
            }
        }

        // Optimized eager loading: only load what we need
        $query = RkapSubmission::with([
            'bureau.department.directorate',
            'workPlans.activity.workPlan',
            'workPlans.workPlan',
            'workPlans.budgetItems.realizations',
            'workPlans.budgetItems.projections',
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

                    // Projections map (use pre-loaded collections)
                    $realizationsMap = $bi->realizations->pluck('amount', 'month')->toArray();
                    $projectionsMap  = $bi->projections->pluck('amount', 'month')->toArray();
                    $hasMonthlyProjections = count($projectionsMap) > 0;

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

                    // Add monthly projection values (use pre-computed closedMonths)
                    for ($m = 1; $m <= 12; $m++) {
                        if (isset($realizationsMap[$m])) {
                            $row[] = (float)$realizationsMap[$m];
                        } elseif (!empty($closedMonths[$m])) {
                            $row[] = 0.0;
                        } elseif ($hasMonthlyProjections && isset($projectionsMap[$m])) {
                            $row[] = (float)$projectionsMap[$m];
                        } else {
                            $row[] = 0.0;
                        }
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

                $highestRow = $sheet->getHighestRow();
                $highestCol = 'AA';

                // Set fixed column widths instead of auto-size (major perf gain)
                $colWidths = [
                    'A' => 5,   // No
                    'B' => 18,  // Direktorat
                    'C' => 18,  // Departemen
                    'D' => 22,  // Biro
                    'E' => 12,  // Kode Program
                    'F' => 25,  // Nama Program
                    'G' => 12,  // Kode Kegiatan
                    'H' => 25,  // Nama Kegiatan
                    'I' => 12,  // Kode COA
                    'J' => 25,  // Deskripsi COA
                    'K' => 20,  // Remarks
                    'L' => 14,  // Volume & Satuan
                    'M' => 18,  // Total Anggaran
                ];
                foreach ($colWidths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
                // Monthly + total columns: uniform width
                for ($c = 14; $c <= 27; $c++) {
                    $col = Coordinate::stringFromColumnIndex($c);
                    $sheet->getColumnDimension($col)->setWidth(18);
                }

                // Style header row in one call
                $sheet->getStyle("A1:{$highestCol}1")->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'color' => ['rgb' => 'FFFFFF'],
                        'size'  => 10,
                    ],
                    'fill' => [
                        'fillType'   => 'solid',
                        'startColor' => ['rgb' => '1F385C'],
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

                // Soft yellow header for monthly projection columns (N to Y)
                $sheet->getStyle('N1:Y1')->applyFromArray([
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFF2CC']],
                    'font' => ['color' => ['rgb' => '7F6000']],
                ]);

                if ($highestRow >= 2) {
                    // Style all data cells in one call
                    $sheet->getStyle("A2:{$highestCol}{$highestRow}")->applyFromArray([
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

                    // Format numeric columns in batch ranges (instead of per-column loop)
                    $sheet->getStyle("M2:M{$highestRow}")
                        ->getNumberFormat()->setFormatCode('#,##0');

                    // N to Y (cols 14-25) — single range styling
                    $sheet->getStyle("N2:Y{$highestRow}")
                        ->getNumberFormat()->setFormatCode('#,##0;-#,##0;0');
                    $sheet->getStyle("N2:Y{$highestRow}")
                        ->getAlignment()->setHorizontal('right');

                    // Z and AA — total & selisih
                    $sheet->getStyle("Z2:AA{$highestRow}")
                        ->getNumberFormat()->setFormatCode('#,##0;-#,##0;0');
                    $sheet->getStyle("Z2:AA{$highestRow}")->getFont()->setBold(true);

                    // Background colors on key columns
                    $sheet->getStyle("M2:M{$highestRow}")->applyFromArray([
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F2F4F7']]
                    ]);
                    $sheet->getStyle("Z2:Z{$highestRow}")->applyFromArray([
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFFDF0']]
                    ]);
                    $sheet->getStyle("AA2:AA{$highestRow}")->applyFromArray([
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FDF2F2']]
                    ]);

                    // Style the TOTAL row
                    $sheet->getStyle("A{$highestRow}:AA{$highestRow}")->applyFromArray([
                        'font' => ['bold' => true],
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
                $sheet->freezePane('N2');
                $sheet->setTitle('Monitoring Proyeksi RKAP');
            },
        ];
    }
}
