<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RkapWorkPlan extends Model
{
    protected $fillable = [
        'rkap_submission_id',
        'program_code',
        'program_name',
        'description',
        'output_target',
        'unit',
        'quantity',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(RkapSubmission::class, 'rkap_submission_id');
    }

    public function budgetItems(): HasMany
    {
        return $this->hasMany(RkapBudgetItem::class);
    }

    public function getTotalBudgetAttribute(): float
    {
        return (float) $this->budgetItems->sum('total_price');
    }
}
