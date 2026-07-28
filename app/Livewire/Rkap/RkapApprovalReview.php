<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapSubmission;
use App\Models\RkapBudgetItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;

class RkapApprovalReview extends Component
{
    public RkapSubmission $submission;
    public string $reviewComments = '';
    public string $revisionReason = '';
    public bool $showRevisionForm = false;
    public string $newComment = '';
    
    public array $activityStatuses = [];
    public array $activityRevisionNotes = [];

    public bool $isEditMode = false;
    public array $editCoas = [];
    public $selectedWorkPlanId = null;
    public $selectedActivityId = null;
    public string $activityDescription = '';
    public string $activityOutputTarget = '';
    public string $activityUnit = 'Paket';
    public int $activityQuantity = 1;
    public array $newActivityBudgetItems = [];

    public function getIsSubmissionClosedProperty(): bool
    {
        return Setting::get('rkap_submission_status', 'open') === 'closed';
    }

    public function mount(int $id): void
    {
        $this->submission = RkapSubmission::with([
            'bureau.department.directorate',
            'period',
            'creator',
            'workPlans.activityFiles',
            'workPlans.budgetItems.monthlies',
            'workPlans.budgetItems.cashOuts',
            'workPlans.budgetItems.coa.coaGroup',
            'workPlans.budgetItems.coa.cashflowGroup',
            'workPlans.budgetItems.coa.differenceGroups',
            'versions.creator',
            'approvals.user',
            'comments' => fn($q) => $q->topLevel()->with(['user', 'replies.user']),
        ])->findOrFail($id);

        $user = Auth::user();

        // Authorization: ensure current user has view access to this submission (same bureau, department, directorate, or admin/verificator/dirut)
        $isAuthorized = $user->isAdmin() ||
            $user->isVerifikator() ||
            $user->isPresidentDirector() ||
            $user->isDirekturFinance() ||
            $user->bureau_id === $this->submission->bureau_id ||
            ($user->department_id && $user->department_id === $this->submission->bureau->department_id) ||
            ($user->directorate_id && $user->directorate_id === $this->submission->bureau->department->directorate_id);

        if (!$isAuthorized) {
            abort(403, __('Anda tidak memiliki akses untuk melihat pengajuan ini.'));
        }

        foreach ($this->submission->workPlans as $wp) {
            $this->activityStatuses[$wp->id] = $wp->approval_status ?: 'pending';
            $this->activityRevisionNotes[$wp->id] = $wp->revision_notes ?: '';
        }
    }

    public function setActivityStatus(int $workPlanId, string $status): void
    {
        $this->activityStatuses[$workPlanId] = $status;
        $wp = \App\Models\RkapWorkPlan::find($workPlanId);
        if ($wp) {
            $wp->update(['approval_status' => $status]);
            if ($status === 'approved') {
                $this->activityRevisionNotes[$workPlanId] = '';
                $wp->update(['revision_notes' => null]);
            }
        }
        $this->submission->refresh()->load(['workPlans.budgetItems.monthlies', 'workPlans.budgetItems.cashOuts', 'workPlans.budgetItems.coa.coaGroup', 'workPlans.budgetItems.coa.cashflowGroup', 'workPlans.budgetItems.coa.differenceGroups']);
    }

    public function approveAllActivities(): void
    {
        foreach ($this->submission->workPlans as $wp) {
            $this->activityStatuses[$wp->id] = 'approved';
            $this->activityRevisionNotes[$wp->id] = '';
            $wp->update([
                'approval_status' => 'approved',
                'revision_notes' => null,
            ]);
        }
        $this->submission->refresh()->load(['workPlans.budgetItems.monthlies', 'workPlans.budgetItems.cashOuts', 'workPlans.budgetItems.coa.coaGroup', 'workPlans.budgetItems.coa.cashflowGroup', 'workPlans.budgetItems.coa.differenceGroups']);
        session()->flash('message', __('Semua kegiatan berhasil ditandai Disetujui.'));
    }

    public function rejectAllActivities(): void
    {
        foreach ($this->submission->workPlans as $wp) {
            $this->activityStatuses[$wp->id] = 'rejected';
            $wp->update([
                'approval_status' => 'rejected',
            ]);
        }
        $this->submission->refresh()->load(['workPlans.budgetItems.monthlies', 'workPlans.budgetItems.cashOuts', 'workPlans.budgetItems.coa.coaGroup', 'workPlans.budgetItems.coa.cashflowGroup', 'workPlans.budgetItems.coa.differenceGroups']);
        session()->flash('message', __('Semua kegiatan berhasil ditandai Ditolak. Silakan berikan catatan revisi.'));
    }

    public function updateActivityRevisionNotes(int $workPlanId, string $notes): void
    {
        $this->activityRevisionNotes[$workPlanId] = $notes;
        $wp = \App\Models\RkapWorkPlan::find($workPlanId);
        if ($wp) {
            $wp->update(['revision_notes' => $notes]);
        }
    }

    public function approve(): void
    {
        if (Setting::get('rkap_submission_status', 'open') === 'closed') {
            session()->flash('error', __('Pengisian usulan RKAP sedang ditutup. Anda tidak dapat menyetujui usulan.'));
            return;
        }

        $user = Auth::user();

        // 1. the approver can only approve the rkap submission if all activities are approved
        foreach ($this->submission->workPlans as $wp) {
            if (($this->activityStatuses[$wp->id] ?? 'pending') !== 'approved') {
                session()->flash('error', __('Gagal menyetujui: Semua kegiatan harus disetujui terlebih dahulu.'));
                return;
            }
        }

        if (($user->isPresidentDirector() || $user->isDirekturFinance()) && $this->submission->status === 'pdir_review') {
            $status = $this->presidentApprovalStatus;
            if (!$status['is_ready']) {
                session()->flash('error', __('Gagal menyetujui: Belum semua departemen menyelesaikan pengajuan RKAP yang terverifikasi.'));
                return;
            }
        }

        match (true) {
            $user->isKepalaDepartemen() => $this->submission->approveByDept($user, $this->reviewComments),
            ($user->isDirekturFinance() && $this->submission->status === 'pdir_review') => $this->submission->approveByFinance($user, $this->reviewComments),
            $user->isDireksi()          => $this->submission->approveByDir($user, $this->reviewComments),
            $user->isVerifikator()      => $this->submission->approveFinal($user, $this->reviewComments),
            $user->isPresidentDirector() => $this->submission->approveByPresident($user, $this->reviewComments),
            default => null,
        };

        // Advance status to next review stage
        $newStatus = match($this->submission->fresh()->status) {
            'dept_approved' => 'dir_review',
            'dir_approved'  => 'final_review',
            'verifikator_approved' => 'pdir_review',
            default         => null,
        };

        if ($newStatus) {
            $this->submission->update(['status' => $newStatus]);
        }

        $this->reviewComments = '';
        $this->submission->refresh()->load(['approvals.user', 'workPlans.budgetItems.monthlies', 'workPlans.budgetItems.cashOuts', 'workPlans.budgetItems.coa.coaGroup', 'workPlans.budgetItems.coa.cashflowGroup', 'workPlans.budgetItems.coa.differenceGroups', 'versions.creator', 'comments.user', 'comments.replies.user']);
        session()->flash('message', __('RKAP berhasil disetujui.'));
    }

