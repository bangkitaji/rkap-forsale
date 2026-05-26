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
        'unit_price',
        'total_price',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function workPlan(): BelongsTo
    {
        return $this->belongsTo(RkapWorkPlan::class, 'rkap_work_plan_id');
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

    protected static function booted(): void
    {
        static::saving(function (RkapBudgetItem $item) {
            $item->total_price = $item->quantity * $item->unit_price;
        });
    }
}
