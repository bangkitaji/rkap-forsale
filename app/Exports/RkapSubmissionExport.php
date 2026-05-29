<?php

namespace App\Exports;

use App\Models\RkapSubmission;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class RkapSubmissionExport implements FromArray, WithEvents, ShouldAutoSize
{
  public function __construct(private readonly RkapSubmission $submission) {}

  public function array(): array
  {
    $rows = [];
    $rows[] = array_merge(
      ['COA SAP', 'COA SAP Desc', 'Dept', 'Biro', 'Kode Program kerja', 'Program Kerja', 'Kode Program Kegiatan', 'Nama Kegiatan'],
      ['Budget', '', '', '', '', '', '', '', '', '', '', '', ''],
      ['Kas Keluar', '', '', '', '', '', '', '', '', '', '', '', '']
    );

    $rows[] = array_merge(
      ['', '', '', '', '', '', '', ''],
      $this->monthHeaders('Budget Month'),
      $this->monthHeaders('Kas Keluar Month')
    );

    $this->submission->load([
      'bureau.department',
      'workPlans.activity',
      'workPlans.workPlan',
      'workPlans.budgetItems.monthlies',
      'workPlans.budgetItems.cashOuts',
    ]);

    foreach ($this->submission->workPlans as $wp) {
      foreach ($wp->budgetItems as $bi) {
        $budgetByMonth = array_fill(1, 12, 0);
        foreach ($bi->monthlies as $monthly) {
          $budgetByMonth[(int) $monthly->month] = (float) $monthly->amount;
        }

        $cashOutByMonth = array_fill(1, 12, 0);
        foreach ($bi->cashOuts as $cashOut) {
          $cashOutByMonth[(int) $cashOut->month] = (float) $cashOut->amount;
        }

        $budgetTotal = array_sum($budgetByMonth);
        $cashOutTotal = array_sum($cashOutByMonth);

        $rows[] = array_merge(
          [
            $bi->account_code ?? '',
            $bi->description ?? '',
            $this->submission->bureau?->department?->name ?? '',
            $this->submission->bureau?->name ?? '',
            $wp->workPlan?->code ?? '',
            $wp->workPlan?->title ?? ($wp->program_name ?? ''),
            $wp->activity?->code ?? '',
            $wp->activity?->title ?? '',
          ],
          [
            $budgetByMonth[1],
            $budgetByMonth[2],
            $budgetByMonth[3],
            $budgetByMonth[4],
            $budgetByMonth[5],
            $budgetByMonth[6],
            $budgetByMonth[7],
            $budgetByMonth[8],
            $budgetByMonth[9],
            $budgetByMonth[10],
            $budgetByMonth[11],
            $budgetByMonth[12],
            $budgetTotal,
          ],
          [
            $cashOutByMonth[1],
            $cashOutByMonth[2],
            $cashOutByMonth[3],
            $cashOutByMonth[4],
            $cashOutByMonth[5],
            $cashOutByMonth[6],
            $cashOutByMonth[7],
            $cashOutByMonth[8],
            $cashOutByMonth[9],
            $cashOutByMonth[10],
            $cashOutByMonth[11],
            $cashOutByMonth[12],
            $cashOutTotal,
          ]
        );
      }
    }

    return $rows;
  }

  public function registerEvents(): array
  {
    return [
      AfterSheet::class => function (AfterSheet $event): void {
        $sheet = $event->sheet->getDelegate();

        // Vertical merge for first 8 headers (A-H)
        foreach (range('A', 'H') as $col) {
          $sheet->mergeCells("{$col}1:{$col}2");
        }

        // Group merge for Budget (I-U) and Kas Keluar (V-AH)
        $sheet->mergeCells('I1:U1');
        $sheet->mergeCells('V1:AH1');

        // Header styles
        $sheet->getStyle('A1:AH2')->applyFromArray([
          'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
          ],
          'fill' => [
            'fillType' => 'solid',
            'startColor' => ['rgb' => 'C00000'],
          ],
          'alignment' => [
            'horizontal' => 'center',
            'vertical' => 'center',
            'wrapText' => true,
          ],
          'borders' => [
            'allBorders' => [
              'borderStyle' => 'thin',
              'color' => ['rgb' => '000000'],
            ],
          ],
        ]);

        // Data styles
        $highestRow = $sheet->getHighestRow();
        if ($highestRow >= 3) {
          $sheet->getStyle("A3:AH{$highestRow}")->applyFromArray([
            'borders' => [
              'allBorders' => [
                'borderStyle' => 'thin',
                'color' => ['rgb' => 'D9D9D9'],
              ],
            ],
            'alignment' => [
              'vertical' => 'top',
            ],
          ]);

          // Number format for Budget + Kas Keluar months and totals
          $sheet->getStyle("I3:AH{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');
        }

        // Freeze header
        $sheet->freezePane('A3');
      },
    ];
  }

  private function monthHeaders(string $prefix): array
  {
    return [
      "{$prefix} 1",
      "{$prefix} 2",
      "{$prefix} 3",
      "{$prefix} 4",
      "{$prefix} 5",
      "{$prefix} 6",
      "{$prefix} 7",
      "{$prefix} 8",
      "{$prefix} 9",
      "{$prefix} 10",
      "{$prefix} 11",
      "{$prefix} 12",
      str_contains($prefix, 'Kas Keluar') ? 'Kas Keluar Total' : 'Budget Total',
    ];
  }
}