    public function requestRevision(): void
    {
        if (Setting::get('rkap_submission_status', 'open') === 'closed') {
            session()->flash('error', __('Pengisian usulan RKAP sedang ditutup. Anda tidak dapat meminta revisi.'));
            return;
        }

        // 2. the approver can only reject if there is one or more rejected activities
        // 3. the approver must give revision notes
        $hasRejected = false;
        foreach ($this->submission->workPlans as $wp) {
            $status = $this->activityStatuses[$wp->id] ?? 'pending';
            if ($status === 'rejected') {
                $hasRejected = true;
                $notes = trim($this->activityRevisionNotes[$wp->id] ?? '');
                if (empty($notes)) {
                    session()->flash('error', __('Gagal meminta revisi: Catatan revisi wajib diisi untuk semua kegiatan yang ditolak.'));
                    $this->dispatch('focus-activity-revision-note', id: $wp->id);
                    return;
                }
            }
        }

        if (!$hasRejected) {
            session()->flash('error', __('Gagal meminta revisi: Minimal harus ada satu kegiatan yang ditolak.'));
            return;
        }

        $this->validate(['revisionReason' => 'required|string|min:10']);

        // Save the activity statuses and revision notes to the database
        foreach ($this->submission->workPlans as $wp) {
            $status = $this->activityStatuses[$wp->id] ?? 'pending';
            if ($status === 'rejected') {
                $wp->update([
                    'approval_status' => 'rejected',
                    'revision_notes' => trim($this->activityRevisionNotes[$wp->id] ?? ''),
                ]);
            } else {
                $wp->update([
                    'approval_status' => $status,
                    'revision_notes' => null,
                ]);
            }
        }

        $user = Auth::user();

        match (true) {
            $user->isKepalaDepartemen() => $this->submission->requestRevisionByDept($user, $this->revisionReason),
            ($user->isDirekturFinance() && $this->submission->status === 'pdir_review') => $this->submission->requestRevisionByFinance($user, $this->revisionReason),
            $user->isDireksi()          => $this->submission->requestRevisionByDir($user, $this->revisionReason),
            $user->isVerifikator()      => $this->submission->requestRevisionByVerificator($user, $this->revisionReason),
            $user->isPresidentDirector() => $this->submission->requestRevisionByPresident($user, $this->revisionReason),
            default => null,
        };

        $this->submission->revise();

        $this->revisionReason = '';
        $this->showRevisionForm = false;
        $this->submission->refresh()->load(['approvals.user', 'workPlans.budgetItems.monthlies', 'workPlans.budgetItems.cashOuts', 'workPlans.budgetItems.coa.coaGroup', 'workPlans.budgetItems.coa.cashflowGroup', 'workPlans.budgetItems.coa.differenceGroups', 'versions.creator', 'comments.user', 'comments.replies.user']);
        session()->flash('message', __('RKAP berhasil ditolak dan dikembalikan untuk revisi.'));
    }

    public function openRevisionForm(): void
    {
        $hasRejected = false;
        $missingNotesWpId = null;

        foreach ($this->submission->workPlans as $wp) {
            $status = $this->activityStatuses[$wp->id] ?? 'pending';
            if ($status === 'rejected') {
                $hasRejected = true;
                $notes = trim($this->activityRevisionNotes[$wp->id] ?? '');
                if ($notes === '') {
                    $missingNotesWpId = $wp->id;
                    break;
                }
            }
        }

        if ($missingNotesWpId !== null) {
            session()->flash('error', __('Gagal meminta revisi: Catatan revisi wajib diisi untuk semua kegiatan yang ditolak.'));
            $this->dispatch('focus-activity-revision-note', id: $missingNotesWpId);
            return;
        }

        if (!$hasRejected) {
            session()->flash('error', __('Gagal meminta revisi: Minimal harus ada satu kegiatan yang ditolak.'));
            return;
        }

        $this->showRevisionForm = true;

        $notes = [];
        foreach ($this->submission->workPlans as $wp) {
            $status = $this->activityStatuses[$wp->id] ?? 'pending';
            if ($status === 'rejected') {
                $wpNotes = trim($this->activityRevisionNotes[$wp->id] ?? '');
                if ($wpNotes !== '') {
                    $activityCode = $wp->program_code ?: '-';
                    $activityTitle = $wp->program_name ?: '-';
                    $notes[] = "- [{$activityCode} — {$activityTitle}]: {$wpNotes}";
                }
            }
        }

        if (!empty($notes)) {
            $this->revisionReason = "Catatan revisi kegiatan:\n" . implode("\n", $notes);
        } else {
            $this->revisionReason = '';
        }
    }

    public function addComment(): void
    {
        $this->validate(['newComment' => 'required|string|min:3']);

        $this->submission->comments()->create([
            'user_id'        => Auth::id(),
            'version_number' => $this->submission->current_version,
            'content'        => $this->newComment,
        ]);

        $this->newComment = '';
        $this->submission->refresh()->load(['comments' => fn($q) => $q->topLevel()->with(['user', 'replies.user'])]);
    }

    public function canApprove(): bool
    {
        $user = Auth::user();
        if (!$this->submission->canBeReviewedBy($user)) {
            return false;
        }

        if (($user->isPresidentDirector() || $user->isDirekturFinance()) && $this->submission->status === 'pdir_review') {
            $status = $this->presidentApprovalStatus;
            if (!$status['is_ready']) {
                return false;
            }
        }

        return true;
    }

