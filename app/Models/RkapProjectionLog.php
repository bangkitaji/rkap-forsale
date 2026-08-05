<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RkapProjectionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rkap_budget_item_id',
        'rkap_period_id',
        'user_id',
        'batch_id',
        'source',
        'input_mode',
        'old_total',
        'new_total',
        'old_monthly',
        'new_monthly',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_total'   => 'decimal:2',
            'new_total'   => 'decimal:2',
            'old_monthly' => 'array',
            'new_monthly' => 'array',
            'created_at'  => 'datetime',
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
        return $this->belongsTo(User::class, 'user_id');
    }
}
