<?php

namespace App\Models;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalRole;
use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\Searchable;
use Illuminate\Support\Facades\Log;

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

  protected $attributes = [
    'current_version' => 1,
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
    $snapshotData = $this->workPlans->load(['budgetItems.monthlies', 'budgetItems.cashOuts'])->map(function ($wp) {
      return [
        'work_plan_id' => $wp->work_plan_id,
        'activity_id' => $wp->activity_id,
        'program_code' => $wp->program_code,
        'program_name' => $wp->program_name,
        'description' => $wp->description,
        'output_target' => $wp->output_target,
        'unit' => $wp->unit,
        'quantity' => $wp->quantity,
        'sort_order' => $wp->sort_order,
        'approval_status' => $wp->approval_status,
        'revision_notes' => $wp->revision_notes,
        'budget_items' => $wp->budgetItems->map(function ($bi) {
          return [
            'id' => $bi->id,
            'account_code' => $bi->account_code,
            'description' => $bi->description,
            'unit' => $bi->unit,
            'quantity' => $bi->quantity,
            'unit_price' => $bi->unit_price,
            'total_price' => $bi->total_price,
            'remarks' => $bi->remarks,
            'monthly_distribution' => $bi->monthlies->pluck('amount', 'month')->toArray(),
            'cash_out_distribution' => $bi->cashOuts->pluck('amount', 'month')->toArray(),
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
    $this->update(['status' => SubmissionStatus::Submitted->value]);
    Log::info('RKAP Submission submitted', [
        'submission_id' => $this->id,
        'bureau_id' => $this->bureau_id,
        'user_id' => auth()->id(),
        'period_id' => $this->rkap_period_id,
    ]);
  }

  public function approveByDept(User $user, ?string $comments = null): void
  {
    $this->approvals()->create([
      'user_id' => $user->id,
      'version_number' => $this->current_version,
      'role' => ApprovalRole::KepalaDepartemen->value,
      'action' => ApprovalAction::Approved->value,
      'comments' => $comments,
    ]);
    $this->update(['status' => SubmissionStatus::DeptApproved->value]);
    Log::info('RKAP Submission approved by Department Head', [
        'submission_id' => $this->id,
        'user_id' => $user->id,
        'comments' => $comments,
    ]);
  }

  public function requestRevisionByDept(User $user, ?string $comments = null): void
  {
    $this->approvals()->create([
      'user_id' => $user->id,
      'version_number' => $this->current_version,
      'role' => ApprovalRole::KepalaDepartemen->value,
      'action' => ApprovalAction::RevisionRequested->value,
      'comments' => $comments,
    ]);
    $this->update(['status' => SubmissionStatus::DeptRevision->value]);
    Log::info('RKAP Submission revision requested by Department Head', [
        'submission_id' => $this->id,
        'user_id' => $user->id,
        'comments' => $comments,
    ]);
  }

  public function approveByDir(User $user, ?string $comments = null): void
  {
    $this->approvals()->create([
      'user_id' => $user->id,
      'version_number' => $this->current_version,
      'role' => ApprovalRole::Direksi->value,
      'action' => ApprovalAction::Approved->value,
      'comments' => $comments,
    ]);
    $this->update(['status' => SubmissionStatus::DirApproved->value]);
    Log::info('RKAP Submission approved by Board of Directors', [
        'submission_id' => $this->id,
        'user_id' => $user->id,
        'comments' => $comments,
    ]);
  }

  public function requestRevisionByDir(User $user, ?string $comments = null): void
  {
    $this->approvals()->create([
      'user_id' => $user->id,
      'version_number' => $this->current_version,
      'role' => ApprovalRole::Direksi->value,
      'action' => ApprovalAction::RevisionRequested->value,
      'comments' => $comments,
    ]);
    $this->update(['status' => SubmissionStatus::DirRevision->value]);
    Log::info('RKAP Submission revision requested by Board of Directors', [
        'submission_id' => $this->id,
        'user_id' => $user->id,
        'comments' => $comments,
    ]);
  }

  public function approveFinal(User $user, ?string $comments = null): void
  {
    $this->approvals()->create([
      'user_id' => $user->id,
      'version_number' => $this->current_version,
      'role' => ApprovalRole::Verifikator->value,
      'action' => ApprovalAction::Approved->value,
      'comments' => $comments,
    ]);
    $this->update(['status' => SubmissionStatus::VerifikatorApproved->value]);
    Log::info('RKAP Submission verified by Verificator', [
        'submission_id' => $this->id,
        'user_id' => $user->id,
        'comments' => $comments,
    ]);
  }

  public function requestRevisionByVerificator(User $user, ?string $comments = null): void
  {
    $this->approvals()->create([
      'user_id' => $user->id,
      'version_number' => $this->current_version,
      'role' => ApprovalRole::Verifikator->value,
      'action' => ApprovalAction::RevisionRequested->value,
      'comments' => $comments,
    ]);
    $this->update(['status' => SubmissionStatus::FinalRevision->value]);
    Log::info('RKAP Submission revision requested by Verificator', [
        'submission_id' => $this->id,
        'user_id' => $user->id,
        'comments' => $comments,
    ]);
  }

  public function hasApprovedCurrentVersion(User $user): bool
  {
    $role = null;
    if ($user->isPresidentDirector()) {
      $role = ApprovalRole::DirekturUtama;
    } elseif ($user->isDirekturFinance()) {
      $role = ApprovalRole::DirekturKeuangan;
    }

    if (!$role) {
      return false;
    }

    return $this->approvals()
      ->where('version_number', $this->current_version)
      ->where('role', $role->value)
      ->where('action', ApprovalAction::Approved->value)
      ->exists();
  }

  public function approveByPresident(User $user, ?string $comments = null): void
  {
    $this->approvals()->create([
      'user_id' => $user->id,
      'version_number' => $this->current_version,
      'role' => ApprovalRole::DirekturUtama->value,
      'action' => ApprovalAction::Approved->value,
      'comments' => $comments,
    ]);
    Log::info('RKAP Submission approved by President Director', [
        'submission_id' => $this->id,
        'user_id' => $user->id,
        'comments' => $comments,
    ]);
    $this->checkParallelApprovalAndFinalize();
  }

  public function requestRevisionByPresident(User $user, ?string $comments = null): void
  {
    $this->approvals()->create([
      'user_id' => $user->id,
      'version_number' => $this->current_version,
      'role' => ApprovalRole::DirekturUtama->value,
      'action' => ApprovalAction::RevisionRequested->value,
      'comments' => $comments,
    ]);
    $this->update(['status' => SubmissionStatus::PdirRevision->value]);
    Log::info('RKAP Submission revision requested by President Director', [
        'submission_id' => $this->id,
        'user_id' => $user->id,
        'comments' => $comments,
    ]);
  }

  public function approveByFinance(User $user, ?string $comments = null): void
  {
    $this->approvals()->create([
      'user_id' => $user->id,
      'version_number' => $this->current_version,
      'role' => ApprovalRole::DirekturKeuangan->value,
      'action' => ApprovalAction::Approved->value,
      'comments' => $comments,
    ]);
    Log::info('RKAP Submission approved by Finance Director', [
        'submission_id' => $this->id,
        'user_id' => $user->id,
        'comments' => $comments,
    ]);
    $this->checkParallelApprovalAndFinalize();
  }

  public function requestRevisionByFinance(User $user, ?string $comments = null): void
  {
    $this->approvals()->create([
      'user_id' => $user->id,
      'version_number' => $this->current_version,
      'role' => ApprovalRole::DirekturKeuangan->value,
      'action' => ApprovalAction::RevisionRequested->value,
      'comments' => $comments,
    ]);
    $this->update(['status' => SubmissionStatus::PdirRevision->value]);
    Log::info('RKAP Submission revision requested by Finance Director', [
        'submission_id' => $this->id,
        'user_id' => $user->id,
        'comments' => $comments,
    ]);
  }

  public function checkParallelApprovalAndFinalize(): void
  {
    $hasDirut = $this->approvals()
      ->where('version_number', $this->current_version)
      ->where('role', ApprovalRole::DirekturUtama->value)
      ->where('action', ApprovalAction::Approved->value)
      ->exists();

    $hasFinance = $this->approvals()
      ->where('version_number', $this->current_version)
      ->where('role', ApprovalRole::DirekturKeuangan->value)
      ->where('action', ApprovalAction::Approved->value)
      ->exists();

    if ($hasDirut && $hasFinance) {
      $this->update(['status' => SubmissionStatus::Approved->value]);
      Log::info('RKAP Submission finalized and fully approved', [
          'submission_id' => $this->id,
      ]);
    } else {
      $this->update(['status' => SubmissionStatus::PdirReview->value]);
    }
  }

  public function revise(): void
  {
    Log::info('RKAP Submission incremented for revision', [
        'submission_id' => $this->id,
        'new_version' => $this->current_version + 1,
    ]);
    $this->increment('current_version');
    $this->update(['status' => SubmissionStatus::Draft->value]);
  }

  // ── Authorization Helpers ──

  public function canBeEditedBy(User $user): bool
  {
    if (!in_array($this->status, SubmissionStatus::values(SubmissionStatus::editableStatuses()))) {
      return false;
    }
    return $user->bureau_id === $this->bureau_id;
  }

  public function canBeReviewedBy(User $user): bool
  {
    // Kepala Departemen
    if ($this->status === SubmissionStatus::Submitted->value && $user->hasRole('kepala_departemen')) {
      return $user->department_id === $this->bureau->department_id;
    }
    // Direksi
    if ($this->status === SubmissionStatus::DirReview->value && $user->hasRole('direksi')) {
      return $user->directorate_id === $this->bureau->department->directorate_id;
    }
    // Verifikator
    if ($this->status === SubmissionStatus::FinalReview->value && $user->hasRole('verifikator')) {
      return true;
    }
    // President Director & Direktur Finance
    if ($this->status === SubmissionStatus::PdirReview->value) {
      if ($user->isPresidentDirector() || $user->isDirekturFinance()) {
        return !$this->hasApprovedCurrentVersion($user);
      }
    }
    return false;
  }

  // ── Status Helpers ──

  public function getStatusLabelAttribute(): string
  {
    $enum = SubmissionStatus::tryFrom($this->status);
    return $enum ? $enum->label() : $this->status;
  }

  public function getStatusColorAttribute(): string
  {
    $enum = SubmissionStatus::tryFrom($this->status);
    return $enum ? $enum->color() : 'secondary';
  }
}
