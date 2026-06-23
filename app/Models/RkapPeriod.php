<?php

namespace App\Models;

use App\Enums\PeriodStatus;
use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\Searchable;

class RkapPeriod extends Model
{
    use Searchable;

    protected $fillable = [
        'year',
        'title',
        'description',
        'status',
        'submission_start',
        'submission_end',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'submission_start' => 'date',
            'submission_end' => 'date',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(RkapSubmission::class);
    }

    public function isOpen(): bool
    {
        return $this->status === PeriodStatus::Open->value;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [PeriodStatus::Closed->value, PeriodStatus::Finalized->value]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', PeriodStatus::Open->value);
    }

    public function getSubmissionProgressAttribute(): array
    {
        $total = $this->submissions()->count();
        $approved = $this->submissions()->where('status', SubmissionStatus::Approved->value)->count();
        $inProgress = $this->submissions()->whereNotIn('status', [SubmissionStatus::Draft->value, SubmissionStatus::Approved->value])->count();

        return [
            'total' => $total,
            'approved' => $approved,
            'in_progress' => $inProgress,
            'percentage' => $total > 0 ? round(($approved / $total) * 100) : 0,
        ];
    }
}
