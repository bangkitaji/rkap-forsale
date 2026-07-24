<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class RkapCompilationExport implements FromArray, WithEvents, ShouldAutoSize
{
    public function __construct(
        private readonly Collection $submissions,
        private readonly string $periodTitle
    ) {}

    public function array(): array
    {
        $rows = [];

        // Row 1 — main headers
        $rows[] = array_merge(
            ['Kode Direktorat', 'Kode Departemen', 'Kode Biro', 'COA SAP', 'COA SAP Desc', 'Kode Group Cashflow', 'Nama Group Cashflow', 'Kode Group Difference', 'Nama Group Difference', 'Kode Program Kerja', 'Program Kerja', 'Kode Kegiatan', 'Nama Kegiatan'],
            ['Budget', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Kas Keluar', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Realisasi', '', '', '', '', '', '', '', '', '', '', '', '']
        );

        // Row 2 — month sub-headers
        $rows[] = array_merge(
            ['', '', '', '', '', '', '', '', '', '', '', '', ''],
            $this->monthHeaders('Budget'),
            $this->monthHeaders('Kas Keluar'),
            $this->monthHeaders('Realisasi')
        );

        $grandBudgetByMonth  = array_fill(1, 12, 0);
        $grandCashByMonth    = array_fill(1, 12, 0);
        $grandRealByMonth    = array_fill(1, 12, 0);
        $grandBudgetTotal    = 0;
        $grandCashTotal      = 0;
        $grandRealTotal      = 0;

        foreach ($this->submissions as $submission) {
            $submission->loadMissing([
                'bureau.department.directorate',
                'workPlans.activity',
                'workPlans.workPlan',
                'workPlans.budgetItems.monthlies',
                'workPlans.budgetItems.cashOuts',
                'workPlans.budgetItems.realizations',
                'workPlans.budgetItems.coa.cashflowGroup',
                'workPlans.budgetItems.differenceGroup',
                'workPlans.budgetItems.coa.differenceGroups',
            ]);

            $dirCode  = $submission->bureau?->department?->directorate?->code ?? '-';
            $deptCode = $submission->bureau?->department?->code ?? '-';
            $buroCode = $submission->bureau?->code ?? '-';

            foreach ($submission->workPlans as $wp) {
                foreach ($wp->budgetItems as $bi) {
                    $cfGroup = $bi->coa?->cashflowGroup;
                    $cfCode  = $cfGroup?->code ?? '-';
                    $cfName  = $cfGroup?->name ?? '-';

                    // Kumpulkan daftar difference groups sebagai array of [code, name]
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

                    $budgetByMonth  = array_fill(1, 12, 0);
                    $cashOutByMonth = array_fill(1, 12, 0);
                    $realByMonth    = array_fill(1, 12, 0);

                    foreach ($bi->monthlies as $monthly) {
                        $budgetByMonth[(int) $monthly->month] = (float) $monthly->amount;
                    }
                    foreach ($bi->cashOuts as $cashOut) {
                        $cashOutByMonth[(int) $cashOut->month] = (float) $cashOut->amount;
                    }
                    foreach ($bi->realizations as $realization) {
                        $realByMonth[(int) $realization->month] = (float) $realization->amount;
                    }

                    $budgetTotal = array_sum($budgetByMonth);
                    $cashTotal   = array_sum($cashOutByMonth);
                    $realTotal   = array_sum($realByMonth);

                    // Akumulasi grand totals dihitung sekali per budget item
                    for ($m = 1; $m <= 12; $m++) {
                        $grandBudgetByMonth[$m] += $budgetByMonth[$m];
                        $grandCashByMonth[$m]   += $cashOutByMonth[$m];
                        $grandRealByMonth[$m]   += $realByMonth[$m];
                    }
                    $grandBudgetTotal += $budgetTotal;
                    $grandCashTotal   += $cashTotal;
                    $grandRealTotal   += $realTotal;

                    // Buat 1 baris per difference group
                    foreach ($diffGroups as $dg) {
                        $rows[] = array_merge(
                            [
                                $dirCode,
                                $deptCode,
                                $buroCode,
                                $bi->account_code ?? '',
                                $bi->description ?? '',
                                $cfCode,
                                $cfName,
                                $dg['code'],
                                $dg['name'],
                                $wp->workPlan?->code ?? '',
                                $wp->workPlan?->title ?? ($wp->program_name ?? ''),
                                $wp->activity?->code ?? '',
                                $wp->activity?->title ?? '',
                            ],
                            array_values($budgetByMonth),
                            [$budgetTotal],
                            array_values($cashOutByMonth),
                            [$cashTotal],
                            array_values($realByMonth),
                            [$realTotal]
                        );
                    }
                }
            }
        }

        // Grand total row
        $rows[] = array_merge(
            ['', '', '', '', '', '', '', '', '', '', '', '', 'GRAND TOTAL'],
            array_values($grandBudgetByMonth),
            [$grandBudgetTotal],
            array_values($grandCashByMonth),
            [$grandCashTotal],
            array_values($grandRealByMonth),
            [$grandRealTotal]
        );

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                // Merge static header cells vertically (A-M)
                foreach (range('A', 'M') as $col) {
                    $sheet->mergeCells("{$col}1:{$col}2");
                }

                // Group headers: Budget (N-Z), Kas Keluar (AA-AM), Realisasi (AN-AZ)
                $sheet->mergeCells('N1:Z1');
                $sheet->mergeCells('AA1:AM1');
                $sheet->mergeCells('AN1:AZ1');

                // Header styling
                $sheet->getStyle('A1:AZ2')->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '1A3C6E']],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => '000000']]],
                ]);

                // Realisasi header — green for distinction
                $sheet->getStyle('AN1:AZ2')->applyFromArray([
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E6B3C']],
                ]);

                // Grand total row styling
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("A{$highestRow}:AZ{$highestRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'C00000']],
                ]);

                // Data rows styling
                if ($highestRow >= 3) {
                    $dataEnd = $highestRow - 1;
                    if ($dataEnd >= 3) {
                        $sheet->getStyle("A3:AZ{$dataEnd}")->applyFromArray([
                            'borders'   => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'D9D9D9']]],
                            'alignment' => ['vertical' => 'top'],
                        ]);
                    }
                    // Number format for numeric columns
                    $sheet->getStyle("N3:AZ{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                }

                // Freeze pane at data start
                $sheet->freezePane('A3');

                // Set sheet title
                $sheet->setTitle('Kompilasi RKAP');
            },
        ];
    }

    private function monthHeaders(string $prefix): array
    {
        $headers = [];
        for ($m = 1; $m <= 12; $m++) {
            $headers[] = "{$prefix} {$m}";
        }
        $headers[] = "{$prefix} Total";
        return $headers;
    }
}
