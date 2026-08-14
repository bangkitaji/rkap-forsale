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
        'source_dept_approved_by',
        'target_dept_approved_by',
        'status',
        'transfer_type',
        'notes',
        'review_notes',
        'source_dept_review_notes',
        'target_dept_review_notes',
        'total_amount',
        'reviewed_at',
        'source_dept_approved_at',
        'target_dept_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'source_dept_approved_at' => 'datetime',
            'target_dept_approved_at' => 'datetime',
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

    public function sourceDeptApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_dept_approved_by');
    }

    public function targetDeptApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_dept_approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetTransferItem::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(BudgetTransferApproval::class)->orderBy('created_at');
    }

    // ── Helper Methods ──

    public function isPending(): bool
    {
        return in_array($this->status, BudgetTransferStatus::pendingStatuses(), true);
    }

    public function isCrossDepartment(): bool
    {
        if ($this->transfer_type === 'inter_department') {
            return true;
        }

        if ($this->relationLoaded('sourceBureau') && $this->relationLoaded('targetBureau')) {
            return $this->sourceBureau && $this->targetBureau && $this->sourceBureau->department_id !== $this->targetBureau->department_id;
        }

        return false;
    }

    public function canBeReviewedBy(User $user): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        // Intra-department transfer flow
        if (!$this->isCrossDepartment()) {
            return (int) $user->bureau_id === (int) $this->target_bureau_id
                && $user->hasPermissionTo('rkap.transfer.review');
        }

        // Cross-department transfer flow
        $userDeptId = $user->department_id ?: $user->bureau?->department_id;

        return match ($this->status) {
            BudgetTransferStatus::PendingSourceDept->value =>
                $user->isKepalaDepartemen()
                && (int) $userDeptId === (int) $this->sourceBureau->department_id
                && $user->hasPermissionTo('rkap.transfer.review'),

            BudgetTransferStatus::PendingTargetDept->value =>
                $user->isKepalaDepartemen()
                && (int) $userDeptId === (int) $this->targetBureau->department_id
                && $user->hasPermissionTo('rkap.transfer.review'),

            BudgetTransferStatus::PendingTargetBureau->value,
            BudgetTransferStatus::Pending->value =>
                (int) $user->bureau_id === (int) $this->target_bureau_id
                && $user->hasPermissionTo('rkap.transfer.review'),

            default => false,
        };
    }

    public function getCurrentStageRoleNameAttribute(): string
    {
        return match ($this->status) {
            BudgetTransferStatus::PendingSourceDept->value => 'Kepala Departemen Pengusul (' . ($this->sourceBureau->department->name ?? 'Dept Pengusul') . ')',
            BudgetTransferStatus::PendingTargetDept->value => 'Kepala Departemen Penerima (' . ($this->targetBureau->department->name ?? 'Dept Penerima') . ')',
            BudgetTransferStatus::PendingTargetBureau->value,
            BudgetTransferStatus::Pending->value => 'Kepala Biro Penerima (' . ($this->targetBureau->name ?? 'Biro Penerima') . ')',
            BudgetTransferStatus::Approved->value => 'Disetujui',
            BudgetTransferStatus::Rejected->value => 'Ditolak',
            BudgetTransferStatus::Cancelled->value => 'Dibatalkan',
            default => $this->status_label,
        };
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
