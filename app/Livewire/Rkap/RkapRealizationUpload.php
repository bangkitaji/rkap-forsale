<?php

namespace App\Livewire\Rkap;

use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemRealization;
use App\Models\RkapPeriod;
use App\Services\AnalyticsCacheService;
use App\Services\ProjectionRecalculationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Exports\RkapRealizationsExport;
use App\Exports\RkapRealizationMassUpdateTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

class RkapRealizationUpload extends Component
{
    use WithFileUploads, WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $file;
    public ?int $periodId = null;
    public ?int $month = null;
    public ?int $filterMonth = null;
    public ?string $importedMonthName = null;
    public ?string $search = '';
    public ?int $filterDepartmentId = null;

    public array $errorsList    = [];
    public array $importSummary = [];
    public bool  $imported      = false;

    // --- Mass Update (Admin only) ---
    public $massUpdateFile;
    public array $massUpdateErrorsList    = [];
    public array $massUpdateImportSummary = [];
    public bool  $massUpdateImported      = false;

    private array $requiredColumns = [
        'budget_item_id',
        'month',
        'amount',
    ];

    public function getIsAdminUserProperty(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->isAdmin()
            || $user->hasRole(['admin', 'administrator', 'superadmin'])
            || $user->can('rkap.closing.manage');
    }

    public function mount(): void
    {
        if (! auth()->user()?->can('rkap.realization.upload') && ! $this->isAdminUser) {
            abort(403, __('Anda tidak memiliki akses untuk halaman ini.'));
        }
    }

    public function updatedPeriodId(): void
    {
        $this->resetPage();
        $this->month = null;
        $this->filterMonth = null;
        $this->filterDepartmentId = null;
        $this->resetState();
    }

    public function updatedFilterDepartmentId(): void
    {
        $this->resetPage();
    }

    public function updatedMonth(): void
    {
        $this->resetPage();
        $this->resetState();
    }

