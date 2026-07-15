<?php

namespace App\Livewire\Rkap;

use App\Models\Activity;
use App\Models\Coa;
use App\Models\RkapBudgetItem;
use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\WorkPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RkapBulkUpload extends Component
{
    use WithFileUploads;

    public ?int $periodId = null;
    public ?RkapPeriod $period = null;
    public $file = null;

    public array $parsedRows = [];
    public array $importErrors = [];
    public array $importSummary = [];
    public bool $parsed = false;
    public bool $imported = false;

    /**
     * Required columns (order must match the template).
     */
    private const REQUIRED_COLUMNS = [
        'work_plan_code',
        'activity_code',
        'coa_code',
        'quantity',
        'unit_price',
    ];

    /**
     * All expected columns in order.
     */
    private const ALL_COLUMNS = [
        'work_plan_code', 'work_plan_name',
        'activity_code', 'activity_name',
        'coa_code', 'coa_name',
        'unit', 'quantity', 'unit_2', 'quantity_2',
        'unit_price', 'remarks',
        'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12',
        'co1', 'co2', 'co3', 'co4', 'co5', 'co6', 'co7', 'co8', 'co9', 'co10', 'co11', 'co12',
    ];

    public function mount(int $periodId): void
    {
        $user = Auth::user();

        if (!$user?->can('rkap.create')) {
            abort(403, __('Anda tidak memiliki akses untuk halaman ini.'));
        }

        if (!$user->bureau_id) {
            abort(403, __('Anda harus terasosiasi dengan Biro untuk membuat pengajuan.'));
        }

        $this->periodId = $periodId;
        $this->period = RkapPeriod::findOrFail($periodId);

        // Check if bureau already has a submission for this period
        $exists = RkapSubmission::where('rkap_period_id', $periodId)
            ->where('bureau_id', $user->bureau_id)
            ->exists();

        if ($exists) {
            session()->flash('error', __('Biro Anda sudah membuat pengajuan RKAP untuk periode ini.'));
            $this->redirectRoute('rkap-submissions');
        }
    }

    /**
     * Handle file upload and parse the Excel.
     */
    public function uploadAndParse(): void
    {
        $this->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:2048',
        ], [
            'file.required' => __('File Excel wajib diupload.'),
            'file.mimes'    => __('File harus berformat .xlsx atau .xls.'),
            'file.max'      => __('Ukuran file maksimal 2 MB.'),
        ]);

        $this->reset(['parsedRows', 'importErrors', 'importSummary', 'parsed', 'imported']);

        try {
            $rows = $this->parseExcel($this->file->getRealPath());
            $this->validateRows($rows);

            if (empty($this->importErrors)) {
                $this->parsedRows = $rows;
                $this->parsed = true;
            }
        } catch (\Exception $e) {
            $this->importErrors[] = __('Gagal membaca file Excel: :message', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Parse the uploaded Excel file into an array of associative rows.
     */
    private function parseExcel(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getSheet(0); // First sheet = "Data Pengajuan"
        $data = $worksheet->toArray(null, true, true, true);

        if (count($data) < 2) {
            $this->importErrors[] = __('File Excel kosong atau tidak memiliki data.');
            return [];
        }

        // Get headers from first row
        $headerRow = $data[1] ?? [];
        $headers = array_map(fn($h) => strtolower(trim((string) $h)), array_values($headerRow));

        // Validate headers
        $missingHeaders = [];
        foreach (self::REQUIRED_COLUMNS as $col) {
            if (!in_array($col, $headers)) {
                $missingHeaders[] = $col;
            }
        }
        if (!empty($missingHeaders)) {
            $this->importErrors[] = __('Kolom wajib tidak ditemukan: :columns', ['columns' => implode(', ', $missingHeaders)]);
            return [];
        }

        // Also check for monthly columns
        for ($i = 1; $i <= 12; $i++) {
            if (!in_array("m{$i}", $headers)) {
                $missingHeaders[] = "m{$i}";
            }
            if (!in_array("co{$i}", $headers)) {
                $missingHeaders[] = "co{$i}";
            }
        }
        if (!empty($missingHeaders)) {
            $this->importErrors[] = __('Kolom distribusi bulanan/kas keluar tidak ditemukan: :columns', ['columns' => implode(', ', $missingHeaders)]);
            return [];
        }

        // Map column letters to header names
        $colMap = [];
        $colLetters = array_keys($headerRow);
        foreach ($colLetters as $idx => $letter) {
            $headerName = $headers[$idx] ?? null;
            if ($headerName) {
                $colMap[$headerName] = $letter;
            }
        }

        // Parse data rows (skip header + hint row)
        $rows = [];
        $rowKeys = array_keys($data);
        for ($i = 2; $i < count($rowKeys); $i++) {
            $rowNum = $rowKeys[$i];
            $rowData = $data[$rowNum] ?? [];

            // Skip if hint row (row 2) or empty row
            if ($i === 1) continue; // Skip hint row

            $row = [];
            foreach (self::ALL_COLUMNS as $col) {
                $letter = $colMap[$col] ?? null;
                $row[$col] = $letter ? trim((string) ($rowData[$letter] ?? '')) : '';
            }

            // Skip completely empty rows
            if (empty($row['work_plan_code']) && empty($row['activity_code']) && empty($row['coa_code'])) {
                continue;
            }

            $row['_row_number'] = $rowNum;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Validate all parsed rows against business rules.
     */
    private function validateRows(array $rows): void
    {
        if (empty($rows)) {
            $this->importErrors[] = __('Tidak ada data yang valid ditemukan dalam file.');
            return;
        }

        // Pre-load master data for validation
        $workPlans = WorkPlan::where('approval_status', 'approved')
            ->get()
            ->keyBy('code');

        $activities = Activity::where('approval_status', 'approved')
            ->with('workPlan')
            ->get();
        $activityMap = $activities->keyBy('code');

        $coas = Coa::all()->keyBy('code');

        foreach ($rows as $idx => $row) {
            $rowNum = $row['_row_number'] ?? ($idx + 3);
            $prefix = __('Baris :row', ['row' => $rowNum]);

            // Work Plan code
            if (empty($row['work_plan_code'])) {
                $this->importErrors[] = "{$prefix}: " . __('work_plan_code wajib diisi.');
                continue;
            }
            $wp = $workPlans->get($row['work_plan_code']);
            if (!$wp) {
                $this->importErrors[] = "{$prefix}: " . __('work_plan_code ":code" tidak ditemukan atau belum disetujui.', ['code' => $row['work_plan_code']]);
                continue;
            }

            // Activity code
            if (empty($row['activity_code'])) {
                $this->importErrors[] = "{$prefix}: " . __('activity_code wajib diisi.');
                continue;
            }
            $act = $activityMap->get($row['activity_code']);
            if (!$act) {
                $this->importErrors[] = "{$prefix}: " . __('activity_code ":code" tidak ditemukan atau belum disetujui.', ['code' => $row['activity_code']]);
                continue;
            }
            if ($act->work_plan_id !== $wp->id) {
                $this->importErrors[] = "{$prefix}: " . __('activity_code ":act" bukan milik work_plan_code ":wp".', [
                    'act' => $row['activity_code'],
                    'wp' => $row['work_plan_code'],
                ]);
                continue;
            }

            // COA code
            if (empty($row['coa_code'])) {
                $this->importErrors[] = "{$prefix}: " . __('coa_code wajib diisi.');
                continue;
            }
            $coa = $coas->get($row['coa_code']);
            if (!$coa) {
                $this->importErrors[] = "{$prefix}: " . __('coa_code ":code" tidak ditemukan.', ['code' => $row['coa_code']]);
                continue;
            }

            // Quantity
            $qty = (int) ($row['quantity'] ?: 0);
            if ($qty < 1) {
                $this->importErrors[] = "{$prefix}: " . __('quantity harus minimal 1.');
            }

            // Unit price
            $unitPrice = (float) ($row['unit_price'] ?: 0);
            if ($unitPrice < 0) {
                $this->importErrors[] = "{$prefix}: " . __('unit_price tidak boleh negatif.');
            }

            // Calculate total
            $qty2 = !empty($row['unit_2']) ? max(1, (int) ($row['quantity_2'] ?: 1)) : 1;
            $totalItem = $qty * $qty2 * $unitPrice;

            // Monthly distribution validation
            $monthlyTotal = 0;
            for ($m = 1; $m <= 12; $m++) {
                $val = (float) ($row["m{$m}"] ?: 0);
                if ($val < 0) {
                    $this->importErrors[] = "{$prefix}: " . __('m:month tidak boleh negatif.', ['month' => $m]);
                }
                $monthlyTotal += $val;
            }

            if ($totalItem > 0 && abs($totalItem - $monthlyTotal) > 0.01) {
                $diff = $totalItem - $monthlyTotal;
                $this->importErrors[] = "{$prefix}: " . __('Total distribusi bulanan (Rp :monthly) tidak sama dengan total item (Rp :total). Selisih: Rp :diff.', [
                    'monthly' => number_format($monthlyTotal, 0, ',', '.'),
                    'total'   => number_format($totalItem, 0, ',', '.'),
                    'diff'    => number_format(abs($diff), 0, ',', '.'),
                ]);
            }

            // Cash out validation
            $cashOutTotal = 0;
            for ($m = 1; $m <= 12; $m++) {
                $val = (float) ($row["co{$m}"] ?: 0);
                if ($val < 0) {
                    $this->importErrors[] = "{$prefix}: " . __('co:month tidak boleh negatif.', ['month' => $m]);
                }
                $cashOutTotal += $val;
            }

            if ($cashOutTotal <= 0 && $totalItem > 0) {
                $this->importErrors[] = "{$prefix}: " . __('Total rencana kas keluar harus lebih besar dari 0.');
            }

            if ($cashOutTotal - $totalItem > 0.01) {
                $this->importErrors[] = "{$prefix}: " . __('Total rencana kas keluar (Rp :cashout) melebihi total item (Rp :total).', [
                    'cashout' => number_format($cashOutTotal, 0, ',', '.'),
                    'total'   => number_format($totalItem, 0, ',', '.'),
                ]);
            }
        }
    }

    /**
     * Save parsed data as a draft RKAP submission.
     */
    public function saveAsDraft(): void
    {
        if (empty($this->parsedRows)) {
            $this->importErrors[] = __('Tidak ada data untuk disimpan.');
            return;
        }

        $user = Auth::user();

        // Double-check no existing submission
        $exists = RkapSubmission::where('rkap_period_id', $this->periodId)
            ->where('bureau_id', $user->bureau_id)
            ->exists();
        if ($exists) {
            $this->importErrors[] = __('Biro Anda sudah membuat pengajuan RKAP untuk periode ini.');
            return;
        }

        // Pre-load master data
        $workPlans = WorkPlan::where('approval_status', 'approved')->get()->keyBy('code');
        $activities = Activity::where('approval_status', 'approved')->get()->keyBy('code');
        $coas = Coa::all()->keyBy('code');

        // Group rows by work_plan_code + activity_code
        $grouped = [];
        foreach ($this->parsedRows as $row) {
            $key = $row['work_plan_code'] . '||' . $row['activity_code'];
            $grouped[$key][] = $row;
        }

        try {
            DB::transaction(function () use ($user, $workPlans, $activities, $coas, $grouped) {
                // Create submission
                $submission = RkapSubmission::create([
                    'rkap_period_id' => $this->periodId,
                    'bureau_id'      => $user->bureau_id,
                    'created_by'     => $user->id,
                    'status'         => 'draft',
                    'notes'          => __('Dibuat melalui upload massal Excel'),
                ]);

                $sortOrder = 0;

                foreach ($grouped as $key => $rows) {
                    $firstRow = $rows[0];

                    $wp = $workPlans->get($firstRow['work_plan_code']);
                    $act = $activities->get($firstRow['activity_code']);

                    if (!$wp || !$act) {
                        continue;
                    }

                    // Create RkapWorkPlan
                    $rkapWorkPlan = RkapWorkPlan::create([
                        'rkap_submission_id' => $submission->id,
                        'work_plan_id'       => $wp->id,
                        'activity_id'        => $act->id,
                        'program_name'       => $act->title,
                        'program_code'       => $act->code,
                        'description'        => null,
                        'output_target'      => null,
                        'unit'               => null,
                        'quantity'            => 1,
                        'sort_order'         => $sortOrder++,
                    ]);

                    // Create budget items for each row in this group
                    foreach ($rows as $row) {
                        $coa = $coas->get($row['coa_code']);
                        if (!$coa) continue;

                        $qty = max(1, (int) ($row['quantity'] ?: 1));
                        $qty2 = !empty($row['unit_2']) ? max(1, (int) ($row['quantity_2'] ?: 1)) : null;
                        $unitPrice = (float) ($row['unit_price'] ?: 0);

                        $budgetItem = RkapBudgetItem::create([
                            'rkap_work_plan_id' => $rkapWorkPlan->id,
                            'account_code'      => $coa->code,
                            'description'       => $coa->title,
                            'unit'              => $row['unit'] ?: null,
                            'quantity'           => $qty,
                            'unit_2'            => $row['unit_2'] ?: null,
                            'quantity_2'        => $qty2,
                            'unit_price'        => $unitPrice,
                            'remarks'           => $row['remarks'] ?: null,
                        ]);

                        // Save monthly distribution
                        for ($m = 1; $m <= 12; $m++) {
                            $amount = (float) ($row["m{$m}"] ?: 0);
                            if ($amount > 0) {
                                $budgetItem->monthlies()->create([
                                    'month'  => $m,
                                    'amount' => $amount,
                                ]);
                            }
                        }

                        // Save cash out distribution
                        for ($m = 1; $m <= 12; $m++) {
                            $amount = (float) ($row["co{$m}"] ?: 0);
                            if ($amount > 0) {
                                $budgetItem->cashOuts()->create([
                                    'month'  => $m,
                                    'amount' => $amount,
                                ]);
                            }
                        }
                    }
                }

                // Recalculate total budget
                $submission->calculateTotalBudget();

                $this->importSummary = [
                    'submission_id'   => $submission->id,
                    'total_rows'      => count($this->parsedRows),
                    'total_groups'    => count($grouped),
                    'total_budget'    => $submission->fresh()->total_budget ?? 0,
                ];
            });

            $this->imported = true;
            session()->flash('message', __('Upload massal berhasil! :count item anggaran telah disimpan sebagai draft.', [
                'count' => count($this->parsedRows),
            ]));
        } catch (\Exception $e) {
            $this->importErrors[] = __('Gagal menyimpan data: :message', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Reset the upload form.
     */
    public function resetUpload(): void
    {
        $this->reset(['file', 'parsedRows', 'importErrors', 'importSummary', 'parsed', 'imported']);
    }

    /**
     * Get grouped preview data for the blade template.
     */
    public function getGroupedPreviewProperty(): array
    {
        $grouped = [];
        foreach ($this->parsedRows as $row) {
            $key = ($row['work_plan_code'] ?? '') . ' — ' . ($row['activity_code'] ?? '');
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'work_plan_code' => $row['work_plan_code'] ?? '',
                    'work_plan_name' => $row['work_plan_name'] ?? '',
                    'activity_code'  => $row['activity_code'] ?? '',
                    'activity_name'  => $row['activity_name'] ?? '',
                    'items'          => [],
                    'subtotal'       => 0,
                ];
            }

            $qty = max(1, (int) ($row['quantity'] ?: 1));
            $qty2 = !empty($row['unit_2']) ? max(1, (int) ($row['quantity_2'] ?: 1)) : 1;
            $unitPrice = (float) ($row['unit_price'] ?: 0);
            $total = $qty * $qty2 * $unitPrice;

            $grouped[$key]['items'][] = [
                'coa_code'   => $row['coa_code'] ?? '',
                'coa_name'   => $row['coa_name'] ?? '',
                'unit'       => $row['unit'] ?? '',
                'quantity'   => $qty,
                'unit_2'     => $row['unit_2'] ?? '',
                'quantity_2' => $row['quantity_2'] ?? '',
                'unit_price' => $unitPrice,
                'total'      => $total,
                'remarks'    => $row['remarks'] ?? '',
            ];

            $grouped[$key]['subtotal'] += $total;
        }

        return $grouped;
    }

    public function render()
    {
        return view('livewire.rkap.rkap-bulk-upload')
            ->layout('layouts.contentNavbarLayout');
    }
}