    public function getPresidentApprovalStatusProperty(): array
    {
        $periodId = $this->submission->rkap_period_id;
        $activeDeptCount = \App\Models\Department::active()->count();

        $verifiedSubmissions = \App\Models\RkapSubmission::where('rkap_period_id', $periodId)
            ->whereIn('status', ['pdir_review', 'approved'])
            ->whereHas('bureau.department', function ($query) {
                $query->where('is_active', true);
            })
            ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
            ->select('bureaus.department_id')
            ->distinct()
            ->get();

        $verifiedDeptIds = $verifiedSubmissions->pluck('department_id')->toArray();
        $verifiedSubmissionsCount = count($verifiedDeptIds);

        $pendingDepartments = \App\Models\Department::active()
            ->whereNotIn('id', $verifiedDeptIds)
            ->pluck('name')
            ->toArray();

        return [
            'is_ready' => $verifiedSubmissionsCount >= $activeDeptCount,
            'verified_count' => $verifiedSubmissionsCount,
            'total_count' => $activeDeptCount,
            'pending_departments' => $pendingDepartments,
        ];
    }

    /**
     * Build a lookup map of the most recent prior approved submission
     * for the same bureau: [work_plan_id][account_code] => total_price
     */
    private function buildPreviousMap(): array
    {
        $bureauId = $this->submission->bureau_id;
        $currentPeriodYear = $this->submission->period?->year ?? 0;

        $service = app(\App\Services\RkapPreviousDataService::class);
        $prevSubmission = $service->getPreviousApprovedSubmission($bureauId, $currentPeriodYear, $this->submission->id);
        return $service->buildPreviousMap($prevSubmission);
    }

    public function getCombinedWorkPlans(): array
    {
        $currentWps = $this->submission->workPlans;
        
        // Find previous submission
        $bureauId = $this->submission->bureau_id;
        $currentPeriodYear = $this->submission->period?->year ?? 0;

        $service = app(\App\Services\RkapPreviousDataService::class);
        $prevSubmission = $service->getPreviousApprovedSubmission($bureauId, $currentPeriodYear, $this->submission->id);

        $combined = [];
        
        // 1. Map current work plans
        foreach ($currentWps as $wp) {
            $key = "{$wp->work_plan_id}-{$wp->activity_id}";
            
            // Map budget items of this work plan
            $itemsList = [];
            foreach ($wp->budgetItems as $bi) {
                $itemsList[] = [
                    'is_virtual' => false,
                    'model' => $bi,
                    'account_code' => $bi->account_code,
                    'description' => $bi->description,
                    'remarks' => $bi->remarks,
                    'quantity' => $bi->quantity,
                    'unit' => $bi->unit,
                    'unit_price' => (float) $bi->unit_price,
                    'total_price' => (float) $bi->total_price,
                    'is_gain' => (bool) ($bi->is_gain ?? true),
                    'monthlies' => $bi->monthlies,
                    'cashOuts' => $bi->cashOuts,
                ];
            }
            
            // If previous submission exists, check for dropped budget items in this same work plan
            if ($prevSubmission) {
                $prevWp = $prevSubmission->workPlans->first(fn($p) => "{$p->work_plan_id}-{$p->activity_id}" === $key);
                if ($prevWp) {
                    $pool = [];
                    foreach ($itemsList as $idx => $item) {
                        $matchKey = "{$item['account_code']}-" . trim(strtolower($item['description']));
                        $pool[$matchKey][] = $idx;
                    }

                    foreach ($prevWp->budgetItems as $prevBi) {
                        $prevItemKey = "{$prevBi->account_code}-" . trim(strtolower($prevBi->description));
                        if (isset($pool[$prevItemKey]) && !empty($pool[$prevItemKey])) {
                            // Consume one matching current item
                            array_shift($pool[$prevItemKey]);
                        } else {
                            // This budget item was dropped in the new submission
                            $itemsList[] = [
                                'is_virtual' => true,
                                'model' => null,
                                'account_code' => $prevBi->account_code,
                                'description' => $prevBi->description,
                                'remarks' => $prevBi->remarks,
                                'quantity' => 0,
                                'unit' => $prevBi->unit,
                                'unit_price' => (float) $prevBi->unit_price,
                                'total_price' => 0.0,
                                'monthlies' => collect(),
                                'cashOuts' => collect(),
                            ];
                        }
                    }
                }
            }
            
            // Sort items by account code
            usort($itemsList, function($a, $b) {
                return strcasecmp($a['account_code'] ?? '', $b['account_code'] ?? '');
            });

            // Group items by account_code
            $groupedItems = [];
            foreach ($itemsList as $item) {
                $code = $item['account_code'] ?: '-';
                if (!isset($groupedItems[$code])) {
                    $groupedItems[$code] = [];
                }
                $groupedItems[$code][] = $item;
            }

            $combined[$key] = [
                'is_virtual' => false,
                'model' => $wp,
                'work_plan_id' => $wp->work_plan_id,
                'activity_id' => $wp->activity_id,
                'program_code' => $wp->program_code,
                'program_name' => $wp->program_name,
                'description' => $wp->description,
                'output_target' => $wp->output_target,
                'quantity' => $wp->quantity,
                'unit' => $wp->unit,
                'total_budget' => (float) $wp->total_budget,
                'grouped_items' => $groupedItems,
            ];
        }
        
        // 2. Map dropped work plans (exist in previous but not current)
        if ($prevSubmission) {
            foreach ($prevSubmission->workPlans as $prevWp) {
                $key = "{$prevWp->work_plan_id}-{$prevWp->activity_id}";
                if (!isset($combined[$key])) {
                    // This work plan was dropped entirely
                    $itemsList = [];
                    foreach ($prevWp->budgetItems as $prevBi) {
                        $itemsList[] = [
                            'is_virtual' => true,
                            'model' => null,
                            'account_code' => $prevBi->account_code,
                            'description' => $prevBi->description,
                            'remarks' => $prevBi->remarks,
                            'quantity' => 0,
                            'unit' => $prevBi->unit,
                            'unit_price' => (float) $prevBi->unit_price,
                            'total_price' => 0.0,
                            'monthlies' => collect(),
                            'cashOuts' => collect(),
                        ];
                    }
                    
                    usort($itemsList, function($a, $b) {
                        return strcasecmp($a['account_code'] ?? '', $b['account_code'] ?? '');
                    });

                    // Group items by account_code
                    $groupedItems = [];
                    foreach ($itemsList as $item) {
                        $code = $item['account_code'] ?: '-';
                        if (!isset($groupedItems[$code])) {
                            $groupedItems[$code] = [];
                        }
                        $groupedItems[$code][] = $item;
                    }

                    $combined[$key] = [
                        'is_virtual' => true,
                        'model' => null,
                        'work_plan_id' => $prevWp->work_plan_id,
                        'activity_id' => $prevWp->activity_id,
                        'program_code' => $prevWp->program_code,
                        'program_name' => $prevWp->program_name,
                        'description' => $prevWp->description,
                        'output_target' => $prevWp->output_target,
                        'quantity' => 0,
                        'unit' => $prevWp->unit,
                        'total_budget' => 0.0,
                        'grouped_items' => $groupedItems,
                    ];
                }
            }
        }
        
        return $combined;
    }

