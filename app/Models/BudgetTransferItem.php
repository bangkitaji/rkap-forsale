<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetTransferItem extends Model
{
    protected $fillable = [
        'budget_transfer_id',
        'rkap_work_plan_id',
        'snapshot_data',
    ];

    protected function casts(): array
    {
        return [
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
}
