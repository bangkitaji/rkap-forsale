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

/**
 * Sheet 2: "Referensi" — lookup lists of WorkPlans, Activities, and COAs.
 * Users can reference this sheet to find the correct codes.
 */
class RkapSubmissionTemplateReferenceSheet implements FromArray, WithTitle, WithEvents, ShouldAutoSize
{
    public function title(): string
    {
        return 'Referensi';
    }

    public function array(): array
    {
        $rows = [];

        // ── Section 1: Work Plans ──
        $rows[] = ['DAFTAR PROGRAM KERJA (WORK PLAN)', '', ''];
        $rows[] = ['Kode', 'Nama Program Kerja', 'Status'];

        $workPlans = WorkPlan::where('approval_status', 'approved')
            ->orderBy('code')
            ->get();

        foreach ($workPlans as $wp) {
            $rows[] = [$wp->code, $wp->title, $wp->approval_status];
        }

        // Separator
        $rows[] = ['', '', ''];
        $rows[] = ['', '', ''];

        // ── Section 2: Activities ──
        $rows[] = ['DAFTAR KEGIATAN (ACTIVITY)', '', '', ''];
        $actHeaderRow = count($rows);
        $rows[] = ['Kode Work Plan', 'Kode Kegiatan', 'Nama Kegiatan', 'Status'];

        $activities = Activity::where('approval_status', 'approved')
            ->with('workPlan')
            ->orderBy('work_plan_id')
            ->orderBy('code')
            ->get();

        foreach ($activities as $act) {
            $rows[] = [
                $act->workPlan->code ?? '-',
                $act->code,
                $act->title,
                $act->approval_status,
            ];
        }

        // Separator
        $rows[] = ['', '', '', ''];
        $rows[] = ['', '', '', ''];

        // ── Section 3: COAs ──
        $rows[] = ['DAFTAR COA (CHART OF ACCOUNTS)', '', ''];
        $coaHeaderRow = count($rows);
        $rows[] = ['Kode COA', 'Nama COA', 'Deskripsi'];

        $coas = Coa::orderBy('code')->get();

        foreach ($coas as $coa) {
            $rows[] = [$coa->code, $coa->title, $coa->description ?? ''];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Style section title rows
                $data = $this->array();
                $sectionTitleRows = [];
                $sectionHeaderRows = [];

                $row = 1;
                foreach ($data as $line) {
                    $firstCell = $line[0] ?? '';
                    if (str_starts_with($firstCell, 'DAFTAR ')) {
                        $sectionTitleRows[] = $row;
                    }
                    if (in_array($firstCell, ['Kode', 'Kode Work Plan', 'Kode COA'])) {
                        $sectionHeaderRows[] = $row;
                    }
                    $row++;
                }

                // Style section titles
                foreach ($sectionTitleRows as $r) {
                    $sheet->getStyle("A{$r}:D{$r}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 13,
                            'color' => ['argb' => 'FF2F5496'],
                        ],
                    ]);
                    $sheet->mergeCells("A{$r}:D{$r}");
                }

                // Style section headers
                foreach ($sectionHeaderRows as $r) {
                    $sheet->getStyle("A{$r}:D{$r}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['argb' => 'FFFFFFFF'],
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FF4472C4'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FFD9D9D9'],
                            ],
                        ],
                    ]);
                }

                // Set column widths
                $sheet->getColumnDimension('A')->setWidth(22);
                $sheet->getColumnDimension('B')->setWidth(35);
                $sheet->getColumnDimension('C')->setWidth(35);
                $sheet->getColumnDimension('D')->setWidth(15);
            },
        ];
    }
}
