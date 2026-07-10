<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemProjection;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Services\AnalyticsCacheService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;

class RkapProjections extends Component
{
    public ?int $activePeriodId = null;
    public ?int $prevPeriodId = null;
    public ?int $directorateId = null;
    public ?int $departmentId = null;
    public ?int $bureauId = null;

    public ?string $activePeriodTitle = null;
    public ?string $prevPeriodTitle = null;

    public ?int $selectedBudgetItemId = null;
    public array $editingProjections = [];
    public string $inputMode = 'monthly';
    public ?float $yearlyProjection = null;
    public bool $modeLocked = false;

    protected $rules = [
        'editingProjections' => 'array',
        'editingProjections.*' => 'nullable|numeric|min:0',
        'inputMode' => 'required|in:monthly,yearly',
        'yearlyProjection' => 'nullable|numeric|min:0',
    ];

    protected $validationAttributes = [
        'editingProjections.*' => 'Nilai proyeksi bulanan',
        'yearlyProjection' => 'Nilai proyeksi tahunan',
    ];

    public function mount(): void
    {
        $user = Auth::user();

        if (!$user || !$user->can('rkap.projection.view')) {
            abort(403, 'Anda tidak memiliki akses untuk halaman ini.');
        }

        // 1. Resolve period for current year with status = finalized
        $currentYear = (int) date('Y');
        $activePeriod = RkapPeriod::where('year', $currentYear)
            ->where('status', 'finalized')
            ->first();

        if ($activePeriod) {
            $this->activePeriodId = $activePeriod->id;
            $this->activePeriodTitle = $activePeriod->title;

            $prevPeriod = RkapPeriod::where('year', '<', $activePeriod->year)
                ->orderByDesc('year')
                ->first();

            if ($prevPeriod) {
                $this->prevPeriodId = $prevPeriod->id;
                $this->prevPeriodTitle = $prevPeriod->title;
            }
        }

        // 2. Set default filters based on role
        if ($user->isKepalaBiro()) {
            $this->bureauId = $user->bureau_id;
            $this->departmentId = $user->department_id;
            $this->directorateId = $user->directorate_id;
        } elseif ($user->isKepalaDepartemen()) {
            $this->departmentId = $user->department_id;
            $this->directorateId = $user->directorate_id;
        } elseif ($user->isDireksi()) {
            $this->directorateId = $user->directorate_id;
        }
    }

    public function updatedDirectorateId(): void
    {
        $this->departmentId = null;
        $this->bureauId = null;
    }

    public function updatedDepartmentId(): void
    {
        $this->bureauId = null;
    }

    public function getDirectorateOptionsProperty()
    {
        $user = Auth::user();
        if ($user->isKepalaBiro() || $user->isKepalaDepartemen() || $user->isDireksi()) {
            return Directorate::where('id', $user->directorate_id)->get();
        }
        return Directorate::where('is_active', true)->orderBy('name')->get();
    }

    public function getDepartmentOptionsProperty()
    {
        $user = Auth::user();
        if ($user->isKepalaBiro() || $user->isKepalaDepartemen()) {
            return Department::where('id', $user->department_id)->get();
        }

        $query = Department::where('is_active', true);

        if ($this->directorateId) {
            $query->where('directorate_id', $this->directorateId);
        } elseif ($user->isDireksi()) {
            $query->where('directorate_id', $user->directorate_id);
        }

        return $query->orderBy('name')->get();
    }

    public function getBureauOptionsProperty()
    {
        $user = Auth::user();
        if ($user->isKepalaBiro()) {
            return Bureau::where('id', $user->bureau_id)->get();
        }

        $query = Bureau::where('is_active', true);

        if ($this->departmentId) {
            $query->where('department_id', $this->departmentId);
        } elseif ($this->directorateId) {
            $query->whereHas('department', fn($q) => $q->where('directorate_id', $this->directorateId));
        } else {
            if ($user->isKepalaDepartemen()) {
                $query->where('department_id', $user->department_id);
            } elseif ($user->isDireksi()) {
                $query->whereHas('department', fn($q) => $q->where('directorate_id', $user->directorate_id));
            }
        }

        return $query->orderBy('name')->get();
    }

