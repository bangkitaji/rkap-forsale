<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RkapTrendSummaryExport implements FromArray, WithEvents, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly array $summaryData,
        private readonly string $currentPeriodTitle,
        private readonly string $proposalPeriodTitle,
        private readonly string $orgName = '-'
    ) {}

    public function array(): array
    {
        $rows = [];

        // Title Header Rows
        $rows[] = ['LAPORAN PROGRES PENGISIAN JUSTIFIKASI TREND RKAP'];
        $rows[] = ['Unit / Organisasi', $this->orgName];
        $rows[] = ['Periode Berjalan', $this->currentPeriodTitle];
        $rows[] = ['Periode Usulan', $this->proposalPeriodTitle];
        $rows[] = ['Tanggal Cetak', now()->format('d/m/Y H:i')];
        $rows[] = ['']; // Empty separator row

        // Table Header (Row 7)
        $rows[] = [
            'No',
            'Direktorat',
            'Departemen',
            'Kode Biro',
            'Nama Biro (Unit Kerja)',
            'Jumlah Kegiatan',
            'Terjustifikasi',
            'Belum Terjustifikasi',
            'Persentase Progres',
            'Status',
        ];

        $no = 1;
        $summaryRows = $this->summaryData['rows'] ?? [];
        foreach ($summaryRows as $row) {
            $rows[] = [
                $no++,
                $row['directorate'] ?? '-',
                $row['department'] ?? '-',
                $row['bureau_code'] ?? '-',
                $row['bureau_name'] ?? '-',
                $row['total_activities'] ?? 0,
                $row['filled_activities'] ?? 0,
                $row['unfilled_activities'] ?? 0,
                ($row['percentage'] ?? 0) . '%',
                $row['status'] ?? '-',
            ];
        }

        // Summary Total Row
        $stats = $this->summaryData['stats'] ?? [];
        $rows[] = [
            'TOTAL',
            '',
            '',
            '',
            '',
            $stats['total_activities'] ?? 0,
            $stats['filled_activities'] ?? 0,
            $stats['unfilled_activities'] ?? 0,
            ($stats['percentage'] ?? 0) . '%',
            ($stats['filled_activities'] ?? 0) . ' / ' . ($stats['total_activities'] ?? 0) . ' Terjustifikasi (' . ($stats['percentage'] ?? 0) . '%)',
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            7 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF696CFF'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowCount = count($this->summaryData['rows'] ?? []);
                $lastRow = 7 + $rowCount + 1;

                // Format number columns (F, G, H)
                $numberFormat = '#,##0;(#,##0);"-"';
                $sheet->getStyle("F8:H{$lastRow}")->getNumberFormat()->setFormatCode($numberFormat);

                // Center align No, Kode Biro, Persentase, Status
                $sheet->getStyle("A8:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D8:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("I8:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Border around data table
                $sheet->getStyle("A7:J{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Highlight Total Row
                $sheet->getStyle("A{$lastRow}:J{$lastRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$lastRow}:J{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F4F8');
                $sheet->mergeCells("A{$lastRow}:E{$lastRow}");
                $sheet->getStyle("A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
