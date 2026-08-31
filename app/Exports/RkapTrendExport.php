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

class RkapTrendExport implements FromArray, WithEvents, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly array $items,
        private readonly array $summary,
        private readonly string $currentPeriodTitle,
        private readonly string $proposalPeriodTitle,
        private readonly string $orgName = '-'
    ) {}

    public function array(): array
    {
        $rows = [];

        // Title Header Rows
        $rows[] = ['TREND & JUSTIFIKASI DEVIASI KEGIATAN RKAP'];
        $rows[] = ['Unit / Organisasi', $this->orgName];
        $rows[] = ['Periode Berjalan', $this->currentPeriodTitle];
        $rows[] = ['Periode Usulan', $this->proposalPeriodTitle];
        $rows[] = ['Tanggal Cetak', now()->format('d/m/Y H:i')];
        $rows[] = ['']; // Empty separator row

        // Table Header (Row 7)
        $rows[] = [
            'No',
            'Biro',
            'Kode Program',
            'Nama Program',
            'Kode Kegiatan',
            'Nama Kegiatan',
            $this->currentPeriodTitle,
            'Proyeksi ' . $this->currentPeriodTitle,
            'Deviasi (RKAP - Proyeksi)',
            'Justifikasi Deviasi Proyeksi',
            $this->proposalPeriodTitle,
            'Deviasi (Usulan - Berjalan)',
            'Justifikasi Deviasi Usulan',
            'Status Justifikasi',
            'Diperbarui Oleh',
            'Waktu Perubahan',
        ];

        $no = 1;
        foreach ($this->items as $item) {
            $rows[] = [
                $no++,
                $item['bureau_name'],
                $item['program_code'] ?? '-',
                $item['program_name'] ?? '-',
                $item['code'],
                $item['name'],
                $item['rkap_current'],
                $item['projection_current'],
                $item['dev_projection'],
                $item['justification_projection'] ?: '-',
                $item['rkap_proposed'],
                $item['dev_proposal'],
                $item['justification_proposal'] ?: '-',
                $item['is_filled'] ? 'Sudah Diisi' : 'Belum Diisi',
                $item['updater_name'] ?: '-',
                $item['updated_at'] ?: '-',
            ];
        }

        // Summary Total Row
        $rows[] = [
            'TOTAL',
            '',
            '',
            '',
            '',
            '',
            $this->summary['total_rkap_current'] ?? 0,
            $this->summary['total_proj_current'] ?? 0,
            $this->summary['total_dev_proj'] ?? 0,
            '',
            $this->summary['total_rkap_proposed'] ?? 0,
            $this->summary['total_dev_prop'] ?? 0,
            '',
            ($this->summary['filled_count'] ?? 0) . ' / ' . ($this->summary['total_activities'] ?? 0) . ' Terjustifikasi (' . ($this->summary['percentage'] ?? 0) . '%)',
            '',
            '',
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
                $lastRow = 7 + count($this->items) + 1;

                // Format number columns (G, H, I, K, L)
                $numberFormat = '#,##0.00;(#,##0.00);"-"';
                $sheet->getStyle("G8:I{$lastRow}")->getNumberFormat()->setFormatCode($numberFormat);
                $sheet->getStyle("K8:L{$lastRow}")->getNumberFormat()->setFormatCode($numberFormat);

                // Border around data table
                $sheet->getStyle("A7:P{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Highlight Total Row
                $sheet->getStyle("A{$lastRow}:P{$lastRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$lastRow}:P{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F4F8');
                $sheet->mergeCells("A{$lastRow}:F{$lastRow}");
                $sheet->getStyle("A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
