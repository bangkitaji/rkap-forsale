<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RkapWorkPlan extends Model
{
    protected $fillable = [
        'rkap_submission_id',
        'work_plan_id',
        'activity_id',
        'program_code',
        'program_name',
        'description',
        'output_target',
        'unit',
        'quantity',
        'sort_order',
        'approval_status',
        'revision_notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity'     => 'integer',
            'sort_order'   => 'integer',
            'work_plan_id' => 'integer',
            'activity_id'  => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(RkapSubmission::class, 'rkap_submission_id');
    }

    public function workPlan(): BelongsTo
    {
        return $this->belongsTo(WorkPlan::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function budgetItems(): HasMany
    {
        return $this->hasMany(RkapBudgetItem::class);
    }

    public function getTotalBudgetAttribute(): float
    {
        return (float) $this->budgetItems->sum('total_price');
    }

    protected static function booted(): void
    {
        static::saving(function (RkapWorkPlan $rkapWorkPlan) {
            if (empty($rkapWorkPlan->program_name)) {
                if ($rkapWorkPlan->activity_id) {
                    $rkapWorkPlan->program_name = $rkapWorkPlan->activity->title ?? null;
                } elseif ($rkapWorkPlan->work_plan_id) {
                    $rkapWorkPlan->program_name = $rkapWorkPlan->workPlan->title ?? null;
                }
            }
            if (empty($rkapWorkPlan->program_code)) {
                if ($rkapWorkPlan->activity_id) {
                    $rkapWorkPlan->program_code = $rkapWorkPlan->activity->code ?? null;
                } elseif ($rkapWorkPlan->work_plan_id) {
                    $rkapWorkPlan->program_code = $rkapWorkPlan->workPlan->code ?? null;
                }
            }
        });
    }
}

