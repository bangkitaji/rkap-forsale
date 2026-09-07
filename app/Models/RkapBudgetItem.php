<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RkapBudgetItem extends Model
{
    protected $fillable = [
        'rkap_work_plan_id',
        'account_code',
        'description',
        'unit',
        'quantity',
        'unit_2',
        'quantity_2',
        'unit_price',
        'total_price',
        'projection',
        'remarks',
        'flow_direction',
        'difference_group_id',
        'is_gain',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'quantity_2' => 'float',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'projection' => 'decimal:2',
            'is_gain' => 'boolean',
        ];
    }

    public function workPlan(): BelongsTo
    {
        return $this->belongsTo(RkapWorkPlan::class, 'rkap_work_plan_id');
    }

    public function coa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'account_code', 'code');
    }

    public function monthlies(): HasMany
    {
        return $this->hasMany(RkapBudgetItemMonthly::class)->orderBy('month');
    }

    /**
     * Sum of all monthly allocation amounts for this budget item.
     */
    public function getMonthlyTotalAttribute(): float
    {
        return (float) $this->monthlies->sum('amount');
    }

    public function cashOuts(): HasMany
    {
        return $this->hasMany(RkapBudgetItemCashOut::class)->orderBy('month');
    }

    /**
     * Sum of all cash out plan amounts for this budget item.
     */
    public function getCashOutTotalAttribute(): float
    {
        return (float) $this->cashOuts->sum('amount');
    }

    public function realizations(): HasMany
    {
        return $this->hasMany(RkapBudgetItemRealization::class)->orderBy('month');
    }

    /**
     * Sum of all realization amounts for this budget item.
     */
    public function getRealizationTotalAttribute(): float
    {
        return (float) $this->realizations->sum('amount');
    }

    public function projections(): HasMany
    {
        return $this->hasMany(RkapBudgetItemProjection::class)->orderBy('month');
    }

    public function projectionCashOuts(): HasMany
    {
        return $this->hasMany(RkapBudgetItemProjectionCashOut::class)->orderBy('month');
    }

    public function projectionLogs(): HasMany
    {
        return $this->hasMany(RkapProjectionLog::class)->orderByDesc('created_at');
    }

    public function differenceGroup(): BelongsTo
    {
        return $this->belongsTo(DifferenceGroup::class, 'difference_group_id');
    }

    /**
     * Check if this budget item belongs to a forex gain/loss toggleable account.
     */
    public function isForexAccount(): bool
    {
        return Coa::isForexAccount($this->account_code);
    }

    protected static function booted(): void
    {
        static::saving(function (RkapBudgetItem $item) {
            if (!empty($item->unit_2)) {
                $item->total_price = $item->quantity * ($item->quantity_2 ?? 1) * $item->unit_price;
            } else {
                $item->total_price = $item->quantity * $item->unit_price;
            }
        });
    }
}
