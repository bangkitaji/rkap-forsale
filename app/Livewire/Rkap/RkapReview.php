<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapSubmission;
use Illuminate\Support\Facades\Auth;

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

    public function render()
    {
        return view('livewire.rkap.rkap-review')
            ->layout('layouts.contentNavbarLayout');
    }
}