    public function getHelicopterViewData(): array
    {
        $currentWpIds = $this->submission->workPlans->pluck('id');
        $currentItems = \App\Models\RkapBudgetItem::whereIn('rkap_work_plan_id', $currentWpIds)
            ->with(['coa.coaGroup', 'coa.coaCategory'])
            ->get();

        $bureauId = $this->submission->bureau_id;
        $currentPeriodYear = $this->submission->period?->year ?? 0;

        $service = app(\App\Services\RkapPreviousDataService::class);
        $prevSubmission = $service->getPreviousApprovedSubmission($bureauId, $currentPeriodYear, $this->submission->id);

        $prevItems = collect();
        if ($prevSubmission) {
            $prevWpIds = $prevSubmission->workPlans->pluck('id');
            $prevItems = \App\Models\RkapBudgetItem::whereIn('rkap_work_plan_id', $prevWpIds)
                ->with(['coa.coaGroup', 'coa.coaCategory'])
                ->get();
        }

        $categoriesData = [];
        $categoriesMeta = \App\Models\CoaCategory::orderBy('sort_order')->get();
        foreach ($categoriesMeta as $cat) {
            $categoriesData[$cat->id] = [
                'key' => $cat->key,
                'label' => $cat->label,
                'group' => $cat->group,
                'color' => $cat->color,
                'current_total' => 0.0,
                'prev_total' => 0.0,
                'coas' => []
            ];
        }

        $unmappedData = [];

        foreach ($currentItems as $item) {
            $coa = $item->coa;
            $totalPrice = (float) $item->total_price;
            // COA 7603000001 (kerugian kurs): is_gain=true → positive, is_gain=false → negative
            if ($item->account_code === '7603000001' && isset($item->is_gain) && !(bool) $item->is_gain) {
                $totalPrice = -$totalPrice;
            }

            if ($coa && $coa->coa_category_id) {
                $catId = $coa->coa_category_id;
                if (isset($categoriesData[$catId])) {
                    $categoriesData[$catId]['current_total'] += $totalPrice;

                    $coaCode = $coa->code;
                    if (!isset($categoriesData[$catId]['coas'][$coaCode])) {
                        $categoriesData[$catId]['coas'][$coaCode] = [
                            'code' => $coaCode,
                            'title' => $coa->title,
                            'current_total' => 0.0,
                            'prev_total' => 0.0,
                        ];
                    }
                    $categoriesData[$catId]['coas'][$coaCode]['current_total'] += $totalPrice;
                }
            } else {
                $coaCode = $item->account_code ?: 'unspecified';
                $coaTitle = $coa ? $coa->title : ($item->description ?: 'Tanpa Kode Akun');
                $coaGroupId = $coa ? $coa->coa_group_id : 0;
                $coaGroupName = ($coa && $coa->coaGroup) ? $coa->coaGroup->name : 'Tanpa Grup COA';
                $coaGroupCode = ($coa && $coa->coaGroup) ? $coa->coaGroup->code : '999';

                if (!isset($unmappedData[$coaGroupId])) {
                    $unmappedData[$coaGroupId] = [
                        'group_id' => $coaGroupId,
                        'group_code' => $coaGroupCode,
                        'group_name' => $coaGroupName,
                        'current_total' => 0.0,
                        'prev_total' => 0.0,
                        'coas' => []
                    ];
                }

                $unmappedData[$coaGroupId]['current_total'] += $totalPrice;

                if (!isset($unmappedData[$coaGroupId]['coas'][$coaCode])) {
                    $unmappedData[$coaGroupId]['coas'][$coaCode] = [
                        'code' => $coaCode,
                        'title' => $coaTitle,
                        'current_total' => 0.0,
                        'prev_total' => 0.0,
                    ];
                }
                $unmappedData[$coaGroupId]['coas'][$coaCode]['current_total'] += $totalPrice;
            }
        }

        foreach ($prevItems as $item) {
            $coa = $item->coa;
            $totalPrice = (float) $item->total_price;
            // COA 7603000001 (kerugian kurs): is_gain=true → positive, is_gain=false → negative
            if ($item->account_code === '7603000001' && isset($item->is_gain) && !(bool) $item->is_gain) {
                $totalPrice = -$totalPrice;
            }

            if ($coa && $coa->coa_category_id) {
                $catId = $coa->coa_category_id;
                if (isset($categoriesData[$catId])) {
                    $categoriesData[$catId]['prev_total'] += $totalPrice;

                    $coaCode = $coa->code;
                    if (!isset($categoriesData[$catId]['coas'][$coaCode])) {
                        $categoriesData[$catId]['coas'][$coaCode] = [
                            'code' => $coaCode,
                            'title' => $coa->title,
                            'current_total' => 0.0,
                            'prev_total' => 0.0,
                        ];
                    }
                    $categoriesData[$catId]['coas'][$coaCode]['prev_total'] += $totalPrice;
                }
            } else {
                $coaCode = $item->account_code ?: 'unspecified';
                $coaTitle = $coa ? $coa->title : ($item->description ?: 'Tanpa Kode Akun');
                $coaGroupId = $coa ? $coa->coa_group_id : 0;
                $coaGroupName = ($coa && $coa->coaGroup) ? $coa->coaGroup->name : 'Tanpa Grup COA';
                $coaGroupCode = ($coa && $coa->coaGroup) ? $coa->coaGroup->code : '999';

                if (!isset($unmappedData[$coaGroupId])) {
                    $unmappedData[$coaGroupId] = [
                        'group_id' => $coaGroupId,
                        'group_code' => $coaGroupCode,
                        'group_name' => $coaGroupName,
                        'current_total' => 0.0,
                        'prev_total' => 0.0,
                        'coas' => []
                    ];
                }

                $unmappedData[$coaGroupId]['prev_total'] += $totalPrice;

                if (!isset($unmappedData[$coaGroupId]['coas'][$coaCode])) {
                    $unmappedData[$coaGroupId]['coas'][$coaCode] = [
                        'code' => $coaCode,
                        'title' => $coaTitle,
                        'current_total' => 0.0,
                        'prev_total' => 0.0,
                    ];
                }
                $unmappedData[$coaGroupId]['coas'][$coaCode]['prev_total'] += $totalPrice;
            }
        }

        // Sort coas in categoriesData
        foreach ($categoriesData as $key => &$cat) {
            if (!empty($cat['coas'])) {
                ksort($cat['coas']);
                $cat['coas'] = array_values($cat['coas']);
            }
        }
        unset($cat);

        // Sort unmapped groups by group_code
        uasort($unmappedData, function ($a, $b) {
            return strcasecmp($a['group_code'], $b['group_code']);
        });

        // Sort coas in unmapped groups
        foreach ($unmappedData as $groupId => &$group) {
            if (!empty($group['coas'])) {
                ksort($group['coas']);
                $group['coas'] = array_values($group['coas']);
            }
        }
        unset($group);

        return [
            'categories' => $categoriesData,
            'unmappedGroups' => array_values($unmappedData),
            'prevPeriod' => $prevSubmission ? ($prevSubmission->period->title ?? '-') : null,
        ];
    }

