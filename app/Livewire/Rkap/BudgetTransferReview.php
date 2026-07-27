<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\BudgetTransfer;
use App\Services\BudgetTransferService;
use Illuminate\Support\Facades\Auth;
use App\Models\Setting;
use Exception;

class BudgetTransferReview extends Component
{
    public BudgetTransfer $transfer;
    public string $reviewNotes = '';

    public function mount(int $id): void
    {
        $this->transfer = BudgetTransfer::with([
            'period',
            'sourceBureau',
            'targetBureau',
            'requester',
            'reviewer',
            'items.workPlan.activity.coas',
            'items.workPlan.budgetItems.monthlies',
            'items.budgetItem',
        ])->findOrFail($id);
    }

    public function approve(BudgetTransferService $service): void
    {
        if (Setting::get('rkap_submission_status', 'open') === 'closed') {
            session()->flash('error', __('Pengisian usulan RKAP sedang ditutup. Anda tidak dapat menyetujui transfer budget.'));
            return;
        }

        $user = Auth::user();
        if (!$user || $user->bureau_id !== $this->transfer->target_bureau_id) {
            session()->flash('error', __('Hanya biro tujuan yang dapat menyetujui transfer ini.'));
            return;
        }

        try {
            $service->approveTransfer($this->transfer, $user, $this->reviewNotes);
            session()->flash('message', __('Transfer budget berhasil disetujui. Program kerja dan kegiatan beserta anggarannya telah dipindahkan ke Biro Anda.'));
            $this->redirectRoute('rkap-budget-transfers');
        } catch (Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function reject(BudgetTransferService $service): void
    {
        if (Setting::get('rkap_submission_status', 'open') === 'closed') {
            session()->flash('error', __('Pengisian usulan RKAP sedang ditutup. Anda tidak dapat menolak transfer budget.'));
            return;
        }

        $user = Auth::user();
        if (!$user || $user->bureau_id !== $this->transfer->target_bureau_id) {
            session()->flash('error', __('Hanya biro tujuan yang dapat menolak transfer ini.'));
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
        return $this->transfer->isPending() && $user && $user->bureau_id === $this->transfer->target_bureau_id && $user->hasPermissionTo('rkap.transfer.review');
    }

    public function deleteZeroBudgetTransferredItem(int $itemId, BudgetTransferService $service): void
    {
        if (Setting::get('rkap_submission_status', 'open') === 'closed') {
            session()->flash('error', __('Pengisian usulan RKAP sedang ditutup. Anda tidak dapat melakukan perubahan.'));
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