    public function updatedFilterMonth(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getPeriodOptionsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        $currentYear = (int) date('Y');
        return RkapPeriod::where('status', 'finalized')
            ->where('year', $currentYear)
            ->whereHas('submissions', function ($query) {
                $query->where('status', 'approved');
            })
            ->orderBy('year', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getMonthOptionsProperty(): array
    {
        if (! $this->periodId) {
            return [];
        }

        $uploadedMonths = RkapBudgetItemRealization::where('rkap_period_id', $this->periodId)
            ->distinct()
            ->pluck('month')
            ->toArray();

        $allMonths = [
            1  => 'Januari',  2  => 'Februari', 3  => 'Maret',
            4  => 'April',    5  => 'Mei',       6  => 'Juni',
            7  => 'Juli',     8  => 'Agustus',   9  => 'September',
            10 => 'Oktober',  11 => 'November',  12 => 'Desember',
        ];

        $period = RkapPeriod::find($this->periodId);

        $options = [];
        foreach ($allMonths as $num => $name) {
            if (! in_array($num, $uploadedMonths, true)) {
                if ($period && !$period->isMonthClosed($num)) {
                    $options[$num] = $name;
                }
            }
        }

        return $options;
    }

    /**
     * Returns the last month number (1–12) that is already closed for the selected period,
     * or null if no month is closed yet.
     */
    public function getLastClosedMonthProperty(): ?int
    {
        if (! $this->periodId) {
            return null;
        }

        $period = RkapPeriod::find($this->periodId);
        if (! $period) {
            return null;
        }

        $lastClosed = null;
        for ($m = 1; $m <= 12; $m++) {
            if ($period->isMonthClosed($m)) {
                $lastClosed = $m;
            }
        }

        return $lastClosed;
    }

    private function getMonthName(int $month): string
    {
        $names = [
            1  => 'Januari',  2  => 'Februari', 3  => 'Maret',
            4  => 'April',    5  => 'Mei',       6  => 'Juni',
            7  => 'Juli',     8  => 'Agustus',   9  => 'September',
            10 => 'Oktober',  11 => 'November',  12 => 'Desember',
        ];
        return $names[$month] ?? '';
    }

    public function uploadAndImport(): void
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        $this->resetState();

        $this->validate([
            'periodId' => 'required|integer|exists:rkap_periods,id',
            'month'    => 'required|integer|between:1,12',
            'file'     => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ], [
            'periodId.required' => 'Periode RKAP wajib dipilih sebelum upload.',
            'month.required'    => 'Bulan realisasi wajib dipilih sebelum upload.',
            'month.between'     => 'Bulan tidak valid.',
        ]);

        // Validate that the period is finalized and is for the current year
        $currentYear = (int) date('Y');
        $validPeriod = RkapPeriod::where('status', 'finalized')
            ->where('year', $currentYear)
            ->find($this->periodId);

        if (!$validPeriod) {
            $this->errorsList[] = 'Realisasi hanya dapat diunggah untuk periode RKAP tahun berjalan (' . $currentYear . ') dengan status Finalized.';
            return;
        }

        // Validate that the month is not closed
        if ($validPeriod->isMonthClosed($this->month)) {
            $closingDate = $validPeriod->getClosingDateForMonth($this->month);
            $closingDateStr = $closingDate ? $closingDate->format('d M Y') : '';
            $this->errorsList[] = 'Pemberitahuan: Pengunggahan realisasi untuk bulan ' . $this->getMonthName($this->month) . ' telah ditutup karena melewati batas closing periode (' . $closingDateStr . ').';
            return;
        }

        // Validate that there is at least one approved (finalized) submission for this period
        $hasApproved = \App\Models\RkapSubmission::where('rkap_period_id', $validPeriod->id)
            ->where('status', 'approved')
            ->exists();

        if (!$hasApproved) {
            $this->errorsList[] = 'Tidak ditemukan pengajuan RKAP yang disetujui (final) pada periode ini.';
            return;
        }

        $alreadyUploaded = RkapBudgetItemRealization::where('rkap_period_id', $this->periodId)
            ->where('month', $this->month)
            ->exists();
        if ($alreadyUploaded) {
            $this->errorsList[] = 'Realisasi untuk bulan ' . $this->getMonthName($this->month) . ' sudah pernah diunggah.';
            return;
        }

        $parsed = $this->parseFile(
            $this->file->getRealPath(),
            $this->file->getClientOriginalExtension()
        );

        if (empty($parsed)) {
            $this->errorsList[] = 'File kosong atau tidak valid.';
            return;
        }

        [$headers, $dataRows] = $parsed;

        $missingColumns = array_values(array_diff($this->requiredColumns, $headers));
        if (! empty($missingColumns)) {
            $this->errorsList[] = 'Kolom wajib tidak ditemukan: ' . implode(', ', $missingColumns);
            return;
        }

        $normalizedRows = $this->normalizeRows($headers, $dataRows);
        $this->validateRows($normalizedRows);

        if (! empty($this->errorsList)) {
            return;
        }

        $this->importRows($normalizedRows);

        // Flush analytics cache for this period since realization data changed
        AnalyticsCacheService::flushPeriod($this->periodId);

        $this->importedMonthName = $this->getMonthName($this->month);
        $this->month = null;
        $this->file = null;
    }

    private function resetState(): void
    {
        $this->errorsList    = [];
        $this->importSummary = [];
        $this->imported      = false;
        $this->importedMonthName = null;
    }

    // =========================================================================
    // Mass Update (Admin only) — Jan s.d. bulan terakhir closing
    // =========================================================================

    /**
     * Download the mass-update template pre-filled with existing realization data.
     * Only available to admin users.
     */
    public function downloadMassUpdateTemplate()
    {
        if (! $this->isAdminUser) {
            session()->flash('massUpdateError', __('Fitur ini hanya tersedia untuk Administrator.'));
            return;
        }

        if (! $this->periodId) {
            session()->flash('massUpdateError', __('Pilih periode RKAP terlebih dahulu.'));
            return;
        }

        $lastClosed = $this->lastClosedMonth;
        if (! $lastClosed) {
            session()->flash('massUpdateError', __('Belum ada bulan yang sudah closing untuk periode ini.'));
            return;
        }

        $currentYear = (int) date('Y');
        $validPeriod = RkapPeriod::where('status', 'finalized')
            ->where('year', $currentYear)
            ->find($this->periodId);

        if (! $validPeriod) {
            session()->flash('massUpdateError', __('Realisasi hanya dapat di-update untuk periode RKAP tahun berjalan (' . $currentYear . ') dengan status Finalized.'));
            return;
        }

        $period   = $validPeriod;
        $year     = $period->year;
        $filename = 'template_mass_update_realisasi_' . $year . '_jan-' . $this->getMonthName($lastClosed) . '_' . date('YmdHis') . '.xlsx';

        return Excel::download(
            new RkapRealizationMassUpdateTemplateExport($this->periodId, $lastClosed),
            $filename
        );
    }

    /**
     * Process the mass-update file uploaded by admin.
     * Upserts realizations for months 1 s.d. lastClosedMonth.
     */
    public function massUpdateUpload(): void
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        $this->resetMassUpdateState();

        if (! $this->isAdminUser) {
            $this->massUpdateErrorsList[] = 'Fitur ini hanya tersedia untuk Administrator.';
            return;
        }

        $this->validate([
            'periodId'       => 'required|integer|exists:rkap_periods,id',
            'massUpdateFile' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ], [
            'periodId.required'       => 'Periode RKAP wajib dipilih sebelum upload.',
            'massUpdateFile.required' => 'File wajib dipilih sebelum upload.',
        ]);

        // Validate period is finalized and current year
        $currentYear  = (int) date('Y');
        $validPeriod  = RkapPeriod::where('status', 'finalized')
            ->where('year', $currentYear)
            ->find($this->periodId);

        if (! $validPeriod) {
            $this->massUpdateErrorsList[] = 'Realisasi hanya dapat di-update untuk periode RKAP tahun berjalan (' . $currentYear . ') dengan status Finalized.';
            return;
        }

        $lastClosed = $this->lastClosedMonth;
        if (! $lastClosed) {
            $this->massUpdateErrorsList[] = 'Belum ada bulan yang sudah closing. Mass update hanya dapat dilakukan setelah minimal satu bulan closing.';
            return;
        }

        // Validate approved submission exists
        $hasApproved = \App\Models\RkapSubmission::where('rkap_period_id', $validPeriod->id)
            ->where('status', 'approved')
            ->exists();

        if (! $hasApproved) {
            $this->massUpdateErrorsList[] = 'Tidak ditemukan pengajuan RKAP yang disetujui (final) pada periode ini.';
            return;
        }

        $parsed = $this->parseFile(
            $this->massUpdateFile->getRealPath(),
            $this->massUpdateFile->getClientOriginalExtension()
        );

        if (empty($parsed)) {
            $this->massUpdateErrorsList[] = 'File kosong atau tidak valid.';
            return;
        }

        [$headers, $dataRows] = $parsed;

        $missingColumns = array_values(array_diff($this->requiredColumns, $headers));
        if (! empty($missingColumns)) {
            $this->massUpdateErrorsList[] = 'Kolom wajib tidak ditemukan: ' . implode(', ', $missingColumns);
            return;
        }

        $normalizedRows = $this->normalizeRows($headers, $dataRows);
        $this->validateMassUpdateRows($normalizedRows, $lastClosed);

        if (! empty($this->massUpdateErrorsList)) {
            return;
        }

        $this->importMassUpdateRows($normalizedRows);

        // Flush analytics cache since realization data changed
        AnalyticsCacheService::flushPeriod($this->periodId);

        $this->massUpdateFile = null;
    }