    public function getPrevVersionSnapshotProperty(): ?array
    {
        if ($this->submission->current_version <= 1) {
            return null;
        }

        $prevVersion = $this->submission->versions->firstWhere('version_number', $this->submission->current_version - 1);
        return $prevVersion ? $prevVersion->snapshot_data : null;
    }

    public function getRevisionChangesProperty(): array
    {
        $prevSnapshot = $this->prevVersionSnapshot;
        if (!$prevSnapshot) {
            return [];
        }

        $prevMap = [];
        foreach ($prevSnapshot as $wp) {
            $key = (!empty($wp['work_plan_id']) && !empty($wp['activity_id']))
                ? "{$wp['work_plan_id']}-{$wp['activity_id']}"
                : "{$wp['program_code']}-" . trim(strtolower($wp['program_name']));
            $prevMap[$key] = $wp;
        }

        $changes = [];

        foreach ($this->submission->workPlans as $wp) {
            $key = "{$wp->work_plan_id}-{$wp->activity_id}";
            $fallbackKey = "{$wp->program_code}-" . trim(strtolower($wp->program_name));

            $prevWp = $prevMap[$key] ?? ($prevMap[$fallbackKey] ?? null);

            $wpChanges = [
                'has_changes' => false,
                'activity_level_changes' => [],
                'added_items' => [],
                'removed_items' => [],
                'modified_items' => [],
                'added_bi_ids' => [],
                'modified_bi_map' => [],
            ];

            if ($prevWp) {
                // Check activity level changes
                if (($prevWp['description'] ?? '') !== ($wp->description ?? '')) {
                    $wpChanges['activity_level_changes'][] = [
                        'field' => 'Deskripsi / Tujuan',
                        'old' => $prevWp['description'] ?? '-',
                        'new' => $wp->description ?? '-',
                    ];
                    $wpChanges['has_changes'] = true;
                }
                if (($prevWp['output_target'] ?? '') !== ($wp->output_target ?? '')) {
                    $wpChanges['activity_level_changes'][] = [
                        'field' => 'Target Output',
                        'old' => $prevWp['output_target'] ?? '-',
                        'new' => $wp->output_target ?? '-',
                    ];
                    $wpChanges['has_changes'] = true;
                }
                if ((float)($prevWp['quantity'] ?? 0) !== (float)($wp->quantity ?? 0) || ($prevWp['unit'] ?? '') !== ($wp->unit ?? '')) {
                    $wpChanges['activity_level_changes'][] = [
                        'field' => 'Volume / Unit Kegiatan',
                        'old' => ($prevWp['quantity'] ?? 0) . ' ' . ($prevWp['unit'] ?? ''),
                        'new' => $wp->quantity . ' ' . $wp->unit,
                    ];
                    $wpChanges['has_changes'] = true;
                }

                // Match budget items
                $prevBis = $prevWp['budget_items'] ?? [];
                $currBis = $wp->budgetItems;

                $prevBiMap = [];
                foreach ($prevBis as $bi) {
                    $biKey = !empty($bi['id']) ? 'id:' . $bi['id'] : (!empty($bi['account_code']) ? $bi['account_code'] : 'desc:' . trim(strtolower($bi['description'])));
                    $prevBiMap[$biKey][] = $bi;
                }

                // Check added & modified
                foreach ($currBis as $bi) {
                    $biKey = 'id:' . $bi->id;
                    $matchedBi = null;

                    if (!empty($prevBiMap[$biKey])) {
                        $matchedBi = array_shift($prevBiMap[$biKey]);
                    } else {
                        // Fallback match
                        $fallbackBiKey = !empty($bi->account_code) ? $bi->account_code : 'desc:' . trim(strtolower($bi->description));
                        if (!empty($prevBiMap[$fallbackBiKey])) {
                            $matchedBi = array_shift($prevBiMap[$fallbackBiKey]);
                        }
                    }

                    if (!$matchedBi) {
                        $wpChanges['added_items'][] = [
                            'account_code' => $bi->account_code,
                            'description' => $bi->description,
                            'quantity' => $bi->quantity,
                            'unit' => $bi->unit,
                            'unit_price' => $bi->unit_price,
                            'total_price' => $bi->total_price,
                        ];
                        $wpChanges['added_bi_ids'][] = $bi->id;
                        $wpChanges['has_changes'] = true;
                    } else {
                        $isModified = ($matchedBi['quantity'] != $bi->quantity) ||
                                      ($matchedBi['unit_price'] != $bi->unit_price) ||
                                      (($matchedBi['unit'] ?? '') != ($bi->unit ?? '')) ||
                                      (($matchedBi['description'] ?? '') != ($bi->description ?? '')) ||
                                      (($matchedBi['remarks'] ?? '') != ($bi->remarks ?? ''));

                        if ($isModified) {
                            $wpChanges['modified_items'][] = [
                                'id' => $bi->id,
                                'account_code' => $bi->account_code,
                                'description' => $bi->description,
                                'old' => [
                                    'description' => $matchedBi['description'],
                                    'quantity' => $matchedBi['quantity'],
                                    'unit' => $matchedBi['unit'] ?? '',
                                    'unit_price' => $matchedBi['unit_price'],
                                    'total_price' => $matchedBi['total_price'],
                                    'remarks' => $matchedBi['remarks'] ?? '',
                                ],
                                'new' => [
                                    'description' => $bi->description,
                                    'quantity' => $bi->quantity,
                                    'unit' => $bi->unit,
                                    'unit_price' => $bi->unit_price,
                                    'total_price' => $bi->total_price,
                                    'remarks' => $bi->remarks,
                                ]
                            ];
                            $wpChanges['modified_bi_map'][$bi->id] = $matchedBi;
                            $wpChanges['has_changes'] = true;
                        }
                    }
                }

                // Remaining in $prevBiMap are removed
                foreach ($prevBiMap as $key => $items) {
                    foreach ($items as $matchedBi) {
                        $wpChanges['removed_items'][] = [
                            'account_code' => $matchedBi['account_code'],
                            'description' => $matchedBi['description'],
                            'quantity' => $matchedBi['quantity'],
                            'unit' => $matchedBi['unit'] ?? '',
                            'unit_price' => $matchedBi['unit_price'],
                            'total_price' => $matchedBi['total_price'],
                            'remarks' => $matchedBi['remarks'] ?? '',
                        ];
                        $wpChanges['has_changes'] = true;
                    }
                }
            } else {
                // This is a brand new activity in this version
                $wpChanges['has_changes'] = true;
                $wpChanges['activity_level_changes'][] = [
                    'field' => 'Kegiatan Baru',
                    'old' => '-',
                    'new' => 'Kegiatan ini ditambahkan pada revisi ini.',
                ];
                foreach ($wp->budgetItems as $bi) {
                    $wpChanges['added_items'][] = [
                        'account_code' => $bi->account_code,
                        'description' => $bi->description,
                        'quantity' => $bi->quantity,
                        'unit' => $bi->unit,
                        'unit_price' => $bi->unit_price,
                        'total_price' => $bi->total_price,
                    ];
                    $wpChanges['added_bi_ids'][] = $bi->id;
                }
            }

            $changes[$wp->id] = $wpChanges;
        }

        return $changes;
    }

