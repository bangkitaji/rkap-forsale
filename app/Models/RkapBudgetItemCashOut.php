<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RkapBudgetItemCashOut extends Model
{
    protected $fillable = [
        'rkap_budget_item_id',
        'month',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'month'  => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(RkapBudgetItem::class, 'rkap_budget_item_id');
    }

    /**
     * Return the Indonesian month name for this record's month number.
     */
    public function getMonthNameAttribute(): string
    {
        $names = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April',   5 => 'Mei',      6 => 'Juni',
            7 => 'Juli',    8 => 'Agustus',   9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        return $names[$this->month] ?? '';
    }
}