    private function resetMassUpdateState(): void
    {
        $this->massUpdateErrorsList    = [];
        $this->massUpdateImportSummary = [];
        $this->massUpdateImported      = false;
    }

    /**
     * Validate rows for mass update:
     * - budget_item_id must be valid in the selected period
     * - month must be between 1 and $lastClosedMonth (inclusive)
     * - No check for "already uploaded" — mass update is designed to overwrite
     */
    private function validateMassUpdateRows(array $rows, int $lastClosedMonth): void
    {
        $validBudgetItemIds = $this->getValidBudgetItemIds();

        foreach ($rows as $row) {
            $rowNo = $row['_row_number'];

            // Required field checks
            foreach ($this->requiredColumns as $col) {
                if (! isset($row[$col]) || $row[$col] === '') {
                    $this->massUpdateErrorsList[] = "Baris {$rowNo}: kolom {$col} wajib diisi.";
                }
            }

            // budget_item_id must belong to the selected period
            $biId = $row['budget_item_id'] ?? null;
            if ($biId !== null && $biId !== '') {
                if (! in_array((int) $biId, $validBudgetItemIds, true)) {
                    $this->massUpdateErrorsList[] = "Baris {$rowNo}: budget_item_id {$biId} tidak ditemukan atau tidak termasuk dalam periode yang dipilih.";
                }
            }

            // Month must be between 1 and lastClosedMonth
            $month = (int) ($row['month'] ?? 0);
            if ($month < 1 || $month > 12) {
                $this->massUpdateErrorsList[] = "Baris {$rowNo}: month harus antara 1–12.";
            } elseif ($month > $lastClosedMonth) {
                $this->massUpdateErrorsList[] = "Baris {$rowNo}: bulan {$month} (" . $this->getMonthName($month) . ") melebihi batas bulan closing terakhir: {$lastClosedMonth} (" . $this->getMonthName($lastClosedMonth) . "). Mass update hanya untuk bulan yang sudah closing.";
            }
        }
    }

