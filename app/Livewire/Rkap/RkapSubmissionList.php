<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\RkapSubmission;
use App\Models\RkapPeriod;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RkapSubmissionExport;

class RkapSubmissionList extends Component
{
    use WithCustomPagination;

    public string $search = '';
    public string $filterStatus = '';
    public ?int $filterPeriod = null;
    public bool $showPeriodSelector = false;

    public bool $showDuplicateModal = false;
    public ?int $selectedDestinationPeriodId = null;
    public ?int $selectedSourceSubmissionId = null;

    public function openPeriodSelector(): void
    {
        $this->showPeriodSelector = true;
    }

    public function closePeriodSelector(): void
    {
        $this->showPeriodSelector = false;
    }

    public function selectPeriod(int $periodId): void
    {
        $this->redirectRoute('rkap-submissions-create', ['periodId' => $periodId]);
    }

    public function openDuplicateModal(int $destinationPeriodId): void
    {
        $this->selectedDestinationPeriodId = $destinationPeriodId;
        $this->showDuplicateModal = true;
        $this->showPeriodSelector = false;
    }

    public function closeDuplicateModal(): void
    {
        $this->showDuplicateModal = false;
        $this->selectedDestinationPeriodId = null;
        $this->selectedSourceSubmissionId = null;
    }

    public function duplicateSubmission(): void
    {
        $user = Auth::user();
        if (!$user || !$user->bureau_id) {
            session()->flash('error', 'Anda harus terasosiasi dengan Biro untuk menduplikasi pengajuan.');
            $this->closeDuplicateModal();
            return;
        }

        if (!$this->selectedDestinationPeriodId) {
            session()->flash('error', 'Periode tujuan belum dipilih.');
            $this->closeDuplicateModal();
            return;
        }

        if (!$this->selectedSourceSubmissionId) {
            session()->flash('error', 'Silakan pilih pengajuan sumber yang ingin diduplikasi.');
            return;
        }

        $destinationPeriod = RkapPeriod::findOrFail($this->selectedDestinationPeriodId);
        if (!$destinationPeriod->isOpen()) {
            session()->flash('error', 'Periode tujuan harus berstatus Open.');
            $this->closeDuplicateModal();
            return;
        }

        $sourceSubmission = RkapSubmission::findOrFail($this->selectedSourceSubmissionId);
        if ($sourceSubmission->bureau_id !== $user->bureau_id) {
            session()->flash('error', 'Anda hanya dapat menduplikasi pengajuan milik Biro Anda.');
            $this->closeDuplicateModal();
            return;
        }

        if ($sourceSubmission->rkap_period_id === $destinationPeriod->id) {
            session()->flash('error', 'Tidak dapat menduplikasi ke periode yang sama.');
            return;
        }

        $alreadyExists = RkapSubmission::where('rkap_period_id', $destinationPeriod->id)
            ->where('bureau_id', $user->bureau_id)
            ->exists();
        if ($alreadyExists) {
            session()->flash('error', 'Biro Anda sudah memiliki pengajuan untuk periode tersebut.');
            $this->closeDuplicateModal();
            return;
        }

        DB::transaction(function () use ($sourceSubmission, $destinationPeriod, $user) {
            $newSubmission = RkapSubmission::create([
                'rkap_period_id' => $destinationPeriod->id,
                'bureau_id' => $user->bureau_id,
                'created_by' => $user->id,
                'current_version' => 1,
                'status' => 'draft',
                'total_budget' => 0,
                'notes' => 'Duplikasi dari periode: ' . $sourceSubmission->period->title,
            ]);

            foreach ($sourceSubmission->workPlans as $wp) {
                $newWp = RkapWorkPlan::create([
                    'rkap_submission_id' => $newSubmission->id,
                    'work_plan_id' => $wp->work_plan_id,
                    'activity_id' => $wp->activity_id,
                    'program_code' => $wp->program_code,
                    'program_name' => $wp->program_name,
                    'description' => $wp->description,
                    'output_target' => $wp->output_target,
                    'unit' => $wp->unit,
                    'quantity' => $wp->quantity,
                    'sort_order' => $wp->sort_order,
                ]);

                foreach ($wp->budgetItems as $bi) {
                    $newBi = RkapBudgetItem::create([
                        'rkap_work_plan_id' => $newWp->id,
                        'account_code' => $bi->account_code,
                        'description' => $bi->description,
                        'unit' => $bi->unit,
                        'quantity' => $bi->quantity,
                        'unit_price' => $bi->unit_price,
                        'total_price' => $bi->total_price,
                        'remarks' => $bi->remarks,
                    ]);

                    foreach ($bi->monthlies as $monthly) {
                        $newBi->monthlies()->create([
                            'month' => $monthly->month,
                            'amount' => $monthly->amount,
                        ]);
                    }

                    foreach ($bi->cashOuts as $cashOut) {
                        $newBi->cashOuts()->create([
                            'month' => $cashOut->month,
                            'amount' => $cashOut->amount,
                        ]);
                    }
                }
            }

            $newSubmission->calculateTotalBudget();

            session()->flash('message', 'Pengajuan berhasil diduplikasi ke periode baru.');
            $this->redirectRoute('rkap-submissions-edit', ['id' => $newSubmission->id]);
        });
    }

