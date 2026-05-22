<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RkapPeriod extends Model
{
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
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['closed', 'finalized']);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'open');
    }

    public function getSubmissionProgressAttribute(): array
    {
        $total = $this->submissions()->count();
        $approved = $this->submissions()->where('status', 'approved')->count();
        $inProgress = $this->submissions()->whereNotIn('status', ['draft', 'approved'])->count();

        return [
            'total' => $total,
            'approved' => $approved,
            'in_progress' => $inProgress,
            'percentage' => $total > 0 ? round(($approved / $total) * 100) : 0,
        ];
    }
}
