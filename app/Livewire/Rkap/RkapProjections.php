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

        $currentMonth = (int) date('n');

        // Initialize projection values based on realization (if past or filled) or monthly budget plan
        for ($m = 1; $m <= 12; $m++) {
            $realizationAmount = (float) ($budgetItem->realizations->where('month', $m)->sum('amount'));
            $hasRealization = $budgetItem->realizations->where('month', $m)->count() > 0;
            $monthlyBudget = (float) ($budgetItem->monthlies->where('month', $m)->first()?->amount ?? 0.00);

            if ($m < $currentMonth || $hasRealization) {
                $this->editingProjections[$m] = $realizationAmount;
            } else {
                $existing = $budgetItem->projections->where('month', $m)->first();
                $this->editingProjections[$m] = $existing ? (float) $existing->amount : $monthlyBudget;
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

        // Skip limit validation if the month is locked (past or has realization)
        $currentMonth = (int) date('n');
        $hasRealization = $selectedItem->realizations->where('month', $monthVal)->count() > 0;
        if ($monthVal < $currentMonth || $hasRealization) {
            $this->resetErrorBag("editingProjections.{$monthVal}");
        } else {
            // Check monthly budget plan limit
            $monthlyLimit = $selectedItem->monthlies->where('month', $monthVal)->first()?->amount ?? 0.00;
            $sanitizedValue = $value !== '' && $value !== null ? (float)$value : 0.00;
            if ($sanitizedValue > (float)$monthlyLimit) {
                $this->addError("editingProjections.{$monthVal}", "Proyeksi bulan {$monthVal} tidak boleh melebihi rencana anggaran bulanan (Rp " . number_format($monthlyLimit, 0, ',', '.') . ").");
                return;
            }
        }

        // Check total projections vs total RKAP budget, enforcing realization amounts for locked months
        $totalProjections = 0.00;
        for ($m = 1; $m <= 12; $m++) {
            $hasRealization = $selectedItem->realizations->where('month', $m)->count() > 0;
            $isLocked = $m < $currentMonth || $hasRealization;

            if ($isLocked) {
                $totalProjections += (float) ($selectedItem->realizations->where('month', $m)->sum('amount'));
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

            $currentMonth = (int) date('n');

            // Validate that individual month projections do not exceed monthly plans (excluding locked months)
            foreach ($this->editingProjections as $month => $amount) {
                $hasRealization = $selectedItem->realizations->where('month', $month)->count() > 0;
                $isLocked = $month < $currentMonth || $hasRealization;

                if ($isLocked) {
                    continue;
                }

                $monthlyLimit = $selectedItem->monthlies->where('month', $month)->first()?->amount ?? 0.00;
                $sanitizedAmount = $amount !== '' && $amount !== null ? (float)$amount : 0.00;
                if ($sanitizedAmount > (float)$monthlyLimit) {
                    $this->addError("editingProjections.{$month}", "Proyeksi bulan {$month} tidak boleh melebihi rencana anggaran bulanan (Rp " . number_format($monthlyLimit, 0, ',', '.') . ").");
                    return;
                }
            }

            // Validate that the total projections do not exceed the total RKAP budget, enforcing realization amounts for locked months
            $totalProjections = 0.00;
            for ($m = 1; $m <= 12; $m++) {
                $hasRealization = $selectedItem->realizations->where('month', $m)->count() > 0;
                $isLocked = $m < $currentMonth || $hasRealization;

                if ($isLocked) {
                    $totalProjections += (float) ($selectedItem->realizations->where('month', $m)->sum('amount'));
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

            DB::transaction(function () use ($selectedItem, $currentMonth): void {
                for ($m = 1; $m <= 12; $m++) {
                    $realizationAmount = (float) ($selectedItem->realizations->where('month', $m)->sum('amount'));
                    $hasRealization = $selectedItem->realizations->where('month', $m)->count() > 0;

                    if ($m < $currentMonth || $hasRealization) {
                        $amount = $realizationAmount;
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
