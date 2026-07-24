<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class RkapCompilationExport implements FromArray, WithEvents, ShouldAutoSize
{
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private const STATIC_HEADERS = [
        'Dir',
        'Dept',
        'Biro',
        'Kode Program Kerja',
        'Nama Program Kerja',
        'Kode Kegiatan',
        'Nama Kegiatan',
        'Kode Anggaran',
        'Nama Anggaran',
        'COA SAP',
        'COA SAP Desc',
        'Kode CF',
        'Nama CF',
        'Kode Diff',
        'Nama Group Diff',
    ];

    public function __construct(
        private readonly Collection $submissions,
        private readonly string $periodTitle
    ) {}

    public function array(): array
    {
        $rows = [];

        // Row 1 — 15 static labels + group headers (Budget/CF/Diff each span 13 cols)
        $rows[] = array_merge(
            self::STATIC_HEADERS,
            ['Budget'], array_fill(0, 12, ''),
            ['CF'],     array_fill(0, 12, ''),
            ['Diff'],   array_fill(0, 12, '')
        );

        // Row 2 — 15 empty cells for static columns + month sub-headers per section
        $monthRow = array_merge(self::MONTHS, ['Total']);
        $rows[] = array_merge(
            array_fill(0, 15, ''),
            $monthRow,
            $monthRow,
            $monthRow
        );

        $grandBudgetByMonth = array_fill(1, 12, 0);
        $grandCashByMonth   = array_fill(1, 12, 0);
        $grandBudgetTotal   = 0;
        $grandCashTotal     = 0;

        // Collect all rows before sorting
        $dataRows = [];

        foreach ($this->submissions as $submission) {
            $submission->loadMissing([
                'bureau.department.directorate',
                'workPlans.activity',
                'workPlans.workPlan',
                'workPlans.budgetItems.monthlies',
                'workPlans.budgetItems.cashOuts',
                'workPlans.budgetItems.coa.cashflowGroup',
                'workPlans.budgetItems.coa.coaGroup',
                'workPlans.budgetItems.differenceGroup',
                'workPlans.budgetItems.coa.differenceGroups',
            ]);

            $dirCode  = $submission->bureau?->department?->directorate?->code ?? '-';
            $deptCode = $submission->bureau?->department?->code ?? '-';
            $buroCode = $submission->bureau?->code ?? '-';

            foreach ($submission->workPlans as $wp) {
                $actCode = $wp->activity?->code ?? '';
                $wpCode  = $wp->workPlan?->code ?? '';

                foreach ($wp->budgetItems as $bi) {
                    $cfGroup      = $bi->coa?->cashflowGroup;
                    $cfCode       = $cfGroup?->code ?? '-';
                    $cfName       = $cfGroup?->name ?? '-';

                    $coaGroup     = $bi->coa?->coaGroup;
                    $coaGroupCode = $coaGroup?->code ?? '-';
                    $coaGroupName = $coaGroup?->name ?? '-';

                    $coaCode  = $bi->coa?->code ?? ($bi->account_code ?? '');
                    $coaTitle = $bi->coa?->title ?? ($bi->description ?? '');

                    // Difference groups
                    $diffGroups = [];
                    if ($bi->differenceGroup) {
                        $diffGroups[] = [
                            'code' => $bi->differenceGroup->code ?? '-',
                            'name' => $bi->differenceGroup->name ?? '-',
                        ];
                    } elseif ($bi->coa && $bi->coa->differenceGroups->isNotEmpty()) {
                        foreach ($bi->coa->differenceGroups as $dg) {
                            $diffGroups[] = [
                                'code' => $dg->code ?? '-',
                                'name' => $dg->name ?? '-',
                            ];
                        }
                    } else {
                        $diffGroups[] = ['code' => '-', 'name' => '-'];
                    }

                    // Monthly data
                    $budgetByMonth  = array_fill(1, 12, 0);
                    $cashOutByMonth = array_fill(1, 12, 0);

                    foreach ($bi->monthlies as $monthly) {
                        $budgetByMonth[(int) $monthly->month] = (float) $monthly->amount;
                    }
                    foreach ($bi->cashOuts as $cashOut) {
                        $cashOutByMonth[(int) $cashOut->month] = (float) $cashOut->amount;
                    }

                    $budgetTotal = array_sum($budgetByMonth);
                    $cashTotal   = array_sum($cashOutByMonth);

                    // Diff per month (Budget - CF)
                    $diffByMonth = [];
                    for ($m = 1; $m <= 12; $m++) {
                        $diffByMonth[$m] = $budgetByMonth[$m] - $cashOutByMonth[$m];
                    }
                    $diffTotal = $budgetTotal - $cashTotal;

                    // Grand total accumulation (once per budget item)
                    for ($m = 1; $m <= 12; $m++) {
                        $grandBudgetByMonth[$m] += $budgetByMonth[$m];
                        $grandCashByMonth[$m]   += $cashOutByMonth[$m];
                    }
                    $grandBudgetTotal += $budgetTotal;
                    $grandCashTotal   += $cashTotal;

                    foreach ($diffGroups as $dg) {
                        $dataRows[] = [
                            'act_code' => $actCode,
                            'wp_code'  => $wpCode,
                            'coa_code' => $coaCode,
                            'row'      => array_merge(
                                [
                                    $dirCode,
                                    $deptCode,
                                    $buroCode,
                                    $wpCode,
                                    $wp->workPlan?->title ?? ($wp->program_name ?? ''),
                                    $actCode,
                                    $wp->activity?->title ?? '',
                                    $coaGroupCode,
                                    $coaGroupName,
                                    $coaCode,
                                    $coaTitle,
                                    $cfCode,
                                    $cfName,
                                    $dg['code'],
                                    $dg['name'],
                                ],
                                array_values($budgetByMonth),
                                [$budgetTotal],
                                array_values($cashOutByMonth),
                                [$cashTotal],
                                array_values($diffByMonth),
                                [$diffTotal]
                            ),
                        ];
                    }
                }
            }
        }

        // Sort by activity code, then work plan code, then COA code
        usort($dataRows, fn($a, $b) =>
            [$a['act_code'], $a['wp_code'], $a['coa_code']]
            <=>
            [$b['act_code'], $b['wp_code'], $b['coa_code']]
        );

        foreach ($dataRows as $dr) {
            $rows[] = $dr['row'];
        }

        // Grand total row
        $grandDiffByMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $grandDiffByMonth[$m] = $grandBudgetByMonth[$m] - $grandCashByMonth[$m];
        }
        $grandDiffTotal = $grandBudgetTotal - $grandCashTotal;

        $rows[] = array_merge(
            array_fill(0, 14, ''),
            ['GRAND TOTAL'],
            array_values($grandBudgetByMonth),
            [$grandBudgetTotal],
            array_values($grandCashByMonth),
            [$grandCashTotal],
            array_values($grandDiffByMonth),
            [$grandDiffTotal]
        );

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                // No vertical merge for static cols A-O (both rows show labels)
                // Merge horizontal group headers in Row 1 only:
                // Budget: P1:AB1 (13 cols: P-AB)
                $sheet->mergeCells('P1:AB1');
                // CF: AC1:AO1 (13 cols: AC-AO)
                $sheet->mergeCells('AC1:AO1');
                // Diff: AP1:BB1 (13 cols: AP-BB)
                $sheet->mergeCells('AP1:BB1');

                // Header styling — all of rows 1 & 2
                $sheet->getStyle('A1:BB2')->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '1A3C6E']],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => '000000']]],
                ]);

                // CF header — teal accent
                $sheet->getStyle('AC1:AO2')->applyFromArray([
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '155E75']],
                ]);

                // Diff header — orange accent
                $sheet->getStyle('AP1:BB2')->applyFromArray([
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '92400E']],
                ]);

                // Grand total row styling
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("A{$highestRow}:BB{$highestRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'C00000']],
                ]);

                // Data rows styling
                if ($highestRow >= 3) {
                    $dataEnd = $highestRow - 1;
                    if ($dataEnd >= 3) {
                        $sheet->getStyle("A3:BB{$dataEnd}")->applyFromArray([
                            'borders'   => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'D9D9D9']]],
                            'alignment' => ['vertical' => 'top'],
                        ]);
                    }
                    // Number format for numeric columns (P onwards)
                    $sheet->getStyle("P3:BB{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                }

                // Freeze pane after header rows
                $sheet->freezePane('A3');

                // Sheet title
                $sheet->setTitle('Kompilasi RKAP');
            },
        ];
    }
}