    /**
     * Upsert rows: update existing realization if found, create new one if not.
     * This intentionally overwrites existing data — that is the purpose of mass update.
     */
    private function importMassUpdateRows(array $rows): void
    {
        DB::transaction(function () use ($rows): void {
            $created = 0;
            $updated = 0;
            $affectedBudgetItemIds = [];

            foreach ($rows as $row) {
                $biId     = (int) $row['budget_item_id'];
                $month    = (int) $row['month'];
                $amount   = $this->sanitizeAmount($row['amount'] ?? '0');
                $periodId = $this->periodId;

                $existing = RkapBudgetItemRealization::where('rkap_budget_item_id', $biId)
                    ->where('rkap_period_id', $periodId)
                    ->where('month', $month)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'amount'      => $amount,
                        'uploaded_by' => auth()->id(),
                        'uploaded_at' => now(),
                    ]);
                    $updated++;
                } else {
                    RkapBudgetItemRealization::create([
                        'rkap_budget_item_id' => $biId,
                        'rkap_period_id'      => $periodId,
                        'month'               => $month,
                        'amount'              => $amount,
                        'uploaded_by'         => auth()->id(),
                        'uploaded_at'         => now(),
                    ]);
                    $created++;
                }

                $affectedBudgetItemIds[] = $biId;
            }

            // Recalculate and synchronize projections for affected budget items
            ProjectionRecalculationService::recalculateForBudgetItems(
                $affectedBudgetItemIds,
                $this->periodId,
                auth()->id(),
                null,
                'realization_mass_update'
            );

            $this->massUpdateImportSummary = [
                'created' => $created,
                'updated' => $updated,
                'total'   => $created + $updated,
            ];

