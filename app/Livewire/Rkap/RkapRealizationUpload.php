<?php

namespace App\Livewire\Rkap;

use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemRealization;
use App\Models\RkapPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RkapRealizationUpload extends Component
{
    use WithFileUploads;

    public $file;
    public ?int $periodId = null;

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

    public function getPeriodOptionsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return RkapPeriod::orderByDesc('year')->get();
    }

    public function uploadAndImport(): void
    {
        $this->resetState();

        $this->validate([
            'periodId' => 'required|integer|exists:rkap_periods,id',
            'file'     => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ], [
            'periodId.required' => 'Periode RKAP wajib dipilih sebelum upload.',
        ]);

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
    }

    private function resetState(): void
    {
        $this->errorsList    = [];
        $this->importSummary = [];
        $this->imported      = false;
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
            }

            // Amount must be >= 0
            $amount = (float) ($row['amount'] ?? -1);
            if ($amount < 0) {
                $this->errorsList[] = "Baris {$rowNo}: amount tidak boleh negatif.";
            }
        }
    }

    /**
     * Collect all rkap_budget_item ids that belong to submissions in the selected period.
     */
    private function getValidBudgetItemIds(): array
    {
        return RkapBudgetItem::whereHas('workPlan.submission', function ($q) {
            $q->where('rkap_period_id', $this->periodId);
        })->pluck('id')->map(fn ($v) => (int) $v)->toArray();
    }

    private function importRows(array $rows): void
    {
        DB::transaction(function () use ($rows): void {
            $created = 0;
            $updated = 0;

            foreach ($rows as $row) {
                $biId   = (int) $row['budget_item_id'];
                $month  = (int) $row['month'];
                $amount = (float) $row['amount'];

                $existing = RkapBudgetItemRealization::where('rkap_budget_item_id', $biId)
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

    public function render(): View
    {
        return view('livewire.rkap.rkap-realization-upload', [
            'periodOptions' => $this->periodOptions,
        ])->layout('layouts.contentNavbarLayout');
    }
}
