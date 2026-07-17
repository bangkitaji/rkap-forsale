<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\BudgetTransfer;
use App\Models\RkapPeriod;
use App\Services\BudgetTransferService;
use Illuminate\Support\Facades\Auth;
use Exception;

class BudgetTransferList extends Component
{
    use WithCustomPagination;

    public string $activeTab = 'incoming'; // incoming or outgoing
    public string $search = '';
    public string $filterStatus = '';
    public ?int $filterPeriod = null;

    public function mount(): void
    {
        $user = Auth::user();
        if (!$user || !$user->bureau_id) {
            $this->activeTab = 'outgoing';
        }
    }

    public function selectTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function cancelTransfer(int $id, BudgetTransferService $service): void
    {
        try {
            $transfer = BudgetTransfer::findOrFail($id);
            $service->cancelTransfer($transfer, Auth::user());
            session()->flash('message', __('Transfer berhasil dibatalkan.'));
        } catch (Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $user = Auth::user();
        $query = BudgetTransfer::with(['period', 'sourceBureau', 'targetBureau', 'requester', 'reviewer'])
            ->latest();

        // Filter by tab
        if ($this->activeTab === 'incoming') {
            if ($user->bureau_id) {
                $query->where('target_bureau_id', $user->bureau_id);
            }
        } else {
            if ($user->bureau_id) {
                $query->where('source_bureau_id', $user->bureau_id);
            }
        }

        // Filter by status
        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        // Filter by period
        if ($this->filterPeriod) {
            $query->where('rkap_period_id', $this->filterPeriod);
        }

        // Search in notes or bureaus
        if ($this->search !== '') {
            $searchTerm = '%' . $this->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('notes', 'like', $searchTerm)
                  ->orWhereHas('sourceBureau', function ($sub) use ($searchTerm) {
                      $sub->where('name', 'like', $searchTerm)->orWhere('code', 'like', $searchTerm);
                  })
                  ->orWhereHas('targetBureau', function ($sub) use ($searchTerm) {
                      $sub->where('name', 'like', $searchTerm)->orWhere('code', 'like', $searchTerm);
                  });
            });
        }

        $transfers = $query->paginate(10);
        $periods = RkapPeriod::orderByDesc('year')->get();

        return view('livewire.rkap.budget-transfer-list', [
            'transfers' => $transfers,
            'periods' => $periods,
        ])->layout('layouts.contentNavbarLayout');
    }
}
