<?php

namespace App\Imports;

use App\Models\WorkPlan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class WorkPlanImport implements ToCollection, WithHeadingRow, WithValidation
{
  private $results = [
    'success' => 0,
    'failed' => 0,
    'errors' => [],
  ];

  public function collection(Collection $rows)
  {
    foreach ($rows as $rowIndex => $row) {
      try {
        // Skip empty rows
        if (empty($row['code']) && empty($row['title'])) {
          continue;
        }

        // Validate required fields
        if (empty($row['code']) || empty($row['title'])) {
          $this->results['failed']++;
          $this->results['errors'][] = "Row " . ($rowIndex + 2) . ": Code and Title are required.";
          continue;
        }

        // Create or update WorkPlan
        $workPlan = WorkPlan::updateOrCreate(
          ['code' => trim($row['code'])],
          [
            'title' => trim($row['title']),
          ]
        );

        $this->results['success']++;
      } catch (\Exception $e) {
        $this->results['failed']++;
        $this->results['errors'][] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
      }
    }
  }

  public function rules(): array
  {
    return [
      'code' => 'required|max:255',
      'title' => 'required|string|max:255',
    ];
  }

  public function getResults()
  {
    return $this->results;
  }
}
