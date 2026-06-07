<?php

namespace App\Imports;

use App\Models\Activity;
use App\Models\Coa;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ActivityCoaMappingImport implements ToCollection, WithHeadingRow, WithValidation
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
        if (empty($row['activity_code']) && empty($row['coa_code'])) {
          continue;
        }

        // Validate required fields
        if (empty($row['activity_code']) || empty($row['coa_code'])) {
          $this->results['failed']++;
          $this->results['errors'][] = "Row " . ($rowIndex + 2) . ": Activity Code and COA Code are required.";
          continue;
        }

        // Find Activity by code
        $activity = Activity::where('code', trim($row['activity_code']))->first();
        if (!$activity) {
          $this->results['failed']++;
          $this->results['errors'][] = "Row " . ($rowIndex + 2) . ": Activity with code '" . $row['activity_code'] . "' not found.";
          continue;
        }

        // Find COA by code
        $coa = Coa::where('code', trim($row['coa_code']))->first();
        if (!$coa) {
          $this->results['failed']++;
          $this->results['errors'][] = "Row " . ($rowIndex + 2) . ": COA with code '" . $row['coa_code'] . "' not found.";
          continue;
        }

        // Attach COA to Activity (will not duplicate if already attached)
        $activity->coas()->syncWithoutDetaching([$coa->id]);

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
      'activity_code' => 'required|max:255',
      'coa_code' => 'required|max:255',
    ];
  }

  public function getResults()
  {
    return $this->results;
  }
}
