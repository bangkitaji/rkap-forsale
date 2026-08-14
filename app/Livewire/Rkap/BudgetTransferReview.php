<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\BudgetTransfer;
use App\Services\BudgetTransferService;
use Illuminate\Support\Facades\Auth;
use App\Models\Setting;
use App\Enums\BudgetTransferStatus;
use Exception;

class BudgetTransferReview extends Component
{
    public BudgetTransfer $transfer;
    public string $reviewNotes = '';

    public function mount(int $id): void
    {
        $this->transfer = BudgetTransfer::with([
            'period',
            'sourceBureau.department',
            'targetBureau.department',
            'requester',
            'reviewer',
            'sourceDeptApprover',
            'targetDeptApprover',
            'approvals.user',
            'items.workPlan.activity.coas',
            'items.workPlan.budgetItems.monthlies',
            'items.budgetItem',
        ])->findOrFail($id);
    }

    public function approve(BudgetTransferService $service): void
    {
        if (Setting::get('rkap_submission_status', 'open') === 'closed' || ($this->transfer->period && !$this->transfer->period->isOpen())) {
            session()->flash('error', __('Pengisian usulan RKAP sedang ditutup atau periode tidak dalam status Open. Anda tidak dapat menyetujui transfer budget.'));
            return;
        }

        $user = Auth::user();
        if (!$user || !$this->canReview()) {
            session()->flash('error', __('Anda tidak memiliki wewenang untuk menyetujui transfer budget pada tahap ini.'));
            return;
        }

        try {
            $service->approveTransfer($this->transfer, $user, $this->reviewNotes);
            $newStatus = $this->transfer->fresh()->status;

            $msg = match ($newStatus) {
                BudgetTransferStatus::PendingTargetDept->value => __('Usulan transfer telah disetujui dan diteruskan ke Kepala Departemen Penerima.'),
                BudgetTransferStatus::PendingTargetBureau->value => __('Usulan transfer telah disetujui dan diteruskan ke Kepala Biro Penerima.'),
                BudgetTransferStatus::Approved->value => __('Transfer budget berhasil disetujui. Program kerja dan kegiatan beserta anggarannya telah dipindahkan ke Biro Penerima.'),
                default => __('Aksi approval transfer budget berhasil diproses.'),
            };

            session()->flash('message', $msg);
            $this->redirectRoute('rkap-budget-transfers');
        } catch (Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function reject(BudgetTransferService $service): void
    {
        if (Setting::get('rkap_submission_status', 'open') === 'closed' || ($this->transfer->period && !$this->transfer->period->isOpen())) {
            session()->flash('error', __('Pengisian usulan RKAP sedang ditutup atau periode tidak dalam status Open. Anda tidak dapat menolak transfer budget.'));
            return;
        }

        $user = Auth::user();
        if (!$user || !$this->canReview()) {
            session()->flash('error', __('Anda tidak memiliki wewenang untuk menolak transfer budget pada tahap ini.'));
            return;
        }

        try {
            $service->rejectTransfer($this->transfer, $user, $this->reviewNotes);
            session()->flash('message', __('Transfer budget telah ditolak.'));
            $this->redirectRoute('rkap-budget-transfers');
        } catch (Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function canReview(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return $this->transfer->canBeReviewedBy($user);
    }

    public function deleteZeroBudgetTransferredItem(int $itemId, BudgetTransferService $service): void
    {
        if (Setting::get('rkap_submission_status', 'open') === 'closed' || ($this->transfer->period && !$this->transfer->period->isOpen())) {
            session()->flash('error', __('Pengisian usulan RKAP sedang ditutup atau periode tidak dalam status Open. Anda tidak dapat melakukan perubahan.'));
            return;
        }

        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            session()->flash('error', __('Hanya Administrator yang dapat menghapus record transfer budget Rp 0.'));
            return;
        }

        try {
            $service->deleteZeroBudgetTransferredItem($user, $itemId);
            session()->flash('message', __('Record kegiatan ber-budget Rp 0 di Biro Asal telah berhasil dibersihkan.'));
            $this->transfer->refresh()->load([
                'sourceBureau.department',
                'targetBureau.department',
                'sourceDeptApprover',
                'targetDeptApprover',
                'approvals.user',
                'items.workPlan.activity.coas',
                'items.workPlan.budgetItems.monthlies',
                'items.budgetItem',
            ]);
        } catch (Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.rkap.budget-transfer-review')->layout('layouts.contentNavbarLayout');
    }
}
