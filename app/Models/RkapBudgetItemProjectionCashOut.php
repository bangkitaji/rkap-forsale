<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RkapBudgetItemProjectionCashOut extends Model
{
    protected $table = 'rkap_budget_item_projection_cash_outs';

    protected $fillable = [
        'rkap_budget_item_id',
        'rkap_period_id',
        'month',
        'amount',
        'inputted_by',
    ];

    protected function casts(): array
    {
        return [
            'month'          => 'integer',
            'rkap_period_id' => 'integer',
            'amount'         => 'decimal:2',
        ];
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(RkapBudgetItem::class, 'rkap_budget_item_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RkapPeriod::class, 'rkap_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inputted_by');
    }
}
