<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapSubmission;
use App\Models\RkapBudgetItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RkapReview extends Component
{
    public RkapSubmission $submission;
    public string $reviewComments = '';
    public string $revisionReason = '';
    public bool $showRevisionForm = false;
    public string $newComment = '';

    public function mount(int $id): void
    {
        $this->submission = RkapSubmission::with([
            'bureau.department.directorate',
            'period',
            'creator',
            'workPlans.budgetItems.monthlies',
            'workPlans.budgetItems.cashOuts',
            'versions.creator',
            'approvals.user',
            'comments' => fn($q) => $q->topLevel()->with(['user', 'replies.user']),
        ])->findOrFail($id);
    }

    public function approve(): void
    {
        $user = Auth::user();

        match (true) {
            $user->isKepalaDepartemen() => $this->submission->approveByDept($user, $this->reviewComments),
            $user->isDireksi()          => $this->submission->approveByDir($user, $this->reviewComments),
            $user->isVerifikator()      => $this->submission->approveFinal($user, $this->reviewComments),
            default => null,
        };

        // Advance status to next review stage
        $newStatus = match($this->submission->fresh()->status) {
            'dept_approved' => 'dir_review',
            'dir_approved'  => 'final_review',
            default         => null,
        };

        if ($newStatus) {
            $this->submission->update(['status' => $newStatus]);
        }

        $this->reviewComments = '';
        $this->submission->refresh()->load(['approvals.user', 'workPlans.budgetItems.monthlies', 'workPlans.budgetItems.cashOuts', 'versions.creator', 'comments.user', 'comments.replies.user']);
        session()->flash('message', 'RKAP berhasil disetujui.');
    }

    public function requestRevision(): void
    {
        $this->validate(['revisionReason' => 'required|string|min:10']);

        $user = Auth::user();

        match (true) {
            $user->isKepalaDepartemen() => $this->submission->requestRevisionByDept($user, $this->revisionReason),
            $user->isDireksi()          => $this->submission->requestRevisionByDir($user, $this->revisionReason),
            $user->isVerifikator()      => $this->submission->requestRevisionByVerificator($user, $this->revisionReason),
            default => null,
        };

        $this->submission->revise();

        $this->revisionReason = '';
        $this->showRevisionForm = false;
        $this->submission->refresh()->load(['approvals.user', 'workPlans.budgetItems.monthlies', 'workPlans.budgetItems.cashOuts', 'versions.creator', 'comments.user', 'comments.replies.user']);
        session()->flash('message', 'Permintaan revisi berhasil dikirim.');
    }

    public function addComment(): void
    {
        $this->validate(['newComment' => 'required|string|min:3']);

        $this->submission->comments()->create([
            'user_id'        => Auth::id(),
            'version_number' => $this->submission->current_version,
            'content'        => $this->newComment,
        ]);

        $this->newComment = '';
        $this->submission->refresh()->load(['comments' => fn($q) => $q->topLevel()->with(['user', 'replies.user'])]);
    }

    public function canApprove(): bool
    {
        $user = Auth::user();
        return $this->submission->canBeReviewedBy($user);
    }

    /**
     * Build a lookup map of the most recent prior approved submission
     * for the same bureau: [work_plan_id][account_code] => total_price
     */
    private function buildPreviousMap(): array
    {
        $bureauId = $this->submission->bureau_id;
        $currentPeriodYear = $this->submission->period?->year ?? 0;

        // Find the most recent prior approved submission for this bureau
        $prevSubmission = RkapSubmission::with([
                'workPlans.budgetItems.realizations',
                'workPlans.budgetItems.projections',
                'period',
            ])
            ->where('bureau_id', $bureauId)
            ->where('id', '!=', $this->submission->id)
            ->where('status', 'approved')
            ->whereHas('period', fn($q) => $q->where('year', '<', $currentPeriodYear))
            ->orderByDesc(DB::raw('(SELECT year FROM rkap_periods WHERE rkap_periods.id = rkap_submissions.rkap_period_id)'))
            ->first();

        if (!$prevSubmission) {
            return [];
        }

        $programs = [];
        $activities = [];
        $coas = [];

        foreach ($prevSubmission->workPlans as $wp) {
            $wpId = $wp->work_plan_id;
            $actId = $wp->activity_id;
            if (!$wpId) {
                continue;
            }

            if (!isset($programs[$wpId])) {
                $programs[$wpId] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
            }

            $actKey = "{$wpId}-{$actId}";
            if (!isset($activities[$actKey])) {
                $activities[$actKey] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
            }

            foreach ($wp->budgetItems as $bi) {
                $code = $bi->account_code;
                $budgetVal = (float) $bi->total_price;
                $realizationVal = (float) $bi->realizations->sum('amount');
                $projectionVal = (float) $bi->projections->sum('amount');

                $programs[$wpId]['budget'] += $budgetVal;
                $programs[$wpId]['realization'] += $realizationVal;
                $programs[$wpId]['projection'] += $projectionVal;

                $activities[$actKey]['budget'] += $budgetVal;
                $activities[$actKey]['realization'] += $realizationVal;
                $activities[$actKey]['projection'] += $projectionVal;

                if ($code) {
                    $coaKey = "{$wpId}-{$actId}-{$code}";
                    if (!isset($coas[$coaKey])) {
                        $coas[$coaKey] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
                    }
                    $coas[$coaKey]['budget'] += $budgetVal;
                    $coas[$coaKey]['realization'] += $realizationVal;
                    $coas[$coaKey]['projection'] += $projectionVal;
                }
            }
        }

        return [
            'map' => [
                'programs' => $programs,
                'activities' => $activities,
                'coas' => $coas,
            ],
            'period' => $prevSubmission->period?->title ?? '-',
        ];
    }

    public function render()
    {
        return view('livewire.rkap.rkap-review', [
            'prevData' => $this->buildPreviousMap(),
        ])->layout('layouts.contentNavbarLayout');
    }
}
