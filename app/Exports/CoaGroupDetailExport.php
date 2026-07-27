<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CoaGroupDetailExport implements FromArray, WithHeadings, WithEvents, ShouldAutoSize
{
    private $data;
    private $coaGroupName;
    
    public function __construct(array $data, string $coaGroupName = '')
    {
        $this->data = $data;
        $this->coaGroupName = $coaGroupName;
    }

    public function array(): array
    {
        $exportData = [];
        foreach ($this->data as $row) {
            $exportData[] = [
                $row->coa_code . ' - ' . $row->coa_title,
                $row->program_name,
                $row->directorate_code,
                $row->department_code,
                $row->bureau_code,
                $row->budget,
                $row->realization,
                $row->projection
            ];
        }
        return $exportData;
    }

    public function headings(): array
    {
        return [
            ['Detail Category / Group: ' . $this->coaGroupName],
            [
                'COA',
                'Kegiatan / Program',
                'Dir',
                'Dept',
                'Biro',
                'Anggaran',
                'Realisasi YTD',
                'Proyeksi'
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = count($this->data) + 2;
                
                // Merge title
                $sheet->mergeCells('A1:H1');
                
                $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ];
                
                $headerStyle = [
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D9E1F2']
                    ]
                ];

                $sheet->getStyle('A2:H2')->applyFromArray($headerStyle);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2:H' . $lastRow)->applyFromArray($styleArray);
                
                // Format numbers
                $sheet->getStyle('F3:H' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
            },
        ];
    }
}
