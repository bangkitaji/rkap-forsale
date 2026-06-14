<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use App\Models\RkapBudgetItem;
use App\Models\Bureau;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;

class RkapProjections extends Component
{
    public ?int $activePeriodId = null;
    public ?int $prevPeriodId = null;
    public ?int $bureauId = null;

    public ?string $activePeriodTitle = null;
    public ?string $prevPeriodTitle = null;
    public ?RkapSubmission $submission = null;

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

        if (!$user || !$user->can('rkap.projection.input')) {
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
        } else {
            $this->activePeriodId = null;
            $this->activePeriodTitle = null;
        }

        // 2. Set default Bureau or restrict selector based on role
        if ($user->isKepalaBiro()) {
            $this->bureauId = $user->bureau_id;
            $this->loadProjections();
        } else {
            // Default to null, Admin/Verifikator must select a bureau
            $this->bureauId = null;
        }
    }

    public function updatedBureauId(): void
    {
        $this->loadProjections();
    }

    public function loadProjections(): void
    {
        $this->submission = null;

        if (!$this->bureauId || !$this->activePeriodId) {
            return;
        }

        // Fetch the approved submission for the active period for the selected bureau (current year finalized RKAP)
        $this->submission = RkapSubmission::with([
                'workPlans.budgetItems.realizations',
                'workPlans.budgetItems.projections',
                'period'
            ])
            ->where('bureau_id', $this->bureauId)
            ->where('rkap_period_id', $this->activePeriodId)
            ->where('status', 'approved')
            ->first();
    }

    public function getBureauOptionsProperty()
    {
        $user = Auth::user();

        if ($user->isKepalaBiro()) {
            return Bureau::where('id', $user->bureau_id)->get();
        }

        if ($user->isKepalaDepartemen()) {
            return Bureau::where('department_id', $user->department_id)
                ->orderBy('name')
                ->get();
        }

        if ($user->isDireksi()) {
            return Bureau::whereHas('department', fn($q) => $q->where('directorate_id', $user->directorate_id))
                ->orderBy('name')
                ->get();
        }

        // Admin and Verifikator see all active bureaus
        return Bureau::where('is_active', true)->orderBy('name')->get();
    }

    public function selectBudgetItem(int $id): void
    {
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

        DB::transaction(function () use ($currentYear): void {
            $realizations = \App\Models\RkapBudgetItemRealization::where('rkap_budget_item_id', $this->selectedBudgetItemId)->get();
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

        $this->loadProjections();
        $this->dispatch('close-projection-modal');
        session()->flash('message', 'Proyeksi RKAP berhasil disimpan.');
        $this->dispatch('projections-saved');
    }

    public function render(): View
    {
        return view('livewire.rkap.rkap-projections', [
            'bureauOptions' => $this->bureauOptions,
        ])->layout('layouts.contentNavbarLayout');
    }
}
