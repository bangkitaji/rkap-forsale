<?php

namespace App\Exports;

use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemRealization;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Generates the mass-update realization template (.xlsx).
 *
 * Covers all months from January (1) up to $lastClosedMonth (inclusive).
 * Each budget item produces N rows — one per month in that range.
 * The `amount` column is pre-filled with the latest uploaded realization
 * for that budget item + period + month combination (or 0 if none exists yet).
 *
 * Columns (same structure as RkapRealizationTemplateExport):
 *   budget_item_id | bureaus_name | workplan_code | workplan_name |
 *   activity_code | activity_name | coa_code | coa_desc | budget_item_desc |
 *   amount_of_rkap | sum_of_uploaded_realization | notes | month | amount
 */
class RkapRealizationMassUpdateTemplateExport implements FromArray, WithEvents, ShouldAutoSize
{
    protected ?int $periodId;
    protected int  $lastClosedMonth;

    public function __construct(?int $periodId = null, int $lastClosedMonth = 1)
    {
        $this->periodId        = $periodId;
        $this->lastClosedMonth = max(1, min(12, $lastClosedMonth));
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
            'sum_of_uploaded_realization',
            'notes',
            'month',
            'amount',
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
            '(otomatis diabaikan)',
            '(opsional)',
            '(1–' . $this->lastClosedMonth . ')',
            '(numeric, isi sesuai realisasi)',
        ];

        $rows = [$headers, $hints];

        if (! $this->periodId) {
            return $rows;
        }

        $budgetItems = RkapBudgetItem::whereHas('workPlan.submission', function ($q) {
            $q->where('rkap_period_id', $this->periodId)
              ->where('status', 'approved');
        })
            ->with([
                'workPlan.submission.bureau',
                'workPlan.activity',
                'workPlan.workPlan',
                // Eager-load only realizations for this specific period
                'realizations' => fn ($q) => $q->where('rkap_period_id', $this->periodId),
            ])
            ->get();

        foreach ($budgetItems as $item) {
            $bureauName    = $item->workPlan->submission->bureau->name ?? '';
            $wpCode        = $item->workPlan->program_code ?? '';
            $wpName        = $item->workPlan->program_name ?? '';
            $activityCode  = $item->workPlan->activity?->code ?? '';
            $activityName  = $item->workPlan->activity?->title ?? '';
            $coaCode       = $item->account_code ?? '';
            $coaDesc       = $item->description ?? '';
            $biDesc        = $item->remarks ?? $item->description ?? '';
            $amountRkap    = (float) $item->total_price;
            $sumRealization = (float) $item->realizations->sum('amount');

            // Build a map of month => amount from existing realizations
            /** @var array<int, float> $realizationsMap */
            $realizationsMap = $item->realizations
                ->pluck('amount', 'month')
                ->map(fn ($v) => (float) $v)
                ->toArray();

            // One row per month from January to lastClosedMonth
            for ($m = 1; $m <= $this->lastClosedMonth; $m++) {
                $existingAmount = $realizationsMap[$m] ?? 0;

                $rows[] = [
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
                    $sumRealization,
                    '', // notes — empty for admin to fill optionally
                    $m,
                    $existingAmount,
                ];
            }
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
                        'startColor' => ['rgb' => '7C3AED'], // purple — distinguishes from regular template
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

                // ── Data rows (3+) ──
                $highestRow = $sheet->getHighestRow();
                if ($highestRow >= 3) {
                    $sheet->getStyle("A3:N{$highestRow}")->applyFromArray([
                        'fill' => [
                            'fillType'   => 'solid',
                            'startColor' => ['rgb' => 'F5F3FF'], // light purple tint
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

                    // Number format for amount_of_rkap (J) and sum_of_uploaded_realization (K)
                    $sheet->getStyle("J3:J{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                    $sheet->getStyle("K3:K{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');

                    // amount (N) — plain integer, no thousand separators to avoid parse issues on re-import
                    $sheet->getStyle("N3:N{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('0');
                }

                // Row heights
                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->getRowDimension(2)->setRowHeight(16);

                // Column widths (matches RkapRealizationTemplateExport)
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
                $sheet->getColumnDimension('K')->setWidth(25); // sum_of_uploaded_realization
                $sheet->getColumnDimension('L')->setWidth(25); // notes
                $sheet->getColumnDimension('M')->setWidth(8);  // month
                $sheet->getColumnDimension('N')->setWidth(18); // amount

                // Comments on key columns
                $comment = $sheet->getComment('A1');
                $comment->getText()->createTextRun(
                    "budget_item_id: ID dari tabel rkap_budget_items.\n" .
                        "Harus termasuk dalam periode RKAP yang dipilih."
                );

                $monthComment = $sheet->getComment('M1');
                $monthComment->getText()->createTextRun(
                    "month: Bulan dalam angka 1–{$this->lastClosedMonth}.\n" .
                        "Template ini mencakup Januari s.d. " . $this->getMonthName($this->lastClosedMonth) . ".\n" .
                        "Jangan ubah nilai bulan, cukup ubah kolom amount."
                );

                $amountComment = $sheet->getComment('N1');
                $amountComment->getText()->createTextRun(
                    "amount: Jumlah realisasi anggaran untuk bulan tersebut.\n" .
                        "Nilai sudah diisi dari data realisasi terkini (jika ada).\n" .
                        "Ubah nilai ini sesuai koreksi yang diperlukan."
                );

                $sheet->setTitle('Mass Update Realisasi');
                $sheet->freezePane('A3');
            },
        ];
    }

    private function getMonthName(int $month): string
    {
        $names = [
            1  => 'Januari',  2  => 'Februari', 3  => 'Maret',
            4  => 'April',    5  => 'Mei',       6  => 'Juni',
            7  => 'Juli',     8  => 'Agustus',   9  => 'September',
            10 => 'Oktober',  11 => 'November',  12 => 'Desember',
        ];
        return $names[$month] ?? '';
    }
}
