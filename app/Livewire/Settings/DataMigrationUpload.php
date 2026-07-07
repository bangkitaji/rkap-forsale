<?php

namespace App\Livewire\Settings;

use App\Models\Activity;
use App\Models\Bureau;
use App\Models\Coa;
use App\Models\RkapBudgetItem;
use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\User;
use App\Models\WorkPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DataMigrationUpload extends Component
{
  use WithFileUploads;

  public $file;

  public array $errorsList = [];
  public array $importSummary = [];
  public bool $imported = false;

  private array $rkapPeriodCache = [];
  private array $workPlanCache = [];
  private array $activityCache = [];
  private array $coaCache = [];
  private array $bureauCache = [];

  private array $workPlanModels = [];
  private array $activityModels = [];
  private array $coaModels = [];
  private array $userCache = [];

  private array $requiredColumns = [
    'submission_key',
    'title',
    'bureau_code',
    'created_by',
    'work_plan_key',
    'work_plan_code',
    'budget_item_key',
    'coa_code',
    'bi_quantity',
    'unit_price',
  ];

  public function uploadAndImport(): void
  {
    // Prevent timeouts and memory exhaustion during large file migrations
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');

    $this->resetState();

    $this->validate([
      'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
    ]);

    $parsed = $this->parseFile($this->file->getRealPath(), $this->file->getClientOriginalExtension());

    if (empty($parsed)) {
      $this->errorsList[] = 'File kosong atau tidak valid.';
      return;
    }

    [$headers, $dataRows] = $parsed;

    $missingColumns = array_values(array_diff($this->requiredColumns, $headers));
    if (!empty($missingColumns)) {
      $this->errorsList[] = 'Kolom wajib tidak ditemukan: ' . implode(', ', $missingColumns);
      return;
    }

    $normalizedRows = $this->normalizeRows($headers, $dataRows);
    $this->validateRows($normalizedRows);

    if (!empty($this->errorsList)) {
      return;
    }

    $this->importRows($normalizedRows);
  }

  private function resetState(): void
  {
    $this->errorsList = [];
    $this->importSummary = [];
    $this->imported = false;
    $this->rkapPeriodCache = [];
    $this->workPlanCache = [];
    $this->activityCache = [];
    $this->coaCache = [];
    $this->bureauCache = [];
    $this->workPlanModels = [];
    $this->activityModels = [];
    $this->coaModels = [];
    $this->userCache = [];
  }

  /**
   * Route to the correct parser based on file extension.
   */
  private function parseFile(string $filePath, string $extension): array
  {
    $ext = strtolower($extension);

    if (in_array($ext, ['xlsx', 'xls'])) {
      return $this->parseExcel($filePath);
    }

    return $this->parseCsv($filePath);
  }

  /**
   * Parse an Excel (.xlsx / .xls) file using PhpSpreadsheet.
   */
  private function parseExcel(string $filePath): array
  {
    try {
      $spreadsheet = IOFactory::load($filePath);
      $worksheet = $spreadsheet->getActiveSheet();
      $rows = $worksheet->toArray(null, true, true, false);

      if (empty($rows)) {
        return [];
      }

      $headers = array_map(static fn($h) => trim((string) ($h ?? '')), array_shift($rows));

      $dataRows = [];
      foreach ($rows as $row) {
        $stringRow = array_map(static fn($v) => (string) ($v ?? ''), $row);
        if (count(array_filter($stringRow, static fn($v) => trim($v) !== '')) === 0) {
          continue;
        }
        $dataRows[] = $stringRow;
      }

      return [$headers, $dataRows];
    } catch (\Throwable $e) {
      $this->errorsList[] = 'Gagal membaca file Excel: ' . $e->getMessage();
      return [];
    }
  }

  private function parseCsv(string $filePath): array
  {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
      return [];
    }

    $headers = fgetcsv($handle);
    if (!$headers) {
      fclose($handle);
      return [];
    }

    $headers = array_map(static fn($h) => trim((string) $h), $headers);

    $dataRows = [];
    while (($row = fgetcsv($handle)) !== false) {
      if (count(array_filter($row, static fn($v) => trim((string) $v) !== '')) === 0) {
        continue;
      }
      $dataRows[] = $row;
    }

    fclose($handle);

    return [$headers, $dataRows];
  }

  private function normalizeRows(array $headers, array $rows): array
  {
    $normalized = [];

    foreach ($rows as $index => $row) {
      $assoc = [];
      foreach ($headers as $i => $header) {
        $assoc[$header] = isset($row[$i]) ? trim((string) $row[$i]) : null;
      }
      $assoc['_row_number'] = $index + 2;
      $normalized[] = $assoc;
    }

    return $normalized;
  }

  private function validateRows(array $rows): void
  {
    foreach ($rows as $row) {
      $rowNo = $row['_row_number'];

      foreach ($this->requiredColumns as $col) {
        if (!isset($row[$col]) || $row[$col] === '') {
          $this->errorsList[] = "Baris {$rowNo}: kolom {$col} wajib diisi.";
        }
      }

      if (!$this->resolveRkapPeriodId($row['title'] ?? null)) {
        $this->errorsList[] = "Baris {$rowNo}: title (RKAP Period) tidak ditemukan.";
      }

      if (!$this->resolveBureauId($row['bureau_code'] ?? null)) {
        $this->errorsList[] = "Baris {$rowNo}: bureau_code tidak ditemukan.";
      }

      if (!$this->existsUser($row['created_by'] ?? null)) {
        $this->errorsList[] = "Baris {$rowNo}: created_by (user) tidak ditemukan.";
      }

      if (!$this->resolveWorkPlanId($row['work_plan_code'] ?? null)) {
        $this->errorsList[] = "Baris {$rowNo}: work_plan_code tidak ditemukan.";
      }

      if (!empty($row['activity_code']) && !$this->resolveActivityId($row['activity_code'])) {
        $this->errorsList[] = "Baris {$rowNo}: activity_code tidak ditemukan.";
      }

      if (!$this->resolveCoaId($row['coa_code'] ?? null)) {
        $this->errorsList[] = "Baris {$rowNo}: coa_code tidak ditemukan.";
      }

      $qty = (float) ($row['bi_quantity'] ?? 0);
      $unitPrice = (float) ($row['unit_price'] ?? 0);
      $total = $qty * $unitPrice;

      if ($qty < 1) {
        $this->errorsList[] = "Baris {$rowNo}: bi_quantity minimal 1.";
      }

      if ($unitPrice < 0) {
        $this->errorsList[] = "Baris {$rowNo}: unit_price tidak boleh negatif.";
      }

      $monthlyTotal = 0;
      for ($m = 1; $m <= 12; $m++) {
        $monthlyTotal += (float) ($row["m{$m}"] ?? 0);
      }

      if (abs($monthlyTotal - $total) > 0.01) {
        $this->errorsList[] = "Baris {$rowNo}: total distribusi bulanan (m1..m12) harus sama dengan total item.";
      }

      $cashOutTotal = 0;
      for ($m = 1; $m <= 12; $m++) {
        $cashOutTotal += (float) ($row["co{$m}"] ?? 0);
      }

      if ($cashOutTotal <= 0) {
        $this->errorsList[] = "Baris {$rowNo}: total cash out (co1..co12) harus > 0.";
      }

      if ($cashOutTotal - $total > 0.01) {
        $this->errorsList[] = "Baris {$rowNo}: total cash out (co1..co12) tidak boleh melebihi total item.";
      }
    }
  }

  private function importRows(array $rows): void
  {
    DB::transaction(function () use ($rows): void {
      $submissionMap = [];
      $workPlanMap = [];
      $budgetItemMap = [];

      $createdSubmissions = 0;
      $createdWorkPlans = 0;
      $createdBudgetItems = 0;
      $createdMonthlies = 0;
      $createdCashOuts = 0;

      foreach ($rows as $row) {
        $submissionKey = $row['submission_key'];
        $periodId = $this->resolveRkapPeriodId($row['title']);
        $bureauId = $this->resolveBureauId($row['bureau_code']);

        // Use composite submission key to differentiate submissions by period and bureau in case keys are reused
        $compositeSubmissionKey = $submissionKey . '::' . $periodId . '::' . $bureauId;

        if (!isset($submissionMap[$compositeSubmissionKey])) {
          $submission = RkapSubmission::firstOrCreate(
            [
              'rkap_period_id' => $periodId,
              'bureau_id' => $bureauId,
            ],
            [
              'created_by' => (int) $row['created_by'],
              'status' => ($row['status'] ?? null) ?: 'draft',
              'current_version' => (int) (($row['current_version'] ?? null) ?: 1),
              'notes' => ($row['notes'] ?? null) ?: null,
            ]
          );

          $submissionMap[$compositeSubmissionKey] = $submission;

          // Only count genuinely new submissions
          if ($submission->wasRecentlyCreated) {
            $createdSubmissions++;
          }
        }

        /** @var \App\Models\RkapSubmission $submission */
        $submission = $submissionMap[$compositeSubmissionKey];
        
        $wpId = $this->resolveWorkPlanId($row['work_plan_code']);
        $actId = !empty($row['activity_code']) ? $this->resolveActivityId($row['activity_code']) : null;
        $compositeWpKey = $compositeSubmissionKey . '::' . $wpId . '::' . ($actId ?? 'null');

        if (!isset($workPlanMap[$compositeWpKey])) {
          $workPlan = RkapWorkPlan::where('rkap_submission_id', $submission->id)
            ->where('work_plan_id', $wpId)
            ->where('activity_id', $actId)
            ->first();

          if (!$workPlan) {
            $masterWorkPlan = $wpId ? ($this->workPlanModels[$wpId] ?? WorkPlan::withTrashed()->find($wpId)) : null;
            $masterActivity = $actId ? ($this->activityModels[$actId] ?? Activity::withTrashed()->find($actId)) : null;

            $workPlan = RkapWorkPlan::create([
              'rkap_submission_id' => $submission->id,
              'work_plan_id' => $wpId,
              'activity_id' => $actId,
              'program_code' => $masterActivity?->code ?? $masterWorkPlan?->code ?? '-',
              'program_name' => $masterActivity?->title ?? $masterWorkPlan?->title ?? 'Tanpa Nama',
              'description' => ($row['wp_description'] ?? null) ?: null,
              'output_target' => ($row['output_target'] ?? null) ?: null,
              'unit' => ($row['wp_unit'] ?? null) ?: null,
              'quantity' => (int) (($row['wp_quantity'] ?? null) ?: 1),
              'sort_order' => (int) (($row['sort_order'] ?? null) ?: 0),
            ]);
            $createdWorkPlans++;
          }

          $workPlanMap[$compositeWpKey] = $workPlan;
        }

        /** @var \App\Models\RkapWorkPlan $workPlan */
        $workPlan = $workPlanMap[$compositeWpKey];
        
        $coaId = $this->resolveCoaId($row['coa_code']);
        $coa = $coaId ? ($this->coaModels[$coaId] ?? Coa::withTrashed()->find($coaId)) : null;
        $compositeBiKey = $compositeWpKey . '::' . $coaId;

        if (!isset($budgetItemMap[$compositeBiKey])) {
          $budgetItem = RkapBudgetItem::with(['monthlies', 'cashOuts'])
            ->where('rkap_work_plan_id', $workPlan->id)
            ->where('account_code', $coa?->code)
            ->first();

          if (!$budgetItem) {
            $budgetItem = RkapBudgetItem::create([
              'rkap_work_plan_id' => $workPlan->id,
              'account_code' => $coa?->code,
              'description' => $coa?->title ?? '',
              'unit' => ($row['bi_unit'] ?? null) ?: null,
              'quantity' => (int) $row['bi_quantity'],
              'unit_price' => (float) $row['unit_price'],
              'remarks' => ($row['remarks'] ?? null) ?: null,
            ]);
            $budgetItem->setRelation('monthlies', collect());
            $budgetItem->setRelation('cashOuts', collect());
            $createdBudgetItems++;
          }
          $budgetItemMap[$compositeBiKey] = $budgetItem;
        } else {
          /** @var \App\Models\RkapBudgetItem $budgetItem */
          $budgetItem = $budgetItemMap[$compositeBiKey];

          $oldQty = $budgetItem->quantity;
          $oldUnitPrice = $budgetItem->unit_price;
          $oldTotal = $oldQty * $oldUnitPrice;

          $rowQty = (int) $row['bi_quantity'];
          $rowUnitPrice = (float) $row['unit_price'];
          $rowTotal = $rowQty * $rowUnitPrice;

          $newQty = $oldQty + $rowQty;
          $newTotal = $oldTotal + $rowTotal;
          $newUnitPrice = $newQty > 0 ? ($newTotal / $newQty) : 0.00;

          $newRemarks = $budgetItem->remarks;
          if (!empty($row['remarks']) && $row['remarks'] !== $budgetItem->remarks) {
            $newRemarks = $budgetItem->remarks ? $budgetItem->remarks . '; ' . $row['remarks'] : $row['remarks'];
          }

          $budgetItem->update([
            'quantity' => $newQty,
            'unit_price' => $newUnitPrice,
            'remarks' => $newRemarks,
          ]);
        }

        for ($m = 1; $m <= 12; $m++) {
          $amount = (float) ($row["m{$m}"] ?? 0);
          if ($amount > 0) {
            $monthly = $budgetItem->monthlies->firstWhere('month', $m);
            if ($monthly) {
              $monthly->update([
                'amount' => $monthly->amount + $amount,
              ]);
            } else {
              $newMonthly = $budgetItem->monthlies()->create([
                'month' => $m,
                'amount' => $amount,
              ]);
              $budgetItem->monthlies->push($newMonthly);
              $createdMonthlies++;
            }
          }
        }

        for ($m = 1; $m <= 12; $m++) {
          $amount = (float) ($row["co{$m}"] ?? 0);
          if ($amount > 0) {
            $cashOut = $budgetItem->cashOuts->firstWhere('month', $m);
            if ($cashOut) {
              $cashOut->update([
                'amount' => $cashOut->amount + $amount,
              ]);
            } else {
              $newCashOut = $budgetItem->cashOuts()->create([
                'month' => $m,
                'amount' => $amount,
              ]);
              $budgetItem->cashOuts->push($newCashOut);
              $createdCashOuts++;
            }
          }
        }
      }

      foreach ($submissionMap as $submission) {
        $submission->calculateTotalBudget();
      }

      $this->importSummary = [
        'submissions' => $createdSubmissions,
        'work_plans' => $createdWorkPlans,
        'budget_items' => $createdBudgetItems,
        'monthlies' => $createdMonthlies,
        'cash_outs' => $createdCashOuts,
      ];

      $this->imported = true;

      \Illuminate\Support\Facades\Log::info('Data migration import successful', [
          'user_id' => auth()->id(),
          'submissions_created' => $createdSubmissions,
          'work_plans_created' => $createdWorkPlans,
          'budget_items_created' => $createdBudgetItems,
          'monthlies_created' => $createdMonthlies,
          'cash_outs_created' => $createdCashOuts,
      ]);
    });
  }

  private function resolveBureauId(?string $code): ?int
  {
    if ($code === null || $code === '') {
      return null;
    }
    if (array_key_exists($code, $this->bureauCache)) {
      return $this->bureauCache[$code];
    }
    $bureau = Bureau::where('code', $code)->first();
    $id = $bureau ? $bureau->id : null;
    $this->bureauCache[$code] = $id;
    return $id;
  }

  private function resolveRkapPeriodId(?string $title): ?int
  {
    if ($title === null || $title === '') {
      return null;
    }
    if (array_key_exists($title, $this->rkapPeriodCache)) {
      return $this->rkapPeriodCache[$title];
    }
    $period = RkapPeriod::where('title', $title)->first();
    $id = $period ? $period->id : null;
    $this->rkapPeriodCache[$title] = $id;
    return $id;
  }

  private function resolveWorkPlanId(?string $code): ?int
  {
    if ($code === null || $code === '') {
      return null;
    }
    if (array_key_exists($code, $this->workPlanCache)) {
      return $this->workPlanCache[$code];
    }
    $wp = WorkPlan::withTrashed()->where('code', $code)->first();
    $id = $wp ? $wp->id : null;
    $this->workPlanCache[$code] = $id;
    if ($wp) {
      $this->workPlanModels[$id] = $wp;
    }
    return $id;
  }

  private function resolveActivityId(?string $code): ?int
  {
    if ($code === null || $code === '') {
      return null;
    }
    if (array_key_exists($code, $this->activityCache)) {
      return $this->activityCache[$code];
    }
    $act = Activity::withTrashed()->where('code', $code)->first();
    $id = $act ? $act->id : null;
    $this->activityCache[$code] = $id;
    if ($act) {
      $this->activityModels[$id] = $act;
    }
    return $id;
  }

  private function resolveCoaId(?string $code): ?int
  {
    if ($code === null || $code === '') {
      return null;
    }
    if (array_key_exists($code, $this->coaCache)) {
      return $this->coaCache[$code];
    }
    $coa = Coa::withTrashed()->where('code', $code)->first();
    $id = $coa ? $coa->id : null;
    $this->coaCache[$code] = $id;
    if ($coa) {
      $this->coaModels[$id] = $coa;
    }
    return $id;
  }

  private function existsUser($id): bool
  {
    if ($id === null || $id === '') {
      return false;
    }
    $id = (int) $id;
    if (array_key_exists($id, $this->userCache)) {
      return $this->userCache[$id];
    }
    $exists = User::whereKey($id)->exists();
    $this->userCache[$id] = $exists;
    return $exists;
  }

  private function existsId(string $modelClass, $id): bool
  {
    if ($id === null || $id === '') {
      return false;
    }

    return $modelClass::whereKey((int) $id)->exists();
  }

  /**
   * Check existence including soft-deleted records (for models using SoftDeletes).
   */
  private function existsIdWithTrashed(string $modelClass, $id): bool
  {
    if ($id === null || $id === '') {
      return false;
    }

    return $modelClass::withTrashed()->whereKey((int) $id)->exists();
  }

  public function render()
  {
    return view('livewire.settings.data-migration-upload')->layout('layouts.contentNavbarLayout');
  }
}
