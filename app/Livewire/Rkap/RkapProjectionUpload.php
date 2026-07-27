<?php

namespace App\Livewire\Rkap;

use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemProjection;
use App\Models\RkapPeriod;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RkapProjectionUpload extends Component
{
    use WithFileUploads;

    public $file;
    public ?int $periodId = null;

    public array $errorsList    = [];
    public array $importSummary = [];
    public bool  $imported      = false;

    private array $requiredColumns = [
        'budget_item_id',
        'yearly',
        'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12'
    ];

    public function mount(): void
    {
        if (! auth()->user()?->can('rkap.projection.input') ||
            (! auth()->user()->hasRole('admin') && ! auth()->user()->hasRole('verifikator'))) {
            abort(403, __('Anda tidak memiliki akses untuk halaman ini.'));
        }
    }

    public function updatedPeriodId(): void
    {
        $this->resetState(keepPeriod: true);
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

    public function getIsProjectionClosedProperty(): bool
    {
        $status = Setting::get('rkap_projection_status', 'open');
        return in_array($status, ['closed', 'close'], true);
    }

    public function uploadAndImport(): void
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        $this->resetState(keepPeriod: true);

        if ($this->isProjectionClosed) {
            $this->errorsList[] = 'Penginputan dan perubahan data proyeksi saat ini sedang ditutup.';
            return;
        }

        $this->validate([
            'periodId' => 'required|integer|exists:rkap_periods,id',
            'file'     => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ], [
            'periodId.required' => 'Periode RKAP wajib dipilih sebelum upload.',
            'file.required'     => 'File proyeksi wajib dipilih sebelum upload.',
        ]);

        $validPeriod = RkapPeriod::where('status', 'finalized')->find($this->periodId);
        if (!$validPeriod) {
            $this->errorsList[] = 'Proyeksi hanya dapat diunggah untuk periode RKAP dengan status Finalized.';
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
        $this->file = null;
    }

    private function resetState(bool $keepPeriod = false): void
    {
        $this->errorsList    = [];
        $this->importSummary = [];
        $this->imported      = false;

        if (!$keepPeriod) {
            $this->periodId = null;
        }
    }

    private function parseFile(string $filePath, string $extension): array
    {
        $ext = strtolower($extension);

        if (in_array($ext, ['xlsx', 'xls'])) {
            return $this->parseExcel($filePath);
        }

        return $this->parseCsv($filePath);
    }

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
        $validBudgetItemIds = $this->getValidBudgetItemIds();
        $currentMonth = (int) date('n');
        $validPeriod = RkapPeriod::find($this->periodId);

        $budgetItemIds = array_filter(array_map(fn($r) => isset($r['budget_item_id']) && $r['budget_item_id'] !== '' ? (int)$r['budget_item_id'] : null, $rows));
        $budgetItems = RkapBudgetItem::with(['monthlies', 'projections'])
            ->whereIn('id', $budgetItemIds)
            ->get()
            ->keyBy('id');

        foreach ($rows as $row) {
            $rowNo = $row['_row_number'];

            if (! isset($row['budget_item_id']) || $row['budget_item_id'] === '') {
                $this->errorsList[] = "Baris {$rowNo}: kolom budget_item_id wajib diisi.";
            }

            $biId = $row['budget_item_id'] ?? null;
            if ($biId === null || $biId === '') {
                continue;
            }

            $biIdInt = (int) $biId;
            if (! in_array($biIdInt, $validBudgetItemIds, true) || ! isset($budgetItems[$biIdInt])) {
                $this->errorsList[] = "Baris {$rowNo}: budget_item_id {$biId} tidak ditemukan atau tidak termasuk dalam periode yang dipilih.";
                continue;
            }

            /** @var RkapBudgetItem $budgetItem */
            $budgetItem = $budgetItems[$biIdInt];
            $totalBudget = (float) $budgetItem->total_price;

            $hasMonthly = false;
            $monthlySum = 0.0;
            $hasRowAmountError = false;

            for ($m = 1; $m <= 12; $m++) {
                $colKey = "m{$m}";
                $valStr = $row[$colKey] ?? '0';
                $valFloat = $this->sanitizeAmount($valStr);

                if (!is_numeric(str_replace([' ', ','], '', $valStr)) && $valStr !== '') {
                    $this->errorsList[] = "Baris {$rowNo}: kolom {$colKey} harus berupa angka.";
                    $hasRowAmountError = true;
                    continue;
                }



                if (abs($valFloat) > 0.001) {
                    $hasMonthly = true;
                }
                $monthlySum += $valFloat;
            }

            if ($hasRowAmountError) {
                continue;
            }

            $yearlyValStr = $row['yearly'] ?? '0';
            if (!is_numeric(str_replace([' ', ','], '', $yearlyValStr)) && $yearlyValStr !== '') {
                $this->errorsList[] = "Baris {$rowNo}: kolom yearly harus berupa angka.";
                continue;
            }
            $yearlyVal = $this->sanitizeAmount($yearlyValStr);

            $dbHasMonthly = $budgetItem->projections->count() > 0;
            $dbHasYearly = (float)$budgetItem->projection > 0 && !$dbHasMonthly;

            $allowExceed = Setting::get('rkap_allow_projection_exceed_budget', '0') === '1';

            if ($hasMonthly) {
                if ($dbHasYearly) {
                    $this->errorsList[] = "Baris {$rowNo}: Tidak dapat mengisi proyeksi bulanan karena item ini sudah diatur dengan proyeksi tahunan.";
                    continue;
                }

                if (!$allowExceed && $monthlySum > $totalBudget) {
                    $this->errorsList[] = "Baris {$rowNo}: Total akumulasi proyeksi bulanan (Rp " . number_format($monthlySum, 0, ',', '.') . ") tidak boleh melebihi total anggaran RKAP yang disetujui (Rp " . number_format($totalBudget, 0, ',', '.') . ").";
                    continue;
                }

                if (abs($yearlyVal) > 0.001 && abs($yearlyVal - $monthlySum) > 0.01) {
                    $this->errorsList[] = "Baris {$rowNo}: Nilai kolom yearly (Rp " . number_format($yearlyVal, 0, ',', '.') . ") harus sama dengan total akumulasi bulanan (Rp " . number_format($monthlySum, 0, ',', '.') . ") jika mengisi proyeksi bulanan.";
                    continue;
                }

                for ($m = 1; $m <= 12; $m++) {
                    $colKey = "m{$m}";
                    $valFloat = $this->sanitizeAmount($row[$colKey] ?? '0');

                    $isPastMonth = $m < $currentMonth;
                    $isClosed = $validPeriod && $validPeriod->isMonthClosed($m);
                    if ($isClosed) {
                        $existingProjAmount = (float) ($budgetItem->projections->firstWhere('month', $m)->amount ?? 0.0);
                        if (abs($valFloat - $existingProjAmount) > 0.01) {
                            $closingDate = $validPeriod->getClosingDateForMonth($m);
                            $closingDateStr = $closingDate ? $closingDate->format('d M Y') : '';
                            $this->errorsList[] = "Baris {$rowNo}: proyeksi bulan {$m} (" . $this->getMonthName($m) . ") tidak dapat diubah karena periode pengisian telah ditutup ({$closingDateStr}).";
                            $hasRowAmountError = true;
                        }
                    }
                }
            } else {
                if (!$allowExceed && $yearlyVal > $totalBudget) {
                    $this->errorsList[] = "Baris {$rowNo}: Total proyeksi tahunan (Rp " . number_format($yearlyVal, 0, ',', '.') . ") tidak boleh melebihi total anggaran RKAP yang disetujui (Rp " . number_format($totalBudget, 0, ',', '.') . ").";
                    continue;
                }

                if (abs($yearlyVal) > 0.001) {
                    if ($dbHasMonthly) {
                        $this->errorsList[] = "Baris {$rowNo}: Tidak dapat mengisi proyeksi tahunan karena item ini sudah diatur dengan proyeksi bulanan.";
                        continue;
                    }
                }
            }
        }
    }

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
            $updated = 0;
            $currentMonth = (int) date('n');

            $budgetItemIds = array_map(fn($r) => (int)$r['budget_item_id'], $rows);
            $budgetItems = RkapBudgetItem::whereIn('id', $budgetItemIds)->get()->keyBy('id');

            foreach ($rows as $row) {
                $biId = (int) $row['budget_item_id'];
                $budgetItem = $budgetItems[$biId] ?? null;
                if (!$budgetItem) {
                    continue;
                }

                $hasMonthly = false;
                for ($m = 1; $m <= 12; $m++) {
                    $amount = $this->sanitizeAmount($row["m{$m}"] ?? '0');
                    if (abs($amount) > 0.001) {
                        $hasMonthly = true;
                    }
                }

                $validPeriod = RkapPeriod::find($this->periodId);
                if ($hasMonthly) {
                    for ($m = 1; $m <= 12; $m++) {
                        $isClosed = $validPeriod && $validPeriod->isMonthClosed($m);
                        if ($isClosed) {
                            continue;
                        }

                        $amount = $this->sanitizeAmount($row["m{$m}"] ?? '0');

                        RkapBudgetItemProjection::updateOrCreate(
                            [
                                'rkap_budget_item_id' => $biId,
                                'month'               => $m,
                            ],
                            [
                                'rkap_period_id' => $this->periodId,
                                'amount'         => $amount,
                                'inputted_by'    => auth()->id(),
                            ]
                        );
                    }

                    $totalProj = RkapBudgetItemProjection::where('rkap_budget_item_id', $biId)->sum('amount');
                    $budgetItem->update(['projection' => $totalProj]);
                } else {
                    $yearlyVal = $this->sanitizeAmount($row['yearly'] ?? '0');
                    $budgetItem->update(['projection' => $yearlyVal]);

                    RkapBudgetItemProjection::where('rkap_budget_item_id', $biId)->delete();
                }
                $updated++;
            }

            $this->importSummary = [
                'updated' => $updated,
            ];

            $this->imported = true;
        });
    }

    private function sanitizeAmount(string $raw): float
    {
        $s = trim($raw);

        if ($s === '' || $s === '-') {
            return 0.0;
        }

        $dotCount   = substr_count($s, '.');
        $commaCount = substr_count($s, ',');

        if ($dotCount > 0 && $commaCount > 0) {
            $lastDot   = strrpos($s, '.');
            $lastComma = strrpos($s, ',');

            if ($lastDot > $lastComma) {
                $s = str_replace(',', '', $s);
            } else {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            }
        } elseif ($dotCount > 1) {
            $s = str_replace('.', '', $s);
        } elseif ($commaCount > 1) {
            $s = str_replace(',', '', $s);
        } elseif ($commaCount === 1 && $dotCount === 0) {
            $s = str_replace(',', '.', $s);
        }

        return (float) $s;
    }

    public function render(): View
    {
        return view('livewire.rkap.rkap-projection-upload', [
            'periodOptions' => $this->periodOptions,
        ])->layout('layouts.contentNavbarLayout');
    }
}
