<?php

namespace App\Models;

use App\Enums\BudgetTransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetTransfer extends Model
{
    protected $fillable = [
        'rkap_period_id',
        'source_bureau_id',
        'target_bureau_id',
        'source_submission_id',
        'target_submission_id',
        'requested_by',
        'reviewed_by',
        'status',
        'notes',
        'review_notes',
        'total_amount',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function period(): BelongsTo
    {
        return $this->belongsTo(RkapPeriod::class, 'rkap_period_id');
    }

    public function sourceBureau(): BelongsTo
    {
        return $this->belongsTo(Bureau::class, 'source_bureau_id');
    }

    public function targetBureau(): BelongsTo
    {
        return $this->belongsTo(Bureau::class, 'target_bureau_id');
    }

    public function sourceSubmission(): BelongsTo
    {
        return $this->belongsTo(RkapSubmission::class, 'source_submission_id');
    }

    public function targetSubmission(): BelongsTo
    {
        return $this->belongsTo(RkapSubmission::class, 'target_submission_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetTransferItem::class);
    }

    // ── Helper Methods ──

    public function isPending(): bool
    {
        return $this->status === BudgetTransferStatus::Pending->value;
    }

    public function getStatusLabelAttribute(): string
    {
        $enum = BudgetTransferStatus::tryFrom($this->status);
        return $enum ? $enum->label() : $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        $enum = BudgetTransferStatus::tryFrom($this->status);
        return $enum ? $enum->color() : 'secondary';
    }
}
