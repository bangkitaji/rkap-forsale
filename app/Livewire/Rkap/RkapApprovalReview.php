<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapSubmission;
use App\Models\RkapBudgetItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RkapApprovalReview extends Component
{
    public RkapSubmission $submission;
    public string $reviewComments = '';
    public string $revisionReason = '';
    public bool $showRevisionForm = false;
    public string $newComment = '';
    
    public array $activityStatuses = [];
    public array $activityRevisionNotes = [];

    public function mount(int $id): void
    {
        $this->submission = RkapSubmission::with([
            'bureau.department.directorate',
            'period',
            'creator',
            'workPlans.budgetItems.monthlies',
            'workPlans.budgetItems.cashOuts',
            'versions.creator',
            'approvals.user',
            'comments' => fn($q) => $q->topLevel()->with(['user', 'replies.user']),
        ])->findOrFail($id);

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
        $this->submission->refresh()->load(['workPlans.budgetItems.monthlies', 'workPlans.budgetItems.cashOuts']);
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
        $user = Auth::user();

        // 1. the approver can only approve the rkap submission if all activities are approved
        foreach ($this->submission->workPlans as $wp) {
            if (($this->activityStatuses[$wp->id] ?? 'pending') !== 'approved') {
                session()->flash('error', 'Gagal menyetujui: Semua kegiatan harus disetujui terlebih dahulu.');
                return;
            }
        }

        if (($user->isPresidentDirector() || $user->isDirekturFinance()) && $this->submission->status === 'pdir_review') {
            $status = $this->presidentApprovalStatus;
            if (!$status['is_ready']) {
                session()->flash('error', 'Gagal menyetujui: Belum semua departemen menyelesaikan pengajuan RKAP yang terverifikasi.');
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
        $this->submission->refresh()->load(['approvals.user', 'workPlans.budgetItems.monthlies', 'workPlans.budgetItems.cashOuts', 'versions.creator', 'comments.user', 'comments.replies.user']);
        session()->flash('message', 'RKAP berhasil disetujui.');
    }

    public function requestRevision(): void
    {
        // 2. the approver can only reject if there is one or more rejected activities
        // 3. the approver must give revision notes
        $hasRejected = false;
        foreach ($this->submission->workPlans as $wp) {
            $status = $this->activityStatuses[$wp->id] ?? 'pending';
            if ($status === 'rejected') {
                $hasRejected = true;
                $notes = trim($this->activityRevisionNotes[$wp->id] ?? '');
                if (empty($notes)) {
                    session()->flash('error', 'Gagal meminta revisi: Catatan revisi wajib diisi untuk semua kegiatan yang ditolak.');
                    return;
                }
            }
        }

        if (!$hasRejected) {
            session()->flash('error', 'Gagal meminta revisi: Minimal harus ada satu kegiatan yang ditolak.');
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
        $this->submission->refresh()->load(['approvals.user', 'workPlans.budgetItems.monthlies', 'workPlans.budgetItems.cashOuts', 'versions.creator', 'comments.user', 'comments.replies.user']);
        session()->flash('message', 'Permintaan revisi berhasil dikirim.');
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

        // Find the most recent prior approved submission for this bureau
        $prevSubmission = RkapSubmission::with([
                'workPlans.budgetItems.realizations',
                'workPlans.budgetItems.projections',
                'period',
            ])
            ->where('bureau_id', $bureauId)
            ->where('id', '!=', $this->submission->id)
            ->where('status', 'approved')
            ->whereHas('period', fn($q) => $q->where('year', '<', $currentPeriodYear))
            ->orderByDesc(DB::raw('(SELECT year FROM rkap_periods WHERE rkap_periods.id = rkap_submissions.rkap_period_id)'))
            ->first();

        if (!$prevSubmission) {
            return [];
        }

        $programs = [];
        $activities = [];
        $coas = [];
        $itemsMap = [];
        $itemCounters = [];

        foreach ($prevSubmission->workPlans as $wp) {
            $wpId = $wp->work_plan_id;
            $actId = $wp->activity_id;
            if (!$wpId) {
                continue;
            }

            if (!isset($programs[$wpId])) {
                $programs[$wpId] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
            }

            $actKey = "{$wpId}-{$actId}";
            if (!isset($activities[$actKey])) {
                $activities[$actKey] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
            }

            foreach ($wp->budgetItems as $bi) {
                $code = $bi->account_code;
                $budgetVal = (float) $bi->total_price;
                $realizationVal = (float) $bi->realizations->sum('amount');
                $projectionVal = (float) $bi->projections->sum('amount');

                $programs[$wpId]['budget'] += $budgetVal;
                $programs[$wpId]['realization'] += $realizationVal;
                $programs[$wpId]['projection'] += $projectionVal;

                $activities[$actKey]['budget'] += $budgetVal;
                $activities[$actKey]['realization'] += $realizationVal;
                $activities[$actKey]['projection'] += $projectionVal;

                if ($code) {
                    $coaKey = "{$wpId}-{$actId}-{$code}";
                    if (!isset($coas[$coaKey])) {
                        $coas[$coaKey] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
                    }
                    $coas[$coaKey]['budget'] += $budgetVal;
                    $coas[$coaKey]['realization'] += $realizationVal;
                    $coas[$coaKey]['projection'] += $projectionVal;

                    $baseKey = "{$wpId}-{$actId}-{$code}-" . trim(strtolower($bi->description));
                    $itemCounters[$baseKey] = ($itemCounters[$baseKey] ?? 0) + 1;
                    $itemKey = "{$baseKey}-" . $itemCounters[$baseKey];

                    $itemsMap[$itemKey] = [
                        'budget' => $budgetVal,
                        'realization' => $realizationVal,
                        'projection' => $projectionVal,
                    ];
                }
            }
        }

        return [
            'map' => [
                'programs' => $programs,
                'activities' => $activities,
                'coas' => $coas,
                'items' => $itemsMap,
            ],
            'period' => $prevSubmission->period?->title ?? '-',
            'total_budget' => (float) $prevSubmission->total_budget,
        ];
    }

    public function getCombinedWorkPlans(): array
    {
        $currentWps = $this->submission->workPlans;
        
        // Find previous submission
        $bureauId = $this->submission->bureau_id;
        $currentPeriodYear = $this->submission->period?->year ?? 0;

        $prevSubmission = \App\Models\RkapSubmission::with([
                'workPlans.budgetItems.realizations',
                'workPlans.budgetItems.projections',
                'period',
            ])
            ->where('bureau_id', $bureauId)
            ->where('id', '!=', $this->submission->id)
            ->where('status', 'approved')
            ->whereHas('period', fn($q) => $q->where('year', '<', $currentPeriodYear))
            ->orderByDesc(DB::raw('(SELECT year FROM rkap_periods WHERE rkap_periods.id = rkap_submissions.rkap_period_id)'))
            ->first();

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

        $prevSubmission = \App\Models\RkapSubmission::where('bureau_id', $bureauId)
            ->where('id', '!=', $this->submission->id)
            ->where('status', 'approved')
            ->whereHas('period', fn($q) => $q->where('year', '<', $currentPeriodYear))
            ->orderByDesc(DB::raw('(SELECT year FROM rkap_periods WHERE rkap_periods.id = rkap_submissions.rkap_period_id)'))
            ->first();

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
