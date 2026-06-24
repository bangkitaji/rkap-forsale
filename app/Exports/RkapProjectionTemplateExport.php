<?php

namespace App\Exports;

use App\Models\RkapBudgetItem;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class RkapProjectionTemplateExport implements FromArray, WithEvents, ShouldAutoSize
{
    protected ?int $periodId;

    public function __construct(?int $periodId = null)
    {
        $this->periodId = $periodId;
    }

    public function array(): array
    {
        $headers = [
            'budget_item_id',
            'bureaus_name',
            'workplan_code',
            'workplan_name',
            'activity_code',
            'activity_name',
            'coa_code',
            'coa_desc',
            'budget_item_desc',
            'amount_of_rkap',
            'yearly',
            'm1',
            'm2',
            'm3',
            'm4',
            'm5',
            'm6',
            'm7',
            'm8',
            'm9',
            'm10',
            'm11',
            'm12',
        ];

        $hints = [
            '(integer)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(Tahunan - Kosongkan m1-m12 jika diisi)',
            '(Januari)',
            '(Februari)',
            '(Maret)',
            '(April)',
            '(Mei)',
            '(Juni)',
            '(Juli)',
            '(Agustus)',
            '(September)',
            '(Oktober)',
            '(November)',
            '(Desember)',
        ];

        $rows = [$headers, $hints];

        if ($this->periodId) {
            $budgetItems = RkapBudgetItem::whereHas('workPlan.submission', function ($q) {
                $q->where('rkap_period_id', $this->periodId)
                  ->where('status', 'approved');
            })
                ->with([
                    'workPlan.submission.bureau',
                    'workPlan.activity',
                    'workPlan.workPlan',
                    'projections' => fn($q) => $q->where('rkap_period_id', $this->periodId),
                ])
                ->get();

            foreach ($budgetItems as $item) {
                $bureauName = $item->workPlan->submission->bureau->name ?? '';
                $wpCode = $item->workPlan->program_code ?? '';
                $wpName = $item->workPlan->program_name ?? '';
                $activityCode = $item->workPlan->activity?->code ?? '';
                $activityName = $item->workPlan->activity?->title ?? '';
                $coaCode = $item->account_code ?? '';
                $coaDesc = $item->description ?? '';
                $biDesc = $item->remarks ?? $item->description ?? '';
                $amountRkap = (float) $item->total_price;

                $projectionsMap = $item->projections->pluck('amount', 'month')->toArray();
                $hasMonthly = count($projectionsMap) > 0;

                $row = [
                    $item->id,
                    $bureauName,
                    $wpCode,
                    $wpName,
                    $activityCode,
                    $activityName,
                    $coaCode,
                    $coaDesc,
                    $biDesc,
                    $amountRkap,
                    (float) $item->projection,
                ];

                for ($m = 1; $m <= 12; $m++) {
                    $row[] = ($hasMonthly && isset($projectionsMap[$m])) ? (float) $projectionsMap[$m] : 0.0;
                }

                $rows[] = $row;
            }
        } else {
            // Default sample data row
            $row = [
                1,
                'Biro TI',
                'WP001',
                'Pengembangan Aplikasi',
                'ACT001',
                'Coding & Testing',
                '521111',
                'Belanja Bahan',
                'Laptop developer',
                15000000,
                0.0,
            ];
            for ($m = 1; $m <= 12; $m++) {
                $row[] = 0.0;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                // Header styling (row 1)
                $sheet->getStyle('A1:W1')->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'color' => ['rgb' => 'FFFFFF'],
                        'size'  => 11,
                    ],
                    'fill' => [
                        'fillType'   => 'solid',
                        'startColor' => ['rgb' => '4F46E5'], // Sleek Indigo
                    ],
                    'alignment' => [
                        'horizontal' => 'center',
                        'vertical'   => 'center',
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => 'thin',
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Hint styling (row 2)
                $sheet->getStyle('A2:W2')->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'color'  => ['rgb' => '6B7280'],
                        'size'   => 9,
                    ],
                    'fill' => [
                        'fillType'   => 'solid',
                        'startColor' => ['rgb' => 'F3F4F6'],
                    ],
                    'alignment' => [
                        'horizontal' => 'center',
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => 'thin',
                            'color'       => ['rgb' => 'D1D5DB'],
                        ],
                    ],
                ]);

                // Data row styling (3+)
                $highestRow = $sheet->getHighestRow();
                if ($highestRow >= 3) {
                    $sheet->getStyle("A3:W{$highestRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => 'thin',
                                'color'       => ['rgb' => 'D1D5DB'],
                            ],
                        ],
                        'alignment' => [
                            'vertical' => 'center',
                        ],
                    ]);

                    // Format amount_of_rkap and yearly
                    $sheet->getStyle("J3:K{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');

                    // Format month columns
                    $cols = ['L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W'];
                    foreach ($cols as $col) {
                        $sheet->getStyle("{$col}3:{$col}{$highestRow}")
                            ->getNumberFormat()
                            ->setFormatCode('0');
                    }
                }

                // Row heights
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getRowDimension(2)->setRowHeight(18);

                // Column widths
                $sheet->getColumnDimension('A')->setWidth(18); // budget_item_id
                $sheet->getColumnDimension('B')->setWidth(25); // bureaus_name
                $sheet->getColumnDimension('C')->setWidth(18); // workplan_code
                $sheet->getColumnDimension('D')->setWidth(30); // workplan_name
                $sheet->getColumnDimension('E')->setWidth(18); // activity_code
                $sheet->getColumnDimension('F')->setWidth(30); // activity_name
                $sheet->getColumnDimension('G')->setWidth(12); // coa_code
                $sheet->getColumnDimension('H')->setWidth(30); // coa_desc
                $sheet->getColumnDimension('I')->setWidth(30); // budget_item_desc
                $sheet->getColumnDimension('J')->setWidth(18); // amount_of_rkap
                $sheet->getColumnDimension('K')->setWidth(18); // yearly

                // Month columns width
                $cols = ['L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W'];
                foreach ($cols as $col) {
                    $sheet->getColumnDimension($col)->setWidth(12);
                }

                // Sheet title
                $sheet->setTitle('Proyeksi Template');

                // Freeze header rows
                $sheet->freezePane('A3');
            },
        ];
    }
}
