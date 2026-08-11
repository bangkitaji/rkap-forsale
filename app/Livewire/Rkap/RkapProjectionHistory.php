<?php

namespace App\Livewire\Rkap;

use App\Models\RkapBudgetItem;
use App\Models\RkapProjectionLog;
use Livewire\Component;
use Illuminate\View\View;

class RkapProjectionHistory extends Component
{
    public int $budgetItemId;
    public string $historyItemName = '';
    public array $historyLogs = [];

    public function mount(int $budgetItemId): void
    {
        $this->budgetItemId = $budgetItemId;
        
        $budgetItem = RkapBudgetItem::findOrFail($budgetItemId);
        $this->historyItemName = ($budgetItem->account_code ? $budgetItem->account_code . ' — ' : '') . ($budgetItem->description ?? '');
        
        $this->historyLogs = RkapProjectionLog::with(['user'])
            ->where('rkap_budget_item_id', $budgetItemId)
            ->orderByDesc('id')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'created_at' => $log->created_at?->format('d M Y H:i:s'),
                    'user_name' => $log->user?->name ?? 'Sistem',
                    'source' => $log->source === 'bulk_upload' ? 'Upload Massal Excel' : 'Penginputan Manual',
                    'input_mode' => $log->input_mode === 'yearly' ? 'Tahunan' : 'Bulanan',
                    'old_total' => (float)$log->old_total,
                    'new_total' => (float)$log->new_total,
                    'old_monthly' => $log->old_monthly ?? [],
                    'new_monthly' => $log->new_monthly ?? [],
                    'notes' => $log->notes,
                ];
            })
            ->toArray();
    }

    public function render()
    {
        return view('livewire.rkap.rkap-projection-history')
            ->layout('layouts.contentNavbarLayout');
    }
}
