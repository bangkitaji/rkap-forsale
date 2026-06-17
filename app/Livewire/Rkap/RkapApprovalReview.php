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
    }

    public function approve(): void
    {
        $user = Auth::user();

        if ($user->isPresidentDirector() || $user->isDirekturFinance()) {
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
        $this->validate(['revisionReason' => 'required|string|min:10']);

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

        if ($user->isPresidentDirector() || $user->isDirekturFinance()) {
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

                    $itemKey = "{$wpId}-{$actId}-{$code}-" . trim(strtolower($bi->description));
                    if (!isset($itemsMap[$itemKey])) {
                        $itemsMap[$itemKey] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
                    }
                    $itemsMap[$itemKey]['budget'] += $budgetVal;
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
            $items = [];
            foreach ($wp->budgetItems as $bi) {
                $itemKey = "{$bi->account_code}-" . trim(strtolower($bi->description));
                $items[$itemKey] = [
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
                    foreach ($prevWp->budgetItems as $prevBi) {
                        $prevItemKey = "{$prevBi->account_code}-" . trim(strtolower($prevBi->description));
                        if (!isset($items[$prevItemKey])) {
                            // This budget item was dropped in the new submission
                            $items[$prevItemKey] = [
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
            uksort($items, function($a, $b) {
                return strcasecmp(explode('-', $a)[0], explode('-', $b)[0]);
            });

            // Group items by account_code
            $groupedItems = [];
            foreach ($items as $item) {
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
                    $items = [];
                    foreach ($prevWp->budgetItems as $prevBi) {
                        $prevItemKey = "{$prevBi->account_code}-" . trim(strtolower($prevBi->description));
                        $items[$prevItemKey] = [
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
                    
                    uksort($items, function($a, $b) {
                        return strcasecmp(explode('-', $a)[0], explode('-', $b)[0]);
                    });

                    // Group items by account_code
                    $groupedItems = [];
                    foreach ($items as $item) {
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

    public function render()
    {
        return view('livewire.rkap.rkap-approval-review', [
            'combinedWorkPlans' => $this->getCombinedWorkPlans(),
            'prevData' => $this->buildPreviousMap(),
            'helicopterViewData' => $this->getHelicopterViewData(),
        ])->layout('layouts.contentNavbarLayout');
    }
}
