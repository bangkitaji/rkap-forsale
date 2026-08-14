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
        'added_by_verifier',
        'is_past_period_payment',
        'past_period_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity'               => 'integer',
            'sort_order'             => 'integer',
            'work_plan_id'           => 'integer',
            'activity_id'            => 'integer',
            'added_by_verifier'      => 'boolean',
            'is_past_period_payment' => 'boolean',
            'past_period_id'         => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(RkapSubmission::class, 'rkap_submission_id');
    }

    public function pastPeriod(): BelongsTo
    {
        return $this->belongsTo(RkapPeriod::class, 'past_period_id');
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

    public function activityFiles(): HasMany
    {
        return $this->hasMany(RkapActivityFile::class);
    }

    public function getTotalBudgetAttribute(): float
    {
        $total = 0;
        foreach ($this->budgetItems as $bi) {
            $qty2 = !empty($bi->unit_2) ? (float) ($bi->quantity_2 ?? 1) : 1;
            $price = (float) ($bi->total_price ?? (((float) ($bi->quantity ?? 0)) * $qty2 * ((float) ($bi->unit_price ?? 0))));
            $isGain = isset($bi->is_gain) ? filter_var($bi->is_gain, FILTER_VALIDATE_BOOLEAN) : true;
            if ($bi->account_code === '7603000001' && $isGain) {
                $price = -$price;
            }
            $total += $price;
        }
        return $total;
    }

    public function getTransferredFromAttribute(): ?Bureau
    {
        $item = \App\Models\BudgetTransferItem::where('rkap_work_plan_id', $this->id)
            ->whereHas('transfer', function ($query) {
                $query->where('status', \App\Enums\BudgetTransferStatus::Approved->value);
            })
            ->with('transfer.sourceBureau')
            ->first();

        return $item ? $item->transfer->sourceBureau : null;
    }

    public function isLockedForTransfer(): bool
    {
        return \App\Models\BudgetTransferItem::where('rkap_work_plan_id', $this->id)
            ->whereHas('transfer', function ($query) {
                $query->whereIn('status', \App\Enums\BudgetTransferStatus::pendingStatuses());
            })->exists();
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

