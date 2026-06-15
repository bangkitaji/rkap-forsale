<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use App\Models\RkapBudgetItem;
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

    protected $rules = [
        'editingProjections' => 'array',
        'editingProjections.*' => 'nullable|numeric|min:0',
    ];

    protected $validationAttributes = [
        'editingProjections.*' => 'Nilai proyeksi bulanan',
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
        $budgetItem = RkapBudgetItem::with(['projections'])->find($id);
        
        $this->editingProjections = [];

        // Initialize projection values from database or default to 0 for all 12 months
        for ($m = 1; $m <= 12; $m++) {
            $existing = $budgetItem->projections->where('month', $m)->first();
            $this->editingProjections[$m] = $existing ? (float) $existing->amount : 0.00;
        }

        $this->dispatch('open-projection-modal');
    }

    public function updatedEditingProjections($value, $key): void
    {
        $monthVal = (int) $key;
        $selectedItem = RkapBudgetItem::find($this->selectedBudgetItemId);
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

        // Check total projections vs total RKAP budget
        $totalProjections = array_sum(array_map(fn($v) => is_numeric($v) ? (float)$v : 0.00, $this->editingProjections));
        if ($totalProjections > (float) $selectedItem->total_price) {
            $this->addError('editingProjections', "Total akumulasi proyeksi (Rp " . number_format($totalProjections, 0, ',', '.') . ") tidak boleh melebihi total anggaran RKAP yang disetujui (Rp " . number_format($selectedItem->total_price, 0, ',', '.') . ").");
        } else {
            $this->resetErrorBag('editingProjections');
        }
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

        $this->validate();

        $selectedItem = RkapBudgetItem::with(['projections'])->find($this->selectedBudgetItemId);
        if (!$selectedItem) {
            return;
        }

        // Validate that the total projections do not exceed the total RKAP budget
        $totalProjections = array_sum(array_map(fn($v) => $v !== '' && $v !== null ? (float)$v : 0.00, $this->editingProjections));
        if ($totalProjections > (float) $selectedItem->total_price) {
            $this->addError('editingProjections', "Total akumulasi proyeksi (Rp " . number_format($totalProjections, 0, ',', '.') . ") tidak boleh melebihi total anggaran RKAP yang disetujui (Rp " . number_format($selectedItem->total_price, 0, ',', '.') . ").");
            return;
        }

        DB::transaction(function () use ($currentYear): void {
            $projections = \App\Models\RkapBudgetItemProjection::where('rkap_budget_item_id', $this->selectedBudgetItemId)->get();

            foreach ($this->editingProjections as $month => $amount) {
                if ($month < (int) date('n') || $month > 12) {
                    continue;
                }

                // Skip if projection already exists with amount > 0
                $existingProj = $projections->where('month', $month)->first();
                if ($existingProj && (float)$existingProj->amount > 0) {
                    continue;
                }

                $sanitizedAmount = $amount !== '' && $amount !== null ? (float) $amount : 0.00;

                \App\Models\RkapBudgetItemProjection::updateOrCreate(
                    [
                        'rkap_budget_item_id' => $this->selectedBudgetItemId,
                        'month' => $month,
                    ],
                    [
                        'rkap_period_id' => $this->activePeriodId,
                        'amount' => $sanitizedAmount,
                        'inputted_by' => auth()->id(),
                    ]
                );
            }
        });

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