    public function getWorkPlansListProperty()
    {
        return \App\Models\WorkPlan::where('approval_status', 'approved')->orderBy('code')->get();
    }

    public function getCoaOptionsListProperty()
    {
        return \App\Models\Coa::with(['coaGroup', 'cashflowGroup', 'differenceGroups'])->orderBy('code')->get();
    }

    public function getSatuanOptionsProperty()
    {
        return \App\Models\Satuan::orderBy('name')->get();
    }

    private function emptyNewBudgetItem(): array
    {
        return [
            'coa_id'                => null,
            'account_code'          => '',
            'description'           => '',
            'coa_group_name'        => '',
            'cashflow_group_name'   => '',
            'difference_group_name' => '',
            'unit'                  => '',
            'quantity'              => 1,
            'unit_2'                => '',
            'quantity_2'            => null,
            'unit_price'            => 0,
            'remarks'               => '',
        ];
    }

    public function addNewBudgetItem(): void
    {
        $this->newActivityBudgetItems[] = $this->emptyNewBudgetItem();
    }

    public function removeNewBudgetItem(int $idx): void
    {
        unset($this->newActivityBudgetItems[$idx]);
        $this->newActivityBudgetItems = array_values($this->newActivityBudgetItems);
    }

    public function duplicateNewBudgetItem(int $idx): void
    {
        $source = $this->newActivityBudgetItems[$idx];
        $newItem = array_merge($this->emptyNewBudgetItem(), [
            'coa_id'                => $source['coa_id'] ?? null,
            'account_code'          => $source['account_code'] ?? '',
            'description'           => $source['description'] ?? '',
            'coa_group_name'        => $source['coa_group_name'] ?? '',
            'cashflow_group_name'   => $source['cashflow_group_name'] ?? '',
            'difference_group_name' => $source['difference_group_name'] ?? '',
        ]);
        array_splice($this->newActivityBudgetItems, $idx + 1, 0, [$newItem]);
    }

    public function updateNewGroupCoa(int $biIndex, ?int $coaId): void
    {
        $oldCoaId = $this->newActivityBudgetItems[$biIndex]['coa_id'] ?? null;

        $coa = null;
        if ($coaId) {
            $coa = \App\Models\Coa::with(['coaGroup', 'cashflowGroup', 'differenceGroups'])->find($coaId);
        }

        $code = $coa ? $coa->code : '';
        $title = $coa ? $coa->title : '';
        $groupName = ($coa && $coa->coaGroup) ? $coa->coaGroup->name : '';
        $cashflowGroupName = ($coa && $coa->cashflowGroup) ? $coa->cashflowGroup->name : '';
        $differenceGroupName = ($coa && $coa->differenceGroups->isNotEmpty()) ? $coa->differenceGroups->pluck('name')->implode(', ') : '';

        if ($oldCoaId === null) {
            $this->newActivityBudgetItems[$biIndex]['coa_id'] = $coaId;
            $this->newActivityBudgetItems[$biIndex]['account_code'] = $code;
            $this->newActivityBudgetItems[$biIndex]['description'] = $title;
            $this->newActivityBudgetItems[$biIndex]['coa_group_name'] = $groupName;
            $this->newActivityBudgetItems[$biIndex]['cashflow_group_name'] = $cashflowGroupName;
            $this->newActivityBudgetItems[$biIndex]['difference_group_name'] = $differenceGroupName;
            return;
        }

        // Update all items in the group with the same old COA
        foreach ($this->newActivityBudgetItems as $idx => &$item) {
            if (($item['coa_id'] ?? null) === $oldCoaId) {
                $item['coa_id'] = $coaId;
                $item['account_code'] = $code;
                $item['description'] = $title;
                $item['coa_group_name'] = $groupName;
                $item['cashflow_group_name'] = $cashflowGroupName;
                $item['difference_group_name'] = $differenceGroupName;
            }
        }
        unset($item);
    }

