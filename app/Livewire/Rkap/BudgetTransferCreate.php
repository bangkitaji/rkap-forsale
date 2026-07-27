<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use App\Models\Bureau;
use App\Services\BudgetTransferService;
use Illuminate\Support\Facades\Auth;
use App\Models\Setting;
use Exception;

class BudgetTransferCreate extends Component
{
    public ?int $periodId = null;
    public ?int $targetBureauId = null;
    public string $notes = '';
    public array $selectedItems = []; // [budgetItemId => bool]
    public array $transferAmounts = []; // [budgetItemId => float|string]

    public function updatedPeriodId(): void
    {
        $this->selectedItems = [];
        $this->transferAmounts = [];
    }

    public function toggleSelectItem(int $budgetItemId, float $defaultAmount): void
    {
        if (!isset($this->transferAmounts[$budgetItemId]) || empty($this->transferAmounts[$budgetItemId])) {
            $this->transferAmounts[$budgetItemId] = $defaultAmount;
        }
    }

    public function setFullBudget(int $budgetItemId, float $totalPrice): void
    {
        $this->selectedItems[$budgetItemId] = true;
        $this->transferAmounts[$budgetItemId] = $totalPrice;
    }

    public function selectAllFullBudget(): void
    {
        if (!$this->periodId) return;

        $user = Auth::user();
        if (!$user || !$user->bureau_id) return;

        $submission = RkapSubmission::where('rkap_period_id', $this->periodId)
            ->where('bureau_id', $user->bureau_id)
            ->where('status', \App\Enums\SubmissionStatus::Approved->value)
            ->first();

        if (!$submission) return;

        $workPlans = RkapWorkPlan::where('rkap_submission_id', $submission->id)
            ->with('budgetItems')
            ->get();

        foreach ($workPlans as $wp) {
            if ($wp->isLockedForTransfer()) continue;
            foreach ($wp->budgetItems as $bi) {
                $isPending = \App\Models\BudgetTransferItem::where('rkap_budget_item_id', $bi->id)
                    ->whereHas('transfer', fn($q) => $q->where('status', \App\Enums\BudgetTransferStatus::Pending->value))
                    ->exists();
                if ($isPending) continue;

                $this->selectedItems[$bi->id] = true;
                $this->transferAmounts[$bi->id] = (float) $bi->total_price;
            }
        }
    }

    public function submit(BudgetTransferService $service)
    {
        if (Setting::get('rkap_submission_status', 'open') === 'closed') {
            session()->flash('error', __('Pengisian usulan RKAP sedang ditutup. Anda tidak dapat mengajukan transfer budget.'));
            return;
        }

        $user = Auth::user();
        if (!$user || !$user->bureau_id) {
            session()->flash('error', __('Anda harus terasosiasi dengan Biro untuk mengajukan transfer.'));
            return;
        }

        $this->validate([
            'periodId' => 'required|integer',
            'targetBureauId' => 'required|integer',
            'notes' => 'nullable|string|max:500',
        ], [
            'periodId.required' => __('Periode RKAP wajib dipilih.'),
            'targetBureauId.required' => __('Biro tujuan wajib dipilih.'),
        ]);

        $selectedItemIds = array_keys(array_filter($this->selectedItems));

        if (empty($selectedItemIds)) {
            session()->flash('error', __('Pilih minimal satu kegiatan/item anggaran untuk ditransfer.'));
            return;
        }

        $itemsData = [];
        foreach ($selectedItemIds as $itemId) {
            $bi = RkapBudgetItem::find($itemId);
            if (!$bi) {
                continue;
            }
            $amount = isset($this->transferAmounts[$itemId]) && $this->transferAmounts[$itemId] !== ''
                ? (float) $this->transferAmounts[$itemId]
                : (float) $bi->total_price;

            if ($amount <= 0) {
                session()->flash('error', __('Nominal transfer untuk kegiatan "' . $bi->description . '" harus lebih besar dari 0.'));
                return;
            }
            if ($amount > (float) $bi->total_price) {
                session()->flash('error', __('Nominal transfer untuk kegiatan "' . $bi->description . '" tidak boleh melebihi budget yang tersedia (Rp ' . number_format($bi->total_price, 0, ',', '.') . ').'));
                return;
            }

            $itemsData[] = [
                'work_plan_id' => $bi->rkap_work_plan_id,
                'budget_item_id' => $bi->id,
                'amount_transferred' => $amount,
            ];
        }

        try {
            $service->createTransfer($user, $this->periodId, $this->targetBureauId, $itemsData, $this->notes);
            session()->flash('message', __('Pengajuan transfer budget berhasil dibuat dan menunggu approval dari biro tujuan.'));
            return redirect()->route('rkap-budget-transfers');
        } catch (Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $user = Auth::user();
        $currentYear = (int) date('Y');

        if (!$user || !$user->bureau_id) {
            return view('livewire.rkap.budget-transfer-create', [
                'periods' => collect(),
                'targetBureaus' => collect(),
                'workPlans' => collect(),
            ])->layout('layouts.contentNavbarLayout');
        }

        // Available periods: must have an approved submission for user's bureau in current or previous year
        $periods = RkapPeriod::whereIn('year', [$currentYear, $currentYear - 1])
            ->whereHas('submissions', function ($q) use ($user) {
                $q->where('bureau_id', $user->bureau_id)
                  ->where('status', \App\Enums\SubmissionStatus::Approved->value);
            })
            ->orderByDesc('year')
            ->get();

        // Target bureaus: active bureaus in same department, excluding own bureau
        $targetBureaus = Bureau::active()
            ->where('department_id', $user->bureau->department_id)
            ->where('id', '!=', $user->bureau_id)
            ->orderBy('name')
            ->get();

        // Load work plans of approved submission for selected period
        $workPlans = collect();
        if ($this->periodId) {
            $submission = RkapSubmission::where('rkap_period_id', $this->periodId)
                ->where('bureau_id', $user->bureau_id)
                ->where('status', \App\Enums\SubmissionStatus::Approved->value)
                ->first();

            if ($submission) {
                $workPlans = RkapWorkPlan::where('rkap_submission_id', $submission->id)
                    ->with(['workPlan', 'budgetItems'])
                    ->get();
            }
        }

        return view('livewire.rkap.budget-transfer-create', [
            'periods' => $periods,
            'targetBureaus' => $targetBureaus,
            'workPlans' => $workPlans,
        ])->layout('layouts.contentNavbarLayout');
    }
}