            $this->massUpdateImported = true;
        });
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
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $worksheet   = $spreadsheet->getActiveSheet();
            $rows        = $worksheet->toArray(null, true, true, false);

            if (empty($rows)) {
                return [];
            }

            $headers  = array_map(static fn ($h) => trim((string) ($h ?? '')), array_shift($rows));
            $dataRows = [];

            foreach ($rows as $row) {
                $stringRow = array_map(static fn ($v) => (string) ($v ?? ''), $row);
                if (count(array_filter($stringRow, static fn ($v) => trim($v) !== '')) === 0) {
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
        if (! $handle) {
            return [];
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            return [];
        }

        $headers  = array_map(static fn ($h) => trim((string) $h), $headers);
        $dataRows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
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
            // Skip Excel template hint row
            if (isset($assoc['budget_item_id']) && str_starts_with($assoc['budget_item_id'], '(')) {
                continue;
            }
            $assoc['_row_number'] = $index + 2;
            $normalized[]         = $assoc;
        }

        return $normalized;
    }

    private function validateRows(array $rows): void
    {
        // Pre-load valid budget_item_ids for the selected period to avoid N+1 queries
        $validBudgetItemIds = $this->getValidBudgetItemIds();

        // Also get all months that already have realization uploaded for this period (in case someone uploaded in the meantime or they changed the month column in file)
        $uploadedMonths = RkapBudgetItemRealization::where('rkap_period_id', $this->periodId)
            ->distinct()
            ->pluck('month')
            ->toArray();

        foreach ($rows as $row) {
            $rowNo = $row['_row_number'];

            // Required field checks
            foreach ($this->requiredColumns as $col) {
                if (! isset($row[$col]) || $row[$col] === '') {
                    $this->errorsList[] = "Baris {$rowNo}: kolom {$col} wajib diisi.";
                }
            }

            // budget_item_id must belong to the selected period
            $biId = $row['budget_item_id'] ?? null;
            if ($biId !== null && $biId !== '') {
                if (! in_array((int) $biId, $validBudgetItemIds, true)) {
                    $this->errorsList[] = "Baris {$rowNo}: budget_item_id {$biId} tidak ditemukan atau tidak termasuk dalam periode yang dipilih.";
                }
            }

            // Month must be 1–12
            $month = (int) ($row['month'] ?? 0);
            if ($month < 1 || $month > 12) {
                $this->errorsList[] = "Baris {$rowNo}: month harus antara 1–12.";
            } else {
                // Reject if the month doesn't match the selected month
                if ($month !== (int) $this->month) {
                    $this->errorsList[] = "Baris {$rowNo}: bulan {$month} (" . $this->getMonthName($month) . ") tidak sesuai dengan bulan yang dipilih: {$this->month} (" . $this->getMonthName($this->month) . ").";
                }

                // Reject if the month already has realization uploaded
                if (in_array($month, $uploadedMonths, true)) {
                    $this->errorsList[] = "Baris {$rowNo}: Bulan " . $this->getMonthName($month) . " sudah memiliki realisasi yang diunggah.";
                }
            }

            // Amount: negative values are allowed for budget corrections (no sign check)
        }
    }

    /**
     * Collect all rkap_budget_item ids that belong to approved (finalized) submissions in the selected period.
     */
    private function getValidBudgetItemIds(): array
    {
        return RkapBudgetItem::whereHas('workPlan.submission', function ($q) {
            $q->where('rkap_period_id', $this->periodId)
              ->where('status', 'approved');
        })->pluck('id')->map(fn ($v) => (int) $v)->toArray();
    }

    private function importRows(array $rows): void
    {
        DB::transaction(function () use ($rows): void {
            $created = 0;
            $updated = 0;
            $affectedBudgetItemIds = [];

            foreach ($rows as $row) {
                $biId     = (int) $row['budget_item_id'];
                $month    = (int) $row['month'];
                $amount   = $this->sanitizeAmount($row['amount'] ?? '0');
                $periodId = $this->periodId;

                // Scope lookup to the selected period to ensure period separation
                $existing = RkapBudgetItemRealization::where('rkap_budget_item_id', $biId)
                    ->where('rkap_period_id', $periodId)
                    ->where('month', $month)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'amount'         => $amount,
                        'rkap_period_id' => $periodId,
                        'uploaded_by'    => auth()->id(),
                        'uploaded_at'    => now(),
                    ]);
                    $updated++;
                } else {
                    RkapBudgetItemRealization::create([
                        'rkap_budget_item_id' => $biId,
                        'rkap_period_id'      => $periodId,
                        'month'               => $month,
                        'amount'              => $amount,
                        'uploaded_by'         => auth()->id(),
                        'uploaded_at'         => now(),
                    ]);
                    $created++;
                }

                $affectedBudgetItemIds[] = $biId;
            }

            // Recalculate and synchronize projections for affected budget items
            ProjectionRecalculationService::recalculateForBudgetItems(
                $affectedBudgetItemIds,
                $this->periodId,
                auth()->id(),
                null,
                'realization_upload'
            );

            $this->importSummary = [
                'created' => $created,
                'updated' => $updated,
                'total'   => $created + $updated,
            ];

            $this->imported = true;
        });
    }

    public function deleteRealization(int $id): void
    {
        if (! auth()->user()?->can('rkap.realization.upload')) {
            session()->flash('error', __('Anda tidak memiliki akses untuk menghapus data ini.'));
            return;
        }

        $realization = RkapBudgetItemRealization::find($id);
        if ($realization) {
            $period = RkapPeriod::find($realization->rkap_period_id);
            if ($period && $period->year !== (int) date('Y')) {
                session()->flash('error', __('Realisasi hanya dapat dihapus untuk periode RKAP tahun berjalan.'));
                return;
            }
            if ($period && $period->isMonthClosed($realization->month)) {
                $closingDate = $period->getClosingDateForMonth($realization->month);
                $closingDateStr = $closingDate ? $closingDate->format('d M Y') : '';
                session()->flash('error', __('Realisasi untuk bulan ') . $this->getMonthName($realization->month) . ' tidak dapat dihapus karena periode pengisian realisasi telah ditutup (' . $closingDateStr . ').');
                return;
            }

            $budgetItemId = $realization->rkap_budget_item_id;
            $periodId = $realization->rkap_period_id;

            $realization->delete();

            // Recalculate and synchronize projections for affected budget item
            ProjectionRecalculationService::recalculateForBudgetItems(
                [$budgetItemId],
                $periodId,
                auth()->id(),
                null,
                'realization_delete'
            );

            AnalyticsCacheService::flushPeriod($periodId);
            session()->flash('message', __('Data realisasi berhasil dihapus.'));
        }
    }

    public function getDepartmentAccumulationsProperty(): \Illuminate\Support\Collection
    {
        if (!$this->periodId) {
            return collect();
        }

        return RkapBudgetItemRealization::query()
            ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
            ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
            ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
            ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
            ->join('departments', 'bureaus.department_id', '=', 'departments.id')
            ->where('rkap_budget_item_realizations.rkap_period_id', $this->periodId)
            ->when($this->filterMonth, fn($q) => $q->where('rkap_budget_item_realizations.month', $this->filterMonth))
            ->when($this->search, function ($q) {
                $search = $this->search;
                $q->where(function ($sub) use ($search) {
                    $sub->where('rkap_budget_items.account_code', 'like', '%' . $search . '%')
                        ->orWhere('rkap_budget_items.description', 'like', '%' . $search . '%')
                        ->orWhere('bureaus.code', 'like', '%' . $search . '%')
                        ->orWhere('bureaus.name', 'like', '%' . $search . '%')
                        ->orWhere('departments.code', 'like', '%' . $search . '%')
                        ->orWhere('departments.name', 'like', '%' . $search . '%');
                });
            })
            ->selectRaw('departments.id, departments.code, departments.name, SUM(rkap_budget_item_realizations.amount) as total_amount')
            ->groupBy('departments.id', 'departments.code', 'departments.name')
            ->orderBy('total_amount', 'desc')
            ->get();
    }

    public function exportExcel()
    {
        if (! auth()->user()?->can('rkap.realization.upload')) {
            abort(403, __('Anda tidak memiliki akses untuk mengekspor data ini.'));
        }

        $period = RkapPeriod::find($this->periodId);
        $year = $period ? $period->year : date('Y');
        $filename = 'rkap-monitoring-realisasi-' . $year . '-' . date('YmdHis') . '.xlsx';

        return Excel::download(
            new RkapRealizationsExport(
                $this->periodId,
                null,
                $this->filterDepartmentId,
                null,
                $this->search
            ),
            $filename
        );
    }

    public function render(): View
    {
        $realizations = collect();

        if ($this->periodId) {
            $query = RkapBudgetItemRealization::with([
                'budgetItem.workPlan.submission.bureau',
                'uploader'
            ])
            ->where('rkap_period_id', $this->periodId);

            if ($this->filterMonth) {
                $query->where('month', $this->filterMonth);
            }

            if ($this->filterDepartmentId) {
                $query->whereHas('budgetItem.workPlan.submission.bureau', function ($bQuery) {
                    $bQuery->where('department_id', $this->filterDepartmentId);
                });
            }

            if ($this->search) {
                $q = $this->search;
                $query->where(function ($subQuery) use ($q) {
                    $subQuery->whereHas('budgetItem', function ($biQuery) use ($q) {
                        $biQuery->where('account_code', 'like', '%' . $q . '%')
                            ->orWhere('description', 'like', '%' . $q . '%')
                            ->orWhereHas('workPlan.submission.bureau', function ($bQuery) use ($q) {
                                $bQuery->where('code', 'like', '%' . $q . '%')
                                    ->orWhere('name', 'like', '%' . $q . '%');
                            });
                    });
                });
            }

            $realizations = $query->orderBy('month')->orderBy('id', 'desc')->paginate(15);
        }

        return view('livewire.rkap.rkap-realization-upload', [
            'periodOptions' => $this->periodOptions,
            'realizations' => $realizations,
        ])->layout('layouts.contentNavbarLayout');
    }

    /**
     * Sanitize a raw amount string coming from the imported file.
     *
     * Handles locale-specific thousand separators so that both
     * "1.000.000" (Indonesian/German dot) and "1,000,000" (en-US comma)
     * are correctly parsed to 1000000.0.
     *
     * Logic:
     *   - If the string contains BOTH a dot and a comma, the one that
     *     appears last is the decimal separator.
     *   - If it contains only dots and more than one, they are thousand
     *     separators (e.g. "1.000.000" → strip dots → 1000000).
     *   - If it contains only commas and more than one, they are thousand
     *     separators (e.g. "1,000,000" → strip commas → 1000000).
     *   - A single dot or single comma is treated as a decimal separator.
     */
    private function sanitizeAmount(string $raw): float
    {
        $s = trim($raw);

        if ($s === '' || $s === '-') {
            return 0.0;
        }

        $dotCount   = substr_count($s, '.');
        $commaCount = substr_count($s, ',');

        if ($dotCount > 0 && $commaCount > 0) {
            // Both separators present: whichever comes last is the decimal.
            $lastDot   = strrpos($s, '.');
            $lastComma = strrpos($s, ',');

            if ($lastDot > $lastComma) {
                // e.g. "1,000.50" — comma is thousands, dot is decimal
                $s = str_replace(',', '', $s);
            } else {
                // e.g. "1.000,50" — dot is thousands, comma is decimal
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            }
        } elseif ($dotCount > 1) {
            // Multiple dots → all are thousand separators, e.g. "1.000.000"
            $s = str_replace('.', '', $s);
        } elseif ($commaCount > 1) {
            // Multiple commas → all are thousand separators, e.g. "1,000,000"
            $s = str_replace(',', '', $s);
        } elseif ($commaCount === 1 && $dotCount === 0) {
            // Single comma could be decimal (e.g. "1000,50") or thousands (e.g. "1,000")
            // Treat as decimal separator (safer for amounts input by hand).
            $s = str_replace(',', '.', $s);
        }
        // Single dot with dotCount === 1: treat as decimal separator — no change needed.

        return (float) $s;
    }
}