    public function getSubmissions()
    {
        if (!$this->activePeriodId) {
            return collect();
        }

        $user = Auth::user();
        $targetBureauIds = [];

        if ($this->bureauId) {
            $targetBureauIds = [$this->bureauId];
        } elseif ($this->departmentId) {
            $targetBureauIds = Bureau::where('department_id', $this->departmentId)
                ->pluck('id')
                ->toArray();
        } elseif ($this->directorateId) {
            $targetBureauIds = Bureau::whereHas('department', fn($q) => $q->where('directorate_id', $this->directorateId))
                ->pluck('id')
                ->toArray();
        } else {
            // Defaults based on role
            if ($user->isKepalaBiro()) {
                $targetBureauIds = [$user->bureau_id];
            } elseif ($user->isKepalaDepartemen()) {
                $targetBureauIds = Bureau::where('department_id', $user->department_id)
                    ->pluck('id')
                    ->toArray();
            } elseif ($user->isDireksi()) {
                $targetBureauIds = Bureau::whereHas('department', fn($q) => $q->where('directorate_id', $user->directorate_id))
                    ->pluck('id')
                    ->toArray();
            } else {
                return collect();
            }
        }

        if (empty($targetBureauIds)) {
            return collect();
        }

        return RkapSubmission::with([
            'bureau',
            'workPlans.budgetItems.realizations',
            'workPlans.budgetItems.projections',
            'period'
        ])
            ->whereIn('bureau_id', $targetBureauIds)
            ->where('rkap_period_id', $this->activePeriodId)
            ->where('status', 'approved')
            ->get();
    }

    public function selectBudgetItem(int $id): void
    {
        if (!Auth::user()->can('rkap.projection.input')) {
            abort(403, 'Anda tidak memiliki akses untuk mengedit proyeksi.');
        }

        $this->selectedBudgetItemId = $id;
        $budgetItem = RkapBudgetItem::with(['projections', 'monthlies', 'realizations'])->find($id);

        $this->editingProjections = [];

        $activePeriod = RkapPeriod::find($this->activePeriodId);

        // Initialize projection values based on realization or closing period status
        for ($m = 1; $m <= 12; $m++) {
            $realizationAmount = (float) ($budgetItem->realizations->where('month', $m)->sum('amount'));
            $hasRealization = $budgetItem->realizations->where('month', $m)->count() > 0;
            $isClosed = $activePeriod && $activePeriod->isMonthClosed($m);

            if ($hasRealization) {
                // Month has realization: always use realization amount
                $this->editingProjections[$m] = $realizationAmount;
            } elseif ($isClosed) {
                // Month is closed but no realization: force projection = 0 (matches realization)
                $this->editingProjections[$m] = 0.00;
            } else {
                $existing = $budgetItem->projections->where('month', $m)->first();
                $monthlyPlan = $budgetItem->monthlies->where('month', $m)->first();
                $this->editingProjections[$m] = $existing ? (float) $existing->amount : (float) ($monthlyPlan?->amount ?? 0.00);
            }
        }

        // Determine inputMode and locked status based on current projections
        $hasMonthlyProjections = $budgetItem->projections->count() > 0;
        $hasYearlyProjection = (float)$budgetItem->projection > 0 && !$hasMonthlyProjections;

        if ($hasYearlyProjection) {
            $this->inputMode = 'yearly';
            $this->yearlyProjection = (float)$budgetItem->projection;
            $this->modeLocked = true;
        } elseif ($hasMonthlyProjections) {
            $this->inputMode = 'monthly';
            $this->yearlyProjection = (float)$budgetItem->projection;
            $this->modeLocked = true;
        } else {
            $this->inputMode = 'monthly';
            $this->yearlyProjection = 0.00;
            $this->modeLocked = false;
        }

        $this->dispatch('open-projection-modal');
    }

    public function updatedInputMode($value): void
    {
        $this->yearlyProjection = array_sum(array_map(fn($v) => is_numeric($v) ? (float)$v : 0.00, $this->editingProjections));
    }

    public function updatedYearlyProjection($value): void
    {
        $selectedItem = RkapBudgetItem::find($this->selectedBudgetItemId);
        if (!$selectedItem) {
            return;
        }

        if ($value !== '' && $value !== null && !is_numeric($value)) {
            $this->addError('yearlyProjection', 'Nilai proyeksi tahunan harus berupa angka.');
            return;
        } else {
            $this->resetErrorBag('yearlyProjection');
        }

        $sanitizedValue = $value !== '' && $value !== null ? (float)$value : 0.00;
        if ($sanitizedValue > (float)$selectedItem->total_price) {
            $this->addError('yearlyProjection', "Total proyeksi tahunan (Rp " . number_format($sanitizedValue, 0, ',', '.') . ") tidak boleh melebihi total anggaran RKAP yang disetujui (Rp " . number_format($selectedItem->total_price, 0, ',', '.') . ").");
        } else {
            $this->resetErrorBag('yearlyProjection');
        }
    }

