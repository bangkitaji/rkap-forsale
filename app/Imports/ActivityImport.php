<?php

namespace App\Imports;

use App\Models\Activity;
use App\Models\WorkPlan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ActivityImport implements ToCollection, WithHeadingRow, WithValidation
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
        if (empty($row['work_plan_code']) && empty($row['code'])) {
          continue;
        }

        // Validate required fields
        if (empty($row['work_plan_code']) || empty($row['code']) || empty($row['title'])) {
          $this->results['failed']++;
          $this->results['errors'][] = "Row " . ($rowIndex + 2) . ": Work Plan Code, Code, and Title are required.";
          continue;
        }

        // Find WorkPlan by code
        $workPlan = WorkPlan::where('code', trim($row['work_plan_code']))->first();
        if (!$workPlan) {
          $this->results['failed']++;
          $this->results['errors'][] = "Row " . ($rowIndex + 2) . ": Work Plan with code '" . $row['work_plan_code'] . "' not found.";
          continue;
        }

        // Create or update Activity
        $activity = Activity::updateOrCreate(
          [
            'work_plan_id' => $workPlan->id,
            'code' => trim($row['code']),
          ],
          [
            'title' => trim($row['title']),
            'description' => isset($row['description']) ? trim($row['description']) : null,
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
      'work_plan_code' => 'required|max:255',
      'code' => 'required|max:255',
      'title' => 'required|string|max:255',
      'description' => 'nullable|string',
    ];
  }

  public function getResults()
  {
    return $this->results;
  }
}