    public function removeNewGroup(array $indices): void
    {
        foreach ($indices as $idx) {
            unset($this->newActivityBudgetItems[$idx]);
        }
        $this->newActivityBudgetItems = array_values($this->newActivityBudgetItems);
    }

    public function getActivitiesListProperty()
    {
        if (empty($this->selectedWorkPlanId)) {
            return collect();
        }
        $existingActivityIds = $this->submission->workPlans->pluck('activity_id')->filter()->toArray();
        return \App\Models\Activity::where('work_plan_id', $this->selectedWorkPlanId)
            ->where('approval_status', 'approved')
            ->where(function ($query) use ($existingActivityIds) {
                $query->whereNotIn('id', $existingActivityIds)
                    ->orWhere('id', $this->selectedActivityId);
            })
            ->orderBy('code')
            ->get();
    }

    public function updatedSelectedWorkPlanId($value): void
    {
        $this->selectedActivityId = null;
        $this->newActivityBudgetItems = [];
        $this->activityDescription = '';
        $this->activityOutputTarget = '';
        $this->activityUnit = 'Paket';
        $this->activityQuantity = 1;
    }

    public function updatedSelectedActivityId($value): void
    {
        $this->newActivityBudgetItems = [];
        $this->activityDescription = '';
        $this->activityOutputTarget = '';
        $this->activityUnit = 'Paket';
        $this->activityQuantity = 1;

        if (empty($value)) {
            return;
        }

        $activity = \App\Models\Activity::with(['coas.coaGroup', 'coas.cashflowGroup', 'coas.differenceGroups'])->find($value);
        if ($activity) {
            $this->activityDescription = $activity->description ?: '';
            if ($activity->coas->isNotEmpty()) {
                $this->newActivityBudgetItems = $activity->coas->map(function ($coa) {
                    return array_merge($this->emptyNewBudgetItem(), [
                        'coa_id'                => $coa->id,
                        'account_code'          => $coa->code,
                        'description'           => $coa->title,
                        'coa_group_name'        => $coa->coaGroup ? $coa->coaGroup->name : '',
                        'cashflow_group_name'   => $coa->cashflowGroup ? $coa->cashflowGroup->name : '',
                        'difference_group_name' => ($coa && $coa->differenceGroups->isNotEmpty()) ? $coa->differenceGroups->pluck('name')->implode(', ') : '',
                    ]);
                })->toArray();
            } else {
                $this->newActivityBudgetItems = [$this->emptyNewBudgetItem()];
            }
        }
    }

    public function enterEditMode(): void
    {
        $user = Auth::user();
        if (!$user->isVerifikator() || !$this->submission->canBeReviewedBy($user)) {
            session()->flash('error', __('Anda tidak memiliki wewenang untuk masuk ke mode edit.'));
            return;
        }

        $this->isEditMode = true;
        $this->resetAddActivityForm();
        $this->editCoas = [];
        foreach ($this->submission->workPlans as $wp) {
            foreach ($wp->budgetItems as $bi) {
                $this->editCoas[$bi->id] = $bi->account_code;
            }
        }
    }

    public function cancelEditMode(): void
    {
        $this->isEditMode = false;
        $this->editCoas = [];
        $this->resetAddActivityForm();
    }



    private function resetAddActivityForm(): void
    {
        $this->selectedWorkPlanId = null;
        $this->selectedActivityId = null;
        $this->activityDescription = '';
        $this->activityOutputTarget = '';
        $this->activityUnit = 'Paket';
        $this->activityQuantity = 1;
        $this->newActivityBudgetItems = [];
    }

