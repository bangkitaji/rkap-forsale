<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetTransferItem extends Model
{
    protected $fillable = [
        'budget_transfer_id',
        'rkap_work_plan_id',
        'rkap_budget_item_id',
        'amount_transferred',
        'monthly_distribution',
        'snapshot_data',
    ];

    protected function casts(): array
    {
        return [
            'amount_transferred' => 'decimal:2',
            'monthly_distribution' => 'array',
            'snapshot_data' => 'array',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(BudgetTransfer::class, 'budget_transfer_id');
    }

    public function workPlan(): BelongsTo
    {
        return $this->belongsTo(RkapWorkPlan::class, 'rkap_work_plan_id');
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(RkapBudgetItem::class, 'rkap_budget_item_id');
    }
}