    public function updatedEditingProjections($value, $key): void
    {
        $monthVal = (int) $key;
        $selectedItem = RkapBudgetItem::with(['monthlies', 'realizations'])->find($this->selectedBudgetItemId);
        if (!$selectedItem) {
            return;
        }

        // Run standard numeric validation first
        if ($value !== '' && $value !== null && !is_numeric($value)) {
            $this->addError("editingProjections.{$monthVal}", "Nilai proyeksi bulanan harus berupa angka.");
            return;
        } else {
            $this->resetErrorBag("editingProjections.{$monthVal}");
        }

        // Skip limit validation if the month is locked (has realization or is closed via closing period)
        $hasRealization = $selectedItem->realizations->where('month', $monthVal)->count() > 0;
        $period = $selectedItem->workPlan->submission->period;
        $isClosed = $period && $period->isMonthClosed($monthVal);

        if ($hasRealization || $isClosed) {
            $this->resetErrorBag("editingProjections.{$monthVal}");
            if ($isClosed && !$hasRealization) {
                $existing = $selectedItem->projections->where('month', $monthVal)->first();
                $existingAmount = $existing ? (float)$existing->amount : 0.00;
                $sanitizedValue = $value !== '' && $value !== null ? (float)$value : 0.00;
                if (abs($sanitizedValue - $existingAmount) > 0.01) {
                    $this->addError("editingProjections.{$monthVal}", "Proyeksi bulan {$monthVal} tidak dapat diubah karena periode pengisian telah ditutup.");
                }
            }
        }

        // Check total projections vs total RKAP budget, enforcing realization amounts for locked months
        $totalProjections = 0.00;
        for ($m = 1; $m <= 12; $m++) {
            $hasRealization = $selectedItem->realizations->where('month', $m)->count() > 0;
            $isClosed = $period && $period->isMonthClosed($m);

            if ($hasRealization) {
                $totalProjections += (float) ($selectedItem->realizations->where('month', $m)->sum('amount'));
            } elseif ($isClosed) {
                // Closed without realization: projection forced to 0
                $totalProjections += 0.00;
            } else {
                $totalProjections += isset($this->editingProjections[$m]) && $this->editingProjections[$m] !== '' && $this->editingProjections[$m] !== null
                    ? (float) $this->editingProjections[$m]
                    : (float) ($selectedItem->monthlies->where('month', $m)->first()?->amount ?? 0.00);
            }
        }

        if ($totalProjections > (float) $selectedItem->total_price) {
            $this->addError('editingProjections', "Total akumulasi proyeksi (Rp " . number_format($totalProjections, 0, ',', '.') . ") tidak boleh melebihi total anggaran RKAP yang disetujui (Rp " . number_format($selectedItem->total_price, 0, ',', '.') . ").");
        } else {
            $this->resetErrorBag('editingProjections');
        }

        // Sync yearly projection total
        $this->yearlyProjection = $totalProjections;
    }

