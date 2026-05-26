<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\Searchable;

class RkapSubmission extends Model
{
    use Searchable;

    protected $fillable = [
        'rkap_period_id',
        'bureau_id',
        'created_by',
        'current_version',
        'status',
        'total_budget',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'current_version' => 'integer',
            'total_budget' => 'decimal:2',
        ];
    }

    // ── Relationships ──

    public function period(): BelongsTo
    {
        return $this->belongsTo(RkapPeriod::class, 'rkap_period_id');
    }

    public function bureau(): BelongsTo
    {
        return $this->belongsTo(Bureau::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workPlans(): HasMany
    {
        return $this->hasMany(RkapWorkPlan::class)->orderBy('sort_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RkapVersion::class)->orderByDesc('version_number');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RkapApproval::class)->orderByDesc('created_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RkapComment::class)->orderByDesc('created_at');
    }

    // ── Budget Calculation ──

    public function calculateTotalBudget(): float
    {
        $total = 0;
        foreach ($this->workPlans as $workPlan) {
            $total += $workPlan->budgetItems->sum('total_price');
        }
        $this->update(['total_budget' => $total]);
        return $total;
    }

    // ── Version Control ──

    public function createVersion(string $changeType, ?string $changeReason = null): RkapVersion
    {
        $snapshotData = $this->workPlans->load('budgetItems.monthlies')->map(function ($wp) {
            return [
                'program_code' => $wp->program_code,
                'program_name' => $wp->program_name,
                'description' => $wp->description,
                'output_target' => $wp->output_target,
                'unit' => $wp->unit,
                'quantity' => $wp->quantity,
                'sort_order' => $wp->sort_order,
                'budget_items' => $wp->budgetItems->map(function ($bi) {
                    return [
                        'account_code' => $bi->account_code,
                        'description' => $bi->description,
                        'unit' => $bi->unit,
                        'quantity' => $bi->quantity,
                        'unit_price' => $bi->unit_price,
                        'total_price' => $bi->total_price,
                        'remarks' => $bi->remarks,
                        'monthly_distribution' => $bi->monthlies->pluck('amount', 'month')->toArray(),
                    ];
                })->toArray(),
            ];
        })->toArray();

        return RkapVersion::create([
            'rkap_submission_id' => $this->id,
            'version_number' => $this->current_version,
            'created_by' => auth()->id(),
            'change_type' => $changeType,
            'change_reason' => $changeReason,
            'total_budget' => $this->total_budget,
            'snapshot_data' => $snapshotData,
        ]);
    }

    // ── Status Transitions ──

    public function submit(): void
    {
        $this->calculateTotalBudget();
        $this->createVersion('initial', 'Pengajuan awal');
        $this->update(['status' => 'submitted']);
    }

    public function approveByDept(User $user, ?string $comments = null): void
    {
        $this->approvals()->create([
            'user_id' => $user->id,
            'version_number' => $this->current_version,
            'role' => 'kepala_departemen',
            'action' => 'approved',
            'comments' => $comments,
        ]);
        $this->update(['status' => 'dept_approved']);
    }

    public function requestRevisionByDept(User $user, ?string $comments = null): void
    {
        $this->approvals()->create([
            'user_id' => $user->id,
            'version_number' => $this->current_version,
            'role' => 'kepala_departemen',
            'action' => 'revision_requested',
            'comments' => $comments,
        ]);
        $this->update(['status' => 'dept_revision']);
    }

    public function approveByDir(User $user, ?string $comments = null): void
    {
        $this->approvals()->create([
            'user_id' => $user->id,
            'version_number' => $this->current_version,
            'role' => 'direksi',
            'action' => 'approved',
            'comments' => $comments,
        ]);
        $this->update(['status' => 'dir_approved']);
    }

    public function requestRevisionByDir(User $user, ?string $comments = null): void
    {
        $this->approvals()->create([
            'user_id' => $user->id,
            'version_number' => $this->current_version,
            'role' => 'direksi',
            'action' => 'revision_requested',
            'comments' => $comments,
        ]);
        $this->update(['status' => 'dir_revision']);
    }

    public function approveFinal(User $user, ?string $comments = null): void
    {
        $this->approvals()->create([
            'user_id' => $user->id,
            'version_number' => $this->current_version,
            'role' => 'verifikator',
            'action' => 'approved',
            'comments' => $comments,
        ]);
        $this->update(['status' => 'approved']);
    }

    public function requestRevisionByVerificator(User $user, ?string $comments = null): void
    {
        $this->approvals()->create([
            'user_id' => $user->id,
            'version_number' => $this->current_version,
            'role' => 'verifikator',
            'action' => 'revision_requested',
            'comments' => $comments,
        ]);
        $this->update(['status' => 'final_revision']);
    }

    public function revise(): void
    {
        $this->increment('current_version');
        $this->update(['status' => 'draft']);
    }

    // ── Authorization Helpers ──

    public function canBeEditedBy(User $user): bool
    {
        if (!in_array($this->status, ['draft', 'dept_revision', 'dir_revision', 'final_revision'])) {
            return false;
        }
        return $user->bureau_id === $this->bureau_id;
    }

    public function canBeReviewedBy(User $user): bool
    {
        // Kepala Departemen
        if ($this->status === 'submitted' && $user->hasRole('kepala_departemen')) {
            return $user->department_id === $this->bureau->department_id;
        }
        // Direksi
        if ($this->status === 'dir_review' && $user->hasRole('direksi')) {
            return $user->directorate_id === $this->bureau->department->directorate_id;
        }
        // Verifikator
        if ($this->status === 'final_review' && $user->hasRole('verifikator')) {
            return true;
        }
        return false;
    }

    // ── Status Helpers ──

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'submitted' => 'Diajukan',
            'dept_review' => 'Review Kadep',
            'dept_approved' => 'Disetujui Kadep',
            'dept_revision' => 'Revisi Kadep',
            'dir_review' => 'Review Direksi',
            'dir_approved' => 'Disetujui Direksi',
            'dir_revision' => 'Revisi Direksi',
            'final_review' => 'Verifikasi Final',
            'final_revision' => 'Revisi Verifikator',
            'approved' => 'Disetujui',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'secondary',
            'submitted', 'dept_review', 'dir_review', 'final_review' => 'info',
            'dept_approved', 'dir_approved' => 'primary',
            'dept_revision', 'dir_revision', 'final_revision' => 'warning',
            'approved' => 'success',
            default => 'secondary',
        };
    }
}
