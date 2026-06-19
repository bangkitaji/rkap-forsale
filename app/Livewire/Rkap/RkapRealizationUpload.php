<?php

namespace App\Livewire\Rkap;

use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemRealization;
use App\Models\RkapPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

    public array $errorsList    = [];
    public array $importSummary = [];
    public bool  $imported      = false;

    private array $requiredColumns = [
        'budget_item_id',
        'month',
        'amount',
    ];

    public function mount(): void
    {
        if (! auth()->user()?->can('rkap.realization.upload')) {
            abort(403, 'Anda tidak memiliki akses untuk halaman ini.');
        }
    }

    public function updatedPeriodId(): void
    {
        $this->resetPage();
        $this->month = null;
        $this->filterMonth = null;
        $this->resetState();
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
        return RkapPeriod::where('status', 'finalized')
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

        $options = [];
        foreach ($allMonths as $num => $name) {
            if (! in_array($num, $uploadedMonths, true)) {
                $options[$num] = $name;
            }
        }

        return $options;
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

        // Validate that the period is finalized
        $validPeriod = RkapPeriod::where('status', 'finalized')->find($this->periodId);

        if (!$validPeriod) {
            $this->errorsList[] = 'Realisasi hanya dapat diunggah untuk periode RKAP dengan status Finalized.';
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

            // Amount must be >= 0
            $rawAmount = $row['amount'] ?? '';
            $amount = $this->sanitizeAmount($rawAmount);
            if ($amount < 0) {
                $this->errorsList[] = "Baris {$rowNo}: amount tidak boleh negatif.";
            }
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
            }

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
            session()->flash('error', 'Anda tidak memiliki akses untuk menghapus data ini.');
            return;
        }

        $realization = RkapBudgetItemRealization::find($id);
        if ($realization) {
            $realization->delete();
            session()->flash('message', 'Data realisasi berhasil dihapus.');
        }
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
