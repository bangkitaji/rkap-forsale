<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RkapTrendJustification extends Model
{
    protected $fillable = [
        'rkap_work_plan_id',
        'current_period_id',
        'proposal_period_id',
        'justification_deviation_projection',
        'justification_deviation_proposal',
        'updated_by',
    ];

    public function workPlan(): BelongsTo
    {
        return $this->belongsTo(RkapWorkPlan::class, 'rkap_work_plan_id');
    }

    public function currentPeriod(): BelongsTo
    {
        return $this->belongsTo(RkapPeriod::class, 'current_period_id');
    }

    public function proposalPeriod(): BelongsTo
    {
        return $this->belongsTo(RkapPeriod::class, 'proposal_period_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isFilled(): bool
    {
        return !empty(trim((string)$this->justification_deviation_projection))
            || !empty(trim((string)$this->justification_deviation_proposal));
    }

    public function isFullyFilled(string $itemType = 'matched'): bool
    {
        if ($itemType === 'new') {
            return !empty(trim((string)$this->justification_deviation_proposal));
        }

        return !empty(trim((string)$this->justification_deviation_projection))
            && !empty(trim((string)$this->justification_deviation_proposal));
    }
}
