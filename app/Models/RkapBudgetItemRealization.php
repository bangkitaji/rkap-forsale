<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RkapBudgetItemRealization extends Model
{
    protected $fillable = [
        'rkap_budget_item_id',
        'rkap_period_id',
        'month',
        'amount',
        'uploaded_by',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'month'          => 'integer',
            'rkap_period_id' => 'integer',
            'amount'         => 'decimal:2',
            'uploaded_at'    => 'datetime',
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Return the Indonesian month name for this record's month number.
     */
    public function getMonthNameAttribute(): string
    {
        $names = [
            1  => 'Januari',  2  => 'Februari', 3  => 'Maret',
            4  => 'April',    5  => 'Mei',       6  => 'Juni',
            7  => 'Juli',     8  => 'Agustus',   9  => 'September',
            10 => 'Oktober',  11 => 'November',  12 => 'Desember',
        ];
        return $names[$this->month] ?? '';
    }
}