    public function saveMonthlyProjections(): void
    {
        if (!$this->selectedBudgetItemId) {
            return;
        }

        $user = Auth::user();

        if (!$user || !$user->can('rkap.projection.input')) {
            session()->flash('error', 'Anda tidak memiliki akses untuk menyimpan proyeksi.');
            return;
        }

        // Validate that the period matches current year and status is finalized
        $currentYear = (int) date('Y');
        $validPeriod = RkapPeriod::where('year', $currentYear)
            ->where('status', 'finalized')
            ->first();

        if (!$validPeriod || $this->activePeriodId !== $validPeriod->id) {
            session()->flash('error', 'Proyeksi hanya dapat diinput untuk periode RKAP tahun ini (' . $currentYear . ') dengan status Finalized.');
            return;
        }

        $selectedItem = RkapBudgetItem::with(['projections', 'monthlies', 'realizations'])->find($this->selectedBudgetItemId);
        if (!$selectedItem) {
            return;
        }

        if ($this->inputMode === 'yearly') {
            // Check if any month in the active period is closed
            $hasClosedMonths = false;
            for ($m = 1; $m <= 12; $m++) {
                if ($validPeriod && $validPeriod->isMonthClosed($m)) {
                    $hasClosedMonths = true;
                    break;
                }
            }

            if ($hasClosedMonths) {
                $existingYearly = (float)$selectedItem->projection;
                $yearlyVal = $this->yearlyProjection !== '' && $this->yearlyProjection !== null ? (float)$this->yearlyProjection : 0.00;
                if (abs($yearlyVal - $existingYearly) > 0.01) {
                    $this->addError('yearlyProjection', 'Tidak dapat mengubah proyeksi tahunan karena terdapat bulan pada periode ini yang telah ditutup.');
                    return;
                }
            }

            // Validate yearly projection
            $yearlyVal = $this->yearlyProjection !== '' && $this->yearlyProjection !== null ? (float)$this->yearlyProjection : 0.00;
            if ($yearlyVal > (float)$selectedItem->total_price) {
                $this->addError('yearlyProjection', "Total proyeksi tahunan (Rp " . number_format($yearlyVal, 0, ',', '.') . ") tidak boleh melebihi total anggaran RKAP yang disetujui (Rp " . number_format($selectedItem->total_price, 0, ',', '.') . ").");
                return;
            }

            DB::transaction(function () use ($selectedItem, $yearlyVal): void {
                $selectedItem->update(['projection' => $yearlyVal]);

                // Delete any monthly projections
                RkapBudgetItemProjection::where('rkap_budget_item_id', $this->selectedBudgetItemId)->delete();
            });
        } else {
            $this->validate();

            $period = $selectedItem->workPlan->submission->period;

            // Validate that individual month projections (excluding locked months)
            foreach ($this->editingProjections as $month => $amount) {
                $hasRealization = $selectedItem->realizations->where('month', $month)->count() > 0;
                $isClosed = $period && $period->isMonthClosed($month);

                if ($isClosed && !$hasRealization) {
                    $existing = $selectedItem->projections->where('month', $month)->first();
                    $existingAmount = $existing ? (float)$existing->amount : 0.00;
                    $sanitizedAmount = $amount !== '' && $amount !== null ? (float)$amount : 0.00;
                    if (abs($sanitizedAmount - $existingAmount) > 0.01) {
                        $this->addError("editingProjections.{$month}", "Proyeksi bulan {$month} tidak dapat diubah karena periode pengisian telah ditutup.");
                        return;
                    }
                }
            }

            // Validate that the total projections do not exceed the total RKAP budget, enforcing realization amounts for locked months
            $totalProjections = 0.00;
            for ($m = 1; $m <= 12; $m++) {
                $hasRealization = $selectedItem->realizations->where('month', $m)->count() > 0;
                $isClosed = $period && $period->isMonthClosed($m);

                if ($hasRealization) {
                    $totalProjections += (float) ($selectedItem->realizations->where('month', $m)->sum('amount'));
                } elseif ($isClosed) {
                    // Closed without realization: projection forced to 0
                    $totalProjections += 0.00;
                } else {
                    $totalProjections += isset($this->editingProjections[$m]) && $this->editingProjections[$m] !== '' && $this->editingProjections[$m] !== null
                        ? (float) $this->editingProjections[$m]
                        : (float) ($selectedItem->monthlies->where('month', $m)->first()?->amount ?? 0.00);
                }
            }

            if ($totalProjections > (float) $selectedItem->total_price) {
                $this->addError('editingProjections', "Total akumulasi proyeksi (Rp " . number_format($totalProjections, 0, ',', '.') . ") tidak boleh melebihi total anggaran RKAP yang disetujui (Rp " . number_format($selectedItem->total_price, 0, ',', '.') . ").");
                return;
            }

            DB::transaction(function () use ($selectedItem, $period): void {
                for ($m = 1; $m <= 12; $m++) {
                    $realizationAmount = (float) ($selectedItem->realizations->where('month', $m)->sum('amount'));
                    $hasRealization = $selectedItem->realizations->where('month', $m)->count() > 0;
                    $isClosed = $period && $period->isMonthClosed($m);

                    if ($hasRealization) {
                        // Has realization: use realization amount as projection
                        $amount = $realizationAmount;
                    } elseif ($isClosed) {
                        // Closed but no realization: force projection = 0 (matches realization)
                        $amount = 0.00;
                    } else {
                        $amount = isset($this->editingProjections[$m]) && $this->editingProjections[$m] !== '' && $this->editingProjections[$m] !== null
                            ? (float) $this->editingProjections[$m]
                            : (float) ($selectedItem->monthlies->where('month', $m)->first()?->amount ?? 0.00);
                    }

                    \App\Models\RkapBudgetItemProjection::updateOrCreate(
                        [
                            'rkap_budget_item_id' => $this->selectedBudgetItemId,
                            'month' => $m,
                        ],
                        [
                            'rkap_period_id' => $this->activePeriodId,
                            'amount' => $amount,
                            'inputted_by' => auth()->id(),
                        ]
                    );
                }

                // Update the yearly projection column in budget item table to match sum of all monthly projections
                $totalProj = RkapBudgetItemProjection::where('rkap_budget_item_id', $this->selectedBudgetItemId)->sum('amount');
                $selectedItem->update(['projection' => $totalProj]);
            });
        }

        $this->dispatch('close-projection-modal');
        // Flush analytics cache so dashboard reflects updated projections
        if ($this->activePeriodId) {
            AnalyticsCacheService::flushPeriod($this->activePeriodId);
        }
        session()->flash('message', 'Proyeksi RKAP berhasil disimpan.');
        $this->dispatch('projections-saved');
    }

    public function render(): View
    {
        return view('livewire.rkap.rkap-projections', [
            'directorateOptions' => $this->directorateOptions,
            'departmentOptions' => $this->departmentOptions,
            'bureauOptions' => $this->bureauOptions,
            'submissions' => $this->getSubmissions(),
        ])->layout('layouts.contentNavbarLayout');
    }
}