    public function saveEditMode(): void
    {
        if (Setting::get('rkap_submission_status', 'open') === 'closed') {
            session()->flash('error', __('Pengisian usulan RKAP sedang ditutup. Anda tidak dapat menyimpan perubahan.'));
            return;
        }

        $user = Auth::user();
        if (!$user->isVerifikator() || !$this->submission->canBeReviewedBy($user)) {
            session()->flash('error', __('Anda tidak memiliki wewenang untuk menyimpan perubahan.'));
            return;
        }

        $shouldAddActivity = !empty($this->selectedWorkPlanId) || !empty($this->selectedActivityId);

        if ($shouldAddActivity) {
            $this->validate([
                'selectedWorkPlanId' => 'required|exists:work_plans,id',
                'selectedActivityId' => 'required|exists:activities,id',
                'activityQuantity' => 'required|integer|min:1',
                'activityUnit' => 'required|string',
                'activityDescription' => 'nullable|string',
                'activityOutputTarget' => 'nullable|string',
                'newActivityBudgetItems' => 'required|array|min:1',
                'newActivityBudgetItems.*.coa_id' => 'required|exists:coas,id',
                'newActivityBudgetItems.*.quantity' => 'required|numeric|gt:0',
                'newActivityBudgetItems.*.quantity_2' => 'nullable|numeric|gt:0',
                'newActivityBudgetItems.*.unit' => 'required|string',
                'newActivityBudgetItems.*.unit_price' => 'required|numeric|min:0',
                'newActivityBudgetItems.*.remarks' => 'nullable|string',
            ]);

            // Check if activity already exists in submission
            $exists = \App\Models\RkapWorkPlan::where('rkap_submission_id', $this->submission->id)
                ->where('activity_id', $this->selectedActivityId)
                ->exists();
            if ($exists) {
                $this->addError('selectedActivityId', __('Kegiatan ini sudah ada dalam pengajuan RKAP.'));
                return;
            }
        }

        DB::transaction(function () use ($shouldAddActivity) {
            // 1. Update COAs on existing budget items
            foreach ($this->editCoas as $biId => $newCoaCode) {
                $bi = \App\Models\RkapBudgetItem::find($biId);
                if ($bi && $bi->account_code !== $newCoaCode) {
                    $bi->update(['account_code' => $newCoaCode]);
                }
            }

            // 2. Add new program activity
            if ($shouldAddActivity) {
                $activity = \App\Models\Activity::findOrFail($this->selectedActivityId);
                $workPlan = \App\Models\WorkPlan::findOrFail($this->selectedWorkPlanId);

                $wp = \App\Models\RkapWorkPlan::create([
                    'rkap_submission_id' => $this->submission->id,
                    'work_plan_id' => $workPlan->id,
                    'activity_id' => $activity->id,
                    'program_code' => $activity->code,
                    'program_name' => $activity->title,
                    'description' => $this->activityDescription ?: null,
                    'output_target' => $this->activityOutputTarget ?: null,
                    'unit' => $this->activityUnit,
                    'quantity' => $this->activityQuantity,
                    'sort_order' => (\App\Models\RkapWorkPlan::where('rkap_submission_id', $this->submission->id)->max('sort_order') ?? 0) + 1,
                    'approval_status' => 'approved',
                    'added_by_verifier' => true,
                ]);

                foreach ($this->newActivityBudgetItems as $biData) {
                    $coaId = $biData['coa_id'];
                    $coa = \App\Models\Coa::findOrFail($coaId);
                    $unitPrice = (float) $biData['unit_price'];
                    $quantity = (float) $biData['quantity'];
                    $qty2 = !empty($biData['unit_2']) ? (float) ($biData['quantity_2'] ?? 1) : 1;
                    $totalPrice = $quantity * $qty2 * $unitPrice;

                    $bi = \App\Models\RkapBudgetItem::create([
                        'rkap_work_plan_id' => $wp->id,
                        'account_code' => $coa->code,
                        'description' => $coa->title,
                        'unit' => $biData['unit'],
                        'quantity' => $quantity,
                        'unit_2' => $biData['unit_2'] ?: null,
                        'quantity_2' => (!empty($biData['unit_2']) && $biData['quantity_2'] !== null && $biData['quantity_2'] !== '') ? (float) $biData['quantity_2'] : null,
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                        'remarks' => $biData['remarks'] ?: null,
                    ]);

                    // Distribute evenly across 12 months
                    $monthlyAmount = (float) ($totalPrice / 12);
                    for ($m = 1; $m <= 12; $m++) {
                        $bi->monthlies()->create([
                            'month' => $m,
                            'amount' => $monthlyAmount,
                        ]);
                        $bi->cashOuts()->create([
                            'month' => $m,
                            'amount' => $monthlyAmount,
                        ]);
                    }
                }

                $this->activityStatuses[$wp->id] = 'approved';
                $this->activityRevisionNotes[$wp->id] = '';
            }

            // Recalculate submission total budget
            $this->submission->refresh();
            $this->submission->calculateTotalBudget();
        });

        // Reset and close
        $this->isEditMode = false;
        $this->resetAddActivityForm();

        $this->submission->refresh()->load([
            'workPlans.budgetItems.monthlies',
            'workPlans.budgetItems.cashOuts',
            'workPlans.budgetItems.coa.coaGroup',
            'workPlans.budgetItems.coa.cashflowGroup',
            'workPlans.budgetItems.coa.differenceGroups'
        ]);

        session()->flash('message', __('Perubahan berhasil disimpan.'));
    }



    public function deleteWorkPlan(int $id): void
    {
        if (Setting::get('rkap_submission_status', 'open') === 'closed') {
            session()->flash('error', __('Pengisian usulan RKAP sedang ditutup. Anda tidak dapat menghapus kegiatan.'));
            return;
        }

        $user = Auth::user();
        if (!$user->isVerifikator() || !$this->submission->canBeReviewedBy($user)) {
            session()->flash('error', __('Anda tidak memiliki wewenang untuk menghapus kegiatan.'));
            return;
        }

        $wp = \App\Models\RkapWorkPlan::where('rkap_submission_id', $this->submission->id)
            ->where('added_by_verifier', true)
            ->findOrFail($id);

        DB::transaction(function () use ($wp) {
            $wp->delete();

            // Refresh loaded data and recalculate submission total budget
            $this->submission->refresh();
            $this->submission->calculateTotalBudget();
        });

        // Clean up arrays
        unset($this->activityStatuses[$id]);
        unset($this->activityRevisionNotes[$id]);

        $this->submission->refresh()->load([
            'workPlans.budgetItems.monthlies',
            'workPlans.budgetItems.cashOuts',
            'workPlans.budgetItems.coa.coaGroup',
            'workPlans.budgetItems.coa.cashflowGroup',
            'workPlans.budgetItems.coa.differenceGroups'
        ]);

        session()->flash('message', __('Program/Kegiatan berhasil dihapus.'));
    }

    public function deleteBudgetItem(int $id): void
    {
        if (Setting::get('rkap_submission_status', 'open') === 'closed') {
            session()->flash('error', __('Pengisian usulan RKAP sedang ditutup. Anda tidak dapat menghapus item anggaran.'));
            return;
        }

        $user = Auth::user();
        if (!$user->isVerifikator() || !$this->submission->canBeReviewedBy($user)) {
            session()->flash('error', __('Anda tidak memiliki wewenang untuk menghapus item anggaran.'));
            return;
        }

        $bi = \App\Models\RkapBudgetItem::whereHas('workPlan', function ($query) {
            $query->where('rkap_submission_id', $this->submission->id)
                ->where('added_by_verifier', true);
        })->findOrFail($id);

        DB::transaction(function () use ($bi) {
            $bi->delete();

            // Refresh loaded data and recalculate submission total budget
            $this->submission->refresh();
            $this->submission->calculateTotalBudget();
        });

        $this->submission->refresh()->load([
            'workPlans.budgetItems.monthlies',
            'workPlans.budgetItems.cashOuts',
            'workPlans.budgetItems.coa.coaGroup',
            'workPlans.budgetItems.coa.cashflowGroup',
            'workPlans.budgetItems.coa.differenceGroups'
        ]);

        session()->flash('message', __('Detail item anggaran berhasil dihapus.'));
    }

    public function render()
    {
        return view('livewire.rkap.rkap-approval-review', [
            'combinedWorkPlans' => $this->getCombinedWorkPlans(),
            'prevData' => $this->buildPreviousMap(),
            'helicopterViewData' => $this->getHelicopterViewData(),
            'revisionChanges' => $this->revisionChanges,
        ])->layout('layouts.contentNavbarLayout');
    }
}
