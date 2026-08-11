<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapSubmission;
use App\Models\RkapBudgetItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;

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
            'workPlans.activityFiles',
            'workPlans.budgetItems.monthlies',
            'workPlans.budgetItems.cashOuts',
            'workPlans.budgetItems.coa.coaGroup',
            'workPlans.budgetItems.coa.cashflowGroup',
            'workPlans.budgetItems.coa.differenceGroups',
            'versions.creator',
            'approvals.user',
        ])->findOrFail($id);

        $this->submission->calculateTotalBudget();
    }

    public function approve(): void
    {
        if (Setting::get('rkap_submission_status', 'open') === 'closed' || ($this->submission->period && !$this->submission->period->isOpen())) {
            session()->flash('error', __('Pengisian usulan RKAP sedang ditutup atau periode tidak dalam status Open. Anda tidak dapat menyetujui usulan.'));
            return;
        }

        $user = Auth::user();

        match (true) {
            $user->isKepalaDepartemen() => $this->submission->approveByDept($user, $this->reviewComments),
            ($user->isDirekturFinance() && $this->submission->status === 'pdir_review') => $this->submission->approveByFinance($user, $this->reviewComments),
            $user->isDireksi()          => $this->submission->approveByDir($user, $this->reviewComments),
            $user->isVerifikator()      => $this->submission->approveFinal($user, $this->reviewComments),
            $user->isPresidentDirector() => $this->submission->approveByPresident($user, $this->reviewComments),
            default => null,
        };

        // Advance status to next review stage
        $newStatus = match($this->submission->fresh()->status) {
            'dept_approved' => 'dir_review',
            'dir_approved'  => 'final_review',
            'verifikator_approved' => 'pdir_review',
            default         => null,
        };

        if ($newStatus) {
            $this->submission->update(['status' => $newStatus]);
        }

        $this->reviewComments = '';
        $this->submission->refresh()->load(['approvals.user', 'workPlans.budgetItems.monthlies', 'workPlans.budgetItems.cashOuts', 'workPlans.budgetItems.coa.coaGroup', 'workPlans.budgetItems.coa.cashflowGroup', 'workPlans.budgetItems.coa.differenceGroups', 'versions.creator', 'comments.user', 'comments.replies.user']);
        session()->flash('message', __('RKAP berhasil disetujui.'));
    }

    public function requestRevision(): void
    {
        if (Setting::get('rkap_submission_status', 'open') === 'closed' || ($this->submission->period && !$this->submission->period->isOpen())) {
            session()->flash('error', __('Pengisian usulan RKAP sedang ditutup atau periode tidak dalam status Open. Anda tidak dapat meminta revisi.'));
            return;
        }

        $this->validate(['revisionReason' => 'required|string|min:10']);

        $user = Auth::user();

        match (true) {
            $user->isKepalaDepartemen() => $this->submission->requestRevisionByDept($user, $this->revisionReason),
            ($user->isDirekturFinance() && $this->submission->status === 'pdir_review') => $this->submission->requestRevisionByFinance($user, $this->revisionReason),
            $user->isDireksi()          => $this->submission->requestRevisionByDir($user, $this->revisionReason),
            $user->isVerifikator()      => $this->submission->requestRevisionByVerificator($user, $this->revisionReason),
            $user->isPresidentDirector() => $this->submission->requestRevisionByPresident($user, $this->revisionReason),
            default => null,
        };

        $this->submission->revise();

        $this->revisionReason = '';
        $this->showRevisionForm = false;
        $this->submission->refresh()->load(['approvals.user', 'workPlans.budgetItems.monthlies', 'workPlans.budgetItems.cashOuts', 'workPlans.budgetItems.coa.coaGroup', 'workPlans.budgetItems.coa.cashflowGroup', 'workPlans.budgetItems.coa.differenceGroups', 'versions.creator', 'comments.user', 'comments.replies.user']);
        session()->flash('message', __('RKAP berhasil ditolak dan dikembalikan untuk revisi.'));
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

        $service = app(\App\Services\RkapPreviousDataService::class);
        $prevSubmission = $service->getPreviousApprovedSubmission($bureauId, $currentPeriodYear, $this->submission->id);
        return $service->buildPreviousMap($prevSubmission);
    }

    public function render()
    {
        return view('livewire.rkap.rkap-review', [
            'prevData' => $this->buildPreviousMap(),
        ])->layout('layouts.contentNavbarLayout');
    }
}
