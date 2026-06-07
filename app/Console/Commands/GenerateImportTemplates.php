<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateImportTemplates extends Command
{
  protected $signature = 'generate:import-templates';
  protected $description = 'Generate Excel import templates for WorkPlan, Activity, COA, and Activity-COA Mapping';

  public function handle()
  {
    $this->generateWorkPlanTemplate();
    $this->generateActivityTemplate();
    $this->generateCoaTemplate();
    $this->generateActivityCoaMappingTemplate();

    $this->info('Templates generated successfully!');
  }

  private function generateWorkPlanTemplate()
  {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Work Plans');

    // Set headers
    $headers = ['Code', 'Title'];
    foreach ($headers as $col => $header) {
      $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
      $cell->setValue($header);
      $cell->getStyle()->getFont()->setBold(true);
      $cell->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
      $cell->getStyle()->getFill()->getStartColor()->setARGB('FF4472C4');
      $cell->getStyle()->getFont()->getColor()->setARGB('FFFFFFFF');
      $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // Add sample data
    $sheet->setCellValue('A2', 'WP001');
    $sheet->setCellValue('B2', 'Strategic Planning');
    $sheet->setCellValue('A3', 'WP002');
    $sheet->setCellValue('B3', 'Budget Review');

    // Set column width
    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(30);

    // Add instructions sheet
    $instructionSheet = $spreadsheet->createSheet();
    $instructionSheet->setTitle('Instructions');
    $instructionSheet->setCellValue('A1', 'Import Instructions for Work Plan');
    $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

    $instructions = [
      '',
      'How to use this template:',
      '1. Fill in the Code column with a unique identifier for the work plan',
      '2. Fill in the Title column with the work plan name',
      '3. Do not change the header row',
      '4. Each row will be imported as a new work plan or update existing one',
      '',
      'Column Requirements:',
      '- Code: Required, must be unique, max 255 characters',
      '- Title: Required, max 255 characters',
      '',
      'Notes:',
      '- If the code already exists, the record will be updated',
      '- Empty rows will be skipped',
      '- Use the exact column headers as shown in the template',
    ];

    $row = 3;
    foreach ($instructions as $instruction) {
      $instructionSheet->setCellValue('A' . $row, $instruction);
      $row++;
    }

    $instructionSheet->getColumnDimension('A')->setWidth(80);

    // Save file
    $path = public_path('templates');
    if (!is_dir($path)) {
      mkdir($path, 0755, true);
    }

    $writer = new Xlsx($spreadsheet);
    $writer->save($path . '/workplan_template.xlsx');
  }

  private function generateActivityTemplate()
  {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Activities');

    // Set headers
    $headers = ['Work Plan Code', 'Code', 'Title', 'Description'];
    foreach ($headers as $col => $header) {
      $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
      $cell->setValue($header);
      $cell->getStyle()->getFont()->setBold(true);
      $cell->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
      $cell->getStyle()->getFill()->getStartColor()->setARGB('FF70AD47');
      $cell->getStyle()->getFont()->getColor()->setARGB('FFFFFFFF');
      $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // Add sample data
    $sheet->setCellValue('A2', 'WP001');
    $sheet->setCellValue('B2', 'ACT001');
    $sheet->setCellValue('C2', 'Initial Planning');
    $sheet->setCellValue('D2', 'Plan the initial phase of the project');

    $sheet->setCellValue('A3', 'WP001');
    $sheet->setCellValue('B3', 'ACT002');
    $sheet->setCellValue('C3', 'Team Formation');
    $sheet->setCellValue('D3', 'Form the project team');

    // Set column width
    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(25);
    $sheet->getColumnDimension('D')->setWidth(35);

    // Add instructions sheet
    $instructionSheet = $spreadsheet->createSheet();
    $instructionSheet->setTitle('Instructions');
    $instructionSheet->setCellValue('A1', 'Import Instructions for Activity');
    $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

    $instructions = [
      '',
      'How to use this template:',
      '1. Fill in the Work Plan Code with the code of an existing work plan',
      '2. Fill in the Code column with a unique identifier for the activity',
      '3. Fill in the Title column with the activity name',
      '4. Fill in the Description column (optional)',
      '5. Do not change the header row',
      '6. Each row will be imported as a new activity or update existing one',
      '',
      'Column Requirements:',
      '- Work Plan Code: Required, must match an existing work plan code',
      '- Code: Required, must be unique per work plan, max 255 characters',
      '- Title: Required, max 255 characters',
      '- Description: Optional, any text',
      '',
      'Notes:',
      '- If the code already exists for the work plan, the record will be updated',
      '- Empty rows will be skipped',
      '- Use the exact column headers as shown in the template',
      '- Work Plan Code must exist before importing activities',
    ];

    $row = 3;
    foreach ($instructions as $instruction) {
      $instructionSheet->setCellValue('A' . $row, $instruction);
      $row++;
    }

    $instructionSheet->getColumnDimension('A')->setWidth(80);

    // Save file
    $path = public_path('templates');
    if (!is_dir($path)) {
      mkdir($path, 0755, true);
    }

    $writer = new Xlsx($spreadsheet);
    $writer->save($path . '/activity_template.xlsx');
  }

  private function generateCoaTemplate()
  {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('COAs');

    // Set headers
    $headers = ['Code', 'Title', 'Description'];
    foreach ($headers as $col => $header) {
      $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
      $cell->setValue($header);
      $cell->getStyle()->getFont()->setBold(true);
      $cell->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
      $cell->getStyle()->getFill()->getStartColor()->setARGB('FF7030A0');
      $cell->getStyle()->getFont()->getColor()->setARGB('FFFFFFFF');
      $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // Add sample data
    $sheet->setCellValue('A2', 'COA001');
    $sheet->setCellValue('B2', 'Personnel Costs');
    $sheet->setCellValue('C2', 'Salaries, wages, and benefits');

    $sheet->setCellValue('A3', 'COA002');
    $sheet->setCellValue('B3', 'Operating Expenses');
    $sheet->setCellValue('C3', 'Utilities, supplies, and maintenance');

    // Set column width
    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(40);

    // Add instructions sheet
    $instructionSheet = $spreadsheet->createSheet();
    $instructionSheet->setTitle('Instructions');
    $instructionSheet->setCellValue('A1', 'Import Instructions for COA (Chart of Accounts)');
    $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

    $instructions = [
      '',
      'How to use this template:',
      '1. Fill in the Code column with a unique identifier for the COA',
      '2. Fill in the Title column with the COA name',
      '3. Fill in the Description column (optional) with additional details',
      '4. Do not change the header row',
      '5. Each row will be imported as a new COA or update existing one',
      '',
      'Column Requirements:',
      '- Code: Required, must be unique, max 255 characters',
      '- Title: Required, max 255 characters',
      '- Description: Optional, any text',
      '',
      'Notes:',
      '- If the code already exists, the record will be updated',
      '- Empty rows will be skipped',
      '- Use the exact column headers as shown in the template',
    ];

    $row = 3;
    foreach ($instructions as $instruction) {
      $instructionSheet->setCellValue('A' . $row, $instruction);
      $row++;
    }

    $instructionSheet->getColumnDimension('A')->setWidth(80);

    // Save file
    $path = public_path('templates');
    if (!is_dir($path)) {
      mkdir($path, 0755, true);
    }

    $writer = new Xlsx($spreadsheet);
    $writer->save($path . '/coa_template.xlsx');
  }

  private function generateActivityCoaMappingTemplate()
  {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Mappings');

    // Set headers
    $headers = ['Activity Code', 'COA Code'];
    foreach ($headers as $col => $header) {
      $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
      $cell->setValue($header);
      $cell->getStyle()->getFont()->setBold(true);
      $cell->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
      $cell->getStyle()->getFill()->getStartColor()->setARGB('FF00B050');
      $cell->getStyle()->getFont()->getColor()->setARGB('FFFFFFFF');
      $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // Add sample data
    $sheet->setCellValue('A2', 'ACT001');
    $sheet->setCellValue('B2', 'COA001');

    $sheet->setCellValue('A3', 'ACT001');
    $sheet->setCellValue('B3', 'COA002');

    $sheet->setCellValue('A4', 'ACT002');
    $sheet->setCellValue('B4', 'COA001');

    // Set column width
    $sheet->getColumnDimension('A')->setWidth(25);
    $sheet->getColumnDimension('B')->setWidth(25);

    // Add instructions sheet
    $instructionSheet = $spreadsheet->createSheet();
    $instructionSheet->setTitle('Instructions');
    $instructionSheet->setCellValue('A1', 'Import Instructions for Activity-COA Mapping');
    $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

    $instructions = [
      '',
      'How to use this template:',
      '1. Fill in the Activity Code column with an existing activity code',
      '2. Fill in the COA Code column with an existing COA code',
      '3. Each row represents a link between an activity and a COA',
      '4. One activity can be mapped to multiple COAs',
      '5. Do not change the header row',
      '',
      'Column Requirements:',
      '- Activity Code: Required, must match an existing activity code',
      '- COA Code: Required, must match an existing COA code',
      '',
      'Notes:',
      '- Both Activity Code and COA Code must exist in the system',
      '- Duplicate mappings will be automatically skipped',
      '- Empty rows will be skipped',
      '- Use the exact column headers as shown in the template',
      '',
      'Example:',
      'If ACT001 should be linked to both COA001 and COA002, create two rows:',
      '  Row 1: ACT001, COA001',
      '  Row 2: ACT001, COA002',
    ];

    $row = 3;
    foreach ($instructions as $instruction) {
      $instructionSheet->setCellValue('A' . $row, $instruction);
      $row++;
    }

    $instructionSheet->getColumnDimension('A')->setWidth(80);

    // Save file
    $path = public_path('templates');
    if (!is_dir($path)) {
      mkdir($path, 0755, true);
    }

    $writer = new Xlsx($spreadsheet);
    $writer->save($path . '/activity_coa_mapping_template.xlsx');
  }
}
