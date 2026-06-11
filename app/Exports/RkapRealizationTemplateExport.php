<?php

namespace App\Exports;

use App\Models\RkapBudgetItem;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Generates the realization upload template (.xlsx / .csv).
 *
 * Columns:
 *   budget_item_id | month | bureaus_name | workplan_code | workplan_name |
 *   activity_code | activity_name | coa_code | coa_desc | budget_item_desc |
 *   amount_of_rkap | sum_of_uploaded_realization | notes | amount
 */
class RkapRealizationTemplateExport implements FromArray, WithEvents, ShouldAutoSize
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
            'month',
            'bureaus_name',
            'workplan_code',
            'workplan_name',
            'activity_code',
            'activity_name',
            'coa_code',
            'coa_desc',
            'budget_item_desc',
            'amount_of_rkap',
            'sum_of_uploaded_realization',
            'notes',
            'amount',
        ];

        $hints = [
            '(integer)',
            '(1–12)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(otomatis diabaikan)',
            '(opsional)',
            '(numeric ≥ 0)',
        ];

        $rows = [$headers, $hints];

        if ($this->periodId) {
            $budgetItems = RkapBudgetItem::whereHas('workPlan.submission', function ($q) {
                $q->where('rkap_period_id', $this->periodId);
            })
            ->with([
                'workPlan.submission.bureau',
                'workPlan.activity',
                'workPlan.workPlan',
                'realizations',
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
                $sumRealization = (float) $item->realizations->sum('amount');

                // Map current realizations by month to easily fetch
                $realizationsMap = $item->realizations->pluck('amount', 'month')->toArray();

                for ($month = 1; $month <= 12; $month++) {
                    $currentAmount = isset($realizationsMap[$month]) ? (float) $realizationsMap[$month] : 0.0;

                    $rows[] = [
                        $item->id,
                        $month,
                        $bureauName,
                        $wpCode,
                        $wpName,
                        $activityCode,
                        $activityName,
                        $coaCode,
                        $coaDesc,
                        $biDesc,
                        $amountRkap,
                        $sumRealization,
                        '', // notes starts empty
                        $currentAmount,
                    ];
                }
            }
        } else {
            // Sample data row when no period is selected
            $rows[] = [
                1,
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
                0,
                'Realisasi Jan',
                5000000,
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                // ── Header row (row 1) ──
                $sheet->getStyle('A1:N1')->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'color' => ['rgb' => 'FFFFFF'],
                        'size'  => 11,
                    ],
                    'fill' => [
                        'fillType'   => 'solid',
                        'startColor' => ['rgb' => '1E6B3C'],
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

                // ── Hint row (row 2) ──
                $sheet->getStyle('A2:N2')->applyFromArray([
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

                // ── Sample/Real data rows (3+) ──
                $highestRow = $sheet->getHighestRow();
                if ($highestRow >= 3) {
                    $sheet->getStyle("A3:N{$highestRow}")->applyFromArray([
                        'fill' => [
                            'fillType'   => 'solid',
                            'startColor' => ['rgb' => 'ECFDF5'],
                        ],
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

                    // Number formats for amount_of_rkap (K), sum_of_uploaded_realization (L), amount (N)
                    $sheet->getStyle("K3:K{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                    $sheet->getStyle("L3:L{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                    $sheet->getStyle("N3:N{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                }

                // Row heights
                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->getRowDimension(2)->setRowHeight(16);

                // Column widths (manual override after auto-size)
                $sheet->getColumnDimension('A')->setWidth(18); // budget_item_id
                $sheet->getColumnDimension('B')->setWidth(8);  // month
                $sheet->getColumnDimension('C')->setWidth(25); // bureaus_name
                $sheet->getColumnDimension('D')->setWidth(18); // workplan_code
                $sheet->getColumnDimension('E')->setWidth(30); // workplan_name
                $sheet->getColumnDimension('F')->setWidth(18); // activity_code
                $sheet->getColumnDimension('G')->setWidth(30); // activity_name
                $sheet->getColumnDimension('H')->setWidth(12); // coa_code
                $sheet->getColumnDimension('I')->setWidth(30); // coa_desc
                $sheet->getColumnDimension('J')->setWidth(30); // budget_item_desc
                $sheet->getColumnDimension('K')->setWidth(18); // amount_of_rkap
                $sheet->getColumnDimension('L')->setWidth(25); // sum_of_uploaded_realization
                $sheet->getColumnDimension('M')->setWidth(25); // notes
                $sheet->getColumnDimension('N')->setWidth(18); // amount

                // Add a comment on budget_item_id header
                $comment = $sheet->getComment('A1');
                $comment->getText()->createTextRun(
                    "budget_item_id: ID dari tabel rkap_budget_items.\n" .
                    "Harus termasuk dalam periode RKAP yang dipilih saat upload."
                );

                // Add a comment on month header
                $monthComment = $sheet->getComment('B1');
                $monthComment->getText()->createTextRun(
                    "month: Bulan dalam angka 1–12.\n" .
                    "1 = Januari, 12 = Desember."
                );

                // Add a comment on amount header (last column)
                $amountComment = $sheet->getComment('N1');
                $amountComment->getText()->createTextRun(
                    "amount: Jumlah realisasi anggaran untuk bulan tersebut.\n" .
                    "Masukkan angka lebih besar atau sama dengan 0."
                );

                // Sheet title
                $sheet->setTitle('Realisasi Template');

                // Freeze header rows
                $sheet->freezePane('A3');
            },
        ];
    }
}
