<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\Bureau;
use App\Services\BudgetTransferService;
use Illuminate\Support\Facades\Auth;
use Exception;

class BudgetTransferCreate extends Component
{
    public ?int $periodId = null;
    public ?int $targetBureauId = null;
    public string $notes = '';
    public array $selectedWorkPlans = []; // [wpId => boolean]

    public function updatedPeriodId(): void
    {
        $this->selectedWorkPlans = [];
    }

    public function submit(BudgetTransferService $service)
    {
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

        $selectedWpIds = array_keys(array_filter($this->selectedWorkPlans));

        if (empty($selectedWpIds)) {
            session()->flash('error', __('Pilih minimal satu program kerja atau kegiatan untuk ditransfer.'));
            return;
        }

        try {
            $service->createTransfer($user, $this->periodId, $this->targetBureauId, $selectedWpIds, $this->notes);
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
                    ->with('budgetItems')
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