    public function exportExcel(int $submissionId)
    {
        $user = Auth::user();

        $query = RkapSubmission::query()->where('id', $submissionId);

        if ($user->isKepalaBiro()) {
            $query->where('bureau_id', $user->bureau_id);
        } elseif ($user->isKepalaDepartemen()) {
            $query->whereHas('bureau', fn($b) => $b->where('department_id', $user->department_id));
        } elseif ($user->isDireksi()) {
            $query->whereHas('bureau.department', fn($d) => $d->where('directorate_id', $user->directorate_id));
        }
        // Verifikator and Admin see all

        $submission = $query->first();

        if (!$submission) {
            session()->flash('error', 'Pengajuan tidak ditemukan atau Anda tidak memiliki akses.');
            return null;
        }

        $filename = 'rkap-export-' . ($submission->period->year ?? date('Y')) . '-submission-' . $submission->id . '.xlsx';

        return Excel::download(new RkapSubmissionExport($submission), $filename);
    }

    public function render()
    {
        $user = Auth::user();

        $query = RkapSubmission::with(['bureau.department.directorate', 'period', 'creator'])
            ->search('bureau.name|bureau.code|period.title', $this->search)
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPeriod, fn($q) => $q->where('rkap_period_id', $this->filterPeriod));

        // Role-based scoping
        if ($user->isKepalaBiro()) {
            $query->where('bureau_id', $user->bureau_id);
        } elseif ($user->isKepalaDepartemen()) {
            $query->whereHas('bureau', fn($b) => $b->where('department_id', $user->department_id));
        } elseif ($user->isDireksi() && !$user->isDirekturFinance()) {
            $query->whereHas('bureau.department', fn($d) => $d->where('directorate_id', $user->directorate_id));
        }
        // Verifikator, Admin, President Director, and Direktur Finance see all

        $submissions = $query->orderByDesc('updated_at')->paginate($this->perPage);

        // Build map of previous approved submission totals and realizations for the submissions on the current page using batch service to fix N+1
        $prevDataMap = app(\App\Services\RkapPreviousDataService::class)->getBatchPreviousData($submissions);

        // Stats
        $statsQuery = RkapSubmission::query();
        if ($user->isKepalaBiro()) {
            $statsQuery->where('bureau_id', $user->bureau_id);
        } elseif ($user->isKepalaDepartemen()) {
            $statsQuery->whereHas('bureau', fn($b) => $b->where('department_id', $user->department_id));
        } elseif ($user->isDireksi() && !$user->isDirekturFinance()) {
            $statsQuery->whereHas('bureau.department', fn($d) => $d->where('directorate_id', $user->directorate_id));
        }

        $stats = [
            'total'       => (clone $statsQuery)->count(),
            'pending'     => (clone $statsQuery)->whereIn('status', ['submitted', 'dept_review', 'dir_review', 'final_review', 'verifikator_approved', 'pdir_review'])->count(),
            'revision'    => (clone $statsQuery)->whereIn('status', ['dept_revision', 'dir_revision', 'final_revision', 'pdir_revision'])->count(),
            'approved'    => (clone $statsQuery)->where('status', 'approved')->count(),
        ];

        $periods = RkapPeriod::orderByDesc('year')->get();
        $activePeriods = RkapPeriod::active()->orderByDesc('year')->get();

        $submittedPeriodIds = [];
        $previousSubmissions = [];
        $bureauSubmissions = collect();
        if ($user->bureau_id) {
            $bureauSubmissions = RkapSubmission::where('bureau_id', $user->bureau_id)
                ->get()
                ->keyBy('rkap_period_id');

            $submittedPeriodIds = $bureauSubmissions->keys()->toArray();

            $previousSubmissions = RkapSubmission::with('period')
                ->where('bureau_id', $user->bureau_id)
                ->orderByDesc('updated_at')
                ->get();
        }

        return view('livewire.rkap.rkap-submission-list', [
            'submissions'         => $submissions,
            'periods'             => $periods,
            'activePeriods'       => $activePeriods,
            'stats'               => $stats,
            'submittedPeriodIds'  => $submittedPeriodIds,
            'bureauSubmissions'   => $bureauSubmissions,
            'previousSubmissions' => $previousSubmissions,
            'prevDataMap'         => $prevDataMap,
        ])->layout('layouts.contentNavbarLayout');
    }
}
