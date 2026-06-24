<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapSubmission;
use App\Models\RkapPeriod;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use App\Models\WorkPlan;
use App\Models\Activity;
use App\Models\Coa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Livewire\Traits\HandlesDistribution;

class RkapSubmissionForm extends Component
{
    use HandlesDistribution;

    public ?int $submissionId = null;
    public ?int $periodId = null;

    // Submission fields
    public string $notes = '';

    // Work plans (array of nested work plan & activity data)
    public array $workPlans = [];

    public ?RkapSubmission $submission = null;
    public ?RkapPeriod $period = null;

    /**
     * Month labels (Indonesian).
     */
    public const MONTH_LABELS = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    public function mount(?int $periodId = null, ?int $id = null): void
    {
        $user = Auth::user();

        if ($id) {
            $this->submission = RkapSubmission::findOrFail($id);
            $this->submissionId = $id;
            $this->periodId = $this->submission->rkap_period_id;
            $this->period = $this->submission->period;

            // Authorization: ensure current user can edit this submission
            if (!$this->submission->canBeEditedBy($user)) {
                abort(403, 'Anda tidak memiliki akses untuk mengedit pengajuan ini.');
            }

            // Eager-load relations; scope realizations to this period explicitly
            $this->submission->load([
                'workPlans.budgetItems.monthlies',
                'workPlans.budgetItems.cashOuts',
                'workPlans.budgetItems.realizations' => fn ($q) => $q->where('rkap_period_id', $this->periodId),
                'approvals.user',
            ]);
            $this->notes = $this->submission->notes ?? '';
            $this->loadWorkPlans();
        } elseif ($periodId) {
            if (!$user->bureau_id) {
                abort(403, 'Anda harus terasosiasi dengan Biro untuk membuat pengajuan.');
            }

            $exists = RkapSubmission::where('rkap_period_id', $periodId)
                ->where('bureau_id', $user->bureau_id)
                ->exists();
            if ($exists) {
                session()->flash('error', 'Biro Anda sudah membuat pengajuan RKAP untuk periode ini.');
                $this->redirectRoute('rkap-submissions');
                return;
            }

            $this->periodId = $periodId;
            $this->period = RkapPeriod::findOrFail($periodId);
            $this->addWorkPlan();
        }
    }

    /**
     * Reset and update states whenever fields change dynamically.
     */
    public function updated(string $name): void
    {
        // Sanitize unit_price: reset null/empty to 0, strip leading zeros
        if (preg_match('/^workPlans\.(\d+)\.activities\.(\d+)\.budget_items\.(\d+)\.unit_price$/', $name, $m)) {
            $wpIdx = (int) $m[1];
            $actIdx = (int) $m[2];
            $biIdx = (int) $m[3];
            $val = $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx]['unit_price'] ?? null;

            if ($val === null || $val === '') {
                $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx]['unit_price'] = 0;
            } else {
                $cleaned = ltrim((string) $val, '0');
                $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx]['unit_price'] =
                    ($cleaned === '' || $cleaned === '.') ? 0 : (int) $cleaned;
            }
        }

        // COA change in a budget item
        if (preg_match('/^workPlans\.(\d+)\.activities\.(\d+)\.budget_items\.(\d+)\.coa_id$/', $name, $m)) {
            $wpIdx = (int) $m[1];
            $actIdx = (int) $m[2];
            $biIdx = (int) $m[3];
            $coaId = $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx]['coa_id'] ?? null;
            $this->updateGroupCoa($wpIdx, $actIdx, $biIdx, $coaId);
        }

        // Work plan selection changes
        if (preg_match('/^workPlans\.(\d+)\.work_plan_id$/', $name, $m)) {
            $wpIdx    = (int) $m[1];
            $newWpId  = $this->workPlans[$wpIdx]['work_plan_id'] ?? null;

            // Reject duplicate: if another card already uses this work_plan_id, clear it
            if ($newWpId) {
                $usedByOthers = $this->getUsedWorkPlanIds(excludeIndex: $wpIdx);
                if (in_array((int) $newWpId, $usedByOthers, true)) {
                    $this->workPlans[$wpIdx]['work_plan_id'] = null;
                    $this->dispatch('work-plan-duplicate-rejected');
                    return;
                }
            }

            // Reset activities list when work plan changes
            foreach ($this->workPlans[$wpIdx]['activities'] as $actIdx => $act) {
                $this->workPlans[$wpIdx]['activities'][$actIdx]['activity_id'] = null;
                $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'] = [$this->emptyBudgetItem()];
            }
        }

        // Activity selection changes within a work plan
        if (preg_match('/^workPlans\.(\d+)\.activities\.(\d+)\.activity_id$/', $name, $m)) {
            $wpIdx      = (int) $m[1];
            $actIdx     = (int) $m[2];
            $activityId = $this->workPlans[$wpIdx]['activities'][$actIdx]['activity_id'] ?? null;

            // Reject duplicate: if another slot in the same work plan already uses this activity, clear it
            if ($activityId) {
                $usedByOthers = $this->getUsedActivityIds($wpIdx, excludeActIndex: $actIdx);
                if (in_array((int) $activityId, $usedByOthers, true)) {
                    $this->workPlans[$wpIdx]['activities'][$actIdx]['activity_id'] = null;
                    $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'] = [$this->emptyBudgetItem()];
                    $this->dispatch('activity-duplicate-rejected');
                    return;
                }
            }

            if ($activityId) {
                $activity = Activity::with('coas.coaGroup')->find($activityId);

                // Auto-populate work_plan_id on the parent card if not set
                if ($activity && $activity->work_plan_id && empty($this->workPlans[$wpIdx]['work_plan_id'])) {
                    $this->workPlans[$wpIdx]['work_plan_id'] = $activity->work_plan_id;
                }

                if ($activity && $activity->coas->isNotEmpty()) {
                    $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'] = $activity->coas->map(function ($coa) {
                        return array_merge($this->emptyBudgetItem(), [
                            'coa_id' => $coa->id,
                            'account_code' => $coa->code,
                            'description' => $coa->title,
                            'coa_group_name' => $coa->coaGroup ? $coa->coaGroup->name : '',
                        ]);
                    })->toArray();
                } else {
                    $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'] = [$this->emptyBudgetItem()];
                }
            } else {
                $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'] = [$this->emptyBudgetItem()];
            }
        }
    }

    /**
     * Return a blank budget item array including monthly keys.
     */
    private function emptyBudgetItem(): array
    {
        return [
            'id'                        => null,
            'coa_id'                    => null,
            'account_code'              => '',
            'description'               => '',
            'coa_group_name'            => '',
            'unit'                      => '',
            'quantity'                  => 1,
            'unit_2'                    => '',
            'quantity_2'                => null,
            'unit_price'                => 0,
            'remarks'                   => '',
            'monthly_distribution'      => [],
            'distribution_months'       => [],
            'cash_out_distribution'     => [],
            'cash_out_months'           => [],
            'realization_distribution'  => [],
            'realization_months'        => [],
        ];
    }

    /**
     * Return an empty activity block.
     */
    private function emptyActivityBlock(int $sortOrder): array
    {
        return [
            'id'              => null,
            'activity_id'     => null,
            'description'     => '',
            'output_target'   => '',
            'unit'            => '',
            'quantity'        => 1,
            'sort_order'      => $sortOrder,
            'approval_status' => 'pending',
            'revision_notes'  => '',
            'budget_items'    => [$this->emptyBudgetItem()],
        ];
    }

    /**
     * All available WorkPlans for select list.
     */
    public function getWorkPlanOptionsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return WorkPlan::where('approval_status', 'approved')
            ->with(['activities' => fn($q) => $q->where('approval_status', 'approved')])
            ->orderBy('code')
            ->get();
    }

    /**
     * Collect all work_plan_ids currently selected across all cards (excluding a given index).
     */
    public function getUsedWorkPlanIds(int $excludeIndex = -1): array
    {
        $used = [];
        foreach ($this->workPlans as $idx => $wp) {
            if ($idx !== $excludeIndex && !empty($wp['work_plan_id'])) {
                $used[] = (int) $wp['work_plan_id'];
            }
        }
        return $used;
    }

    /**
     * Get Work Plans for a specific index, excluding already-used ones in other cards.
     */
    public function getWorkPlanOptionsForIndex(int $wpIndex): \Illuminate\Database\Eloquent\Collection
    {
        $usedIds = $this->getUsedWorkPlanIds(excludeIndex: $wpIndex);

        $activityId = null;
        foreach (($this->workPlans[$wpIndex]['activities'] ?? []) as $act) {
            if (!empty($act['activity_id'])) {
                $activityId = $act['activity_id'];
                break;
            }
        }

        if (!$activityId) {
            return WorkPlan::where('approval_status', 'approved')
                ->orderBy('code')
                ->when(!empty($usedIds), fn($q) => $q->whereNotIn('id', $usedIds))
                ->get();
        }

        $activity = Activity::find($activityId);
        if ($activity && $activity->work_plan_id) {
            return WorkPlan::where('approval_status', 'approved')
                ->where('id', $activity->work_plan_id)
                ->when(!empty($usedIds), fn($q) => $q->whereNotIn('id', $usedIds))
                ->orderBy('code')
                ->get();
        }

        return WorkPlan::where('approval_status', 'approved')
            ->orderBy('code')
            ->when(!empty($usedIds), fn($q) => $q->whereNotIn('id', $usedIds))
            ->get();
    }

    /**
     * All available COAs for selection.
     */
    public function getCoaOptionsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return Coa::orderBy('code')->get();
    }

    /**
     * All available Satuans for selection.
     */
    public function getSatuanOptionsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return \App\Models\Satuan::orderBy('name')->get();
    }

    /**
     * Collect all activity_ids already selected within a work plan, optionally excluding one slot.
     */
    public function getUsedActivityIds(int $wpIndex, int $excludeActIndex = -1): array
    {
        $used = [];
        foreach (($this->workPlans[$wpIndex]['activities'] ?? []) as $idx => $act) {
            if ($idx !== $excludeActIndex && !empty($act['activity_id'])) {
                $used[] = (int) $act['activity_id'];
            }
        }
        return $used;
    }

    /**
     * Activities filtered by parent work plan, excluding already-selected ones in other slots.
     */
    public function getActivitiesForIndex(int $wpIndex, int $excludeActIndex = -1): \Illuminate\Database\Eloquent\Collection
    {
        $workPlanId = $this->workPlans[$wpIndex]['work_plan_id'] ?? null;
        $usedIds    = $this->getUsedActivityIds($wpIndex, excludeActIndex: $excludeActIndex);

        if (!$workPlanId) {
            return Activity::where('approval_status', 'approved')
                ->orderBy('code')
                ->when(!empty($usedIds), fn($q) => $q->whereNotIn('id', $usedIds))
                ->get();
        }

        return Activity::where('approval_status', 'approved')
            ->where('work_plan_id', $workPlanId)
            ->when(!empty($usedIds), fn($q) => $q->whereNotIn('id', $usedIds))
            ->orderBy('code')
            ->get();
    }

    public function getCoaOptionsForIndex(int $wpIndex): \Illuminate\Database\Eloquent\Collection
    {
        return $this->coaOptions;
    }

    public function getCoaDisplayLabel(int $wpIndex, int $actIndex, int $biIndex): string
    {
        $bi = $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex] ?? null;
        if (!$bi) {
            return '';
        }

        if (isset($bi['coa_id']) && $bi['coa_id']) {
            $coa = $this->coaOptions->firstWhere('id', $bi['coa_id']);
            if ($coa) {
                return $coa->code . ' — ' . $coa->title;
            }
        }

        if (!empty($bi['account_code']) || !empty($bi['description'])) {
            return trim(($bi['account_code'] ?? '') . (!empty($bi['description']) ? ' — ' . ($bi['description'] ?? '') : ''));
        }

        return '';
    }

    // ── Monthly Distribution Methods ──

    public function toggleMonth(int $wpIdx, int $actIdx, int $biIdx, int $month): void
    {
        $this->toggleMonthDistribution($wpIdx, $actIdx, $biIdx, $month, 'distribution_months', 'monthly_distribution');
    }

    public function selectAllMonths(int $wpIdx, int $actIdx, int $biIdx): void
    {
        $this->selectAllMonthsDistribution($wpIdx, $actIdx, $biIdx, 'distribution_months', 'monthly_distribution');
    }

    public function distributeEvenly(int $wpIdx, int $actIdx, int $biIdx): void
    {
        $this->distributeEvenlyDistribution($wpIdx, $actIdx, $biIdx, 'distribution_months', 'monthly_distribution');
    }

    public function getMonthlyRemainder(int $wpIdx, int $actIdx, int $biIdx): float
    {
        return $this->getRemainderDistribution($wpIdx, $actIdx, $biIdx, 'monthly_distribution');
    }

    // ── Cash Out Plan Methods ──

    public function toggleCashOutMonth(int $wpIdx, int $actIdx, int $biIdx, int $month): void
    {
        $this->toggleMonthDistribution($wpIdx, $actIdx, $biIdx, $month, 'cash_out_months', 'cash_out_distribution');
    }

    public function selectAllCashOutMonths(int $wpIdx, int $actIdx, int $biIdx): void
    {
        $this->selectAllMonthsDistribution($wpIdx, $actIdx, $biIdx, 'cash_out_months', 'cash_out_distribution');
    }

    public function distributeCashOutEvenly(int $wpIdx, int $actIdx, int $biIdx): void
    {
        $this->distributeEvenlyDistribution($wpIdx, $actIdx, $biIdx, 'cash_out_months', 'cash_out_distribution');
    }

    public function getCashOutRemainder(int $wpIdx, int $actIdx, int $biIdx): float
    {
        return $this->getRemainderDistribution($wpIdx, $actIdx, $biIdx, 'cash_out_distribution');
    }

    // ── Realization Methods ──

    public function toggleRealizationMonth(int $wpIdx, int $actIdx, int $biIdx, int $month): void
    {
        $this->toggleMonthDistribution($wpIdx, $actIdx, $biIdx, $month, 'realization_months', 'realization_distribution');
    }

    public function selectAllRealizationMonths(int $wpIdx, int $actIdx, int $biIdx): void
    {
        $this->selectAllMonthsDistribution($wpIdx, $actIdx, $biIdx, 'realization_months', 'realization_distribution');
    }

    public function distributeRealizationEvenly(int $wpIdx, int $actIdx, int $biIdx): void
    {
        $this->distributeEvenlyDistribution($wpIdx, $actIdx, $biIdx, 'realization_months', 'realization_distribution');
    }

    public function getRealizationRemainder(int $wpIdx, int $actIdx, int $biIdx): float
    {
        return $this->getRemainderDistribution($wpIdx, $actIdx, $biIdx, 'realization_distribution');
    }

    // ── Work Plan / Activity / Budget Item Management ──

    private function loadWorkPlans(): void
    {
        $grouped = $this->submission->workPlans->groupBy('work_plan_id');

        // Pre-load COAs by account_code to avoid N+1 query
        $accountCodes = $this->submission->workPlans->flatMap(fn($wp) => $wp->budgetItems->pluck('account_code'))->filter()->unique()->toArray();
        $coaMap = Coa::with('coaGroup')->whereIn('code', $accountCodes)->get()->keyBy('code');

        $this->workPlans = [];
        foreach ($grouped as $wpId => $rkapWorkPlans) {
            $activities = [];
            foreach ($rkapWorkPlans as $wp) {
                $activities[] = [
                    'id'              => $wp->id,
                    'activity_id'     => $wp->activity_id,
                    'description'     => $wp->description ?? '',
                    'output_target'   => $wp->output_target ?? '',
                    'unit'            => $wp->unit ?? '',
                    'quantity'        => $wp->quantity,
                    'sort_order'      => $wp->sort_order,
                    'approval_status' => $wp->approval_status ?? 'pending',
                    'revision_notes'  => $wp->revision_notes ?? '',
                    'budget_items'    => $wp->budgetItems->map(function ($bi) use ($coaMap) {
                        $coa = $coaMap->get($bi->account_code);
                        return [
                            'id'                       => $bi->id,
                            'coa_id'                   => $coa ? $coa->id : null,
                            'account_code'             => $bi->account_code ?? '',
                            'description'              => $bi->description,
                            'coa_group_name'           => $coa && $coa->coaGroup ? $coa->coaGroup->name : '',
                            'unit'                     => $bi->unit ?? '',
                            'quantity'                 => $bi->quantity,
                            'unit_2'                   => $bi->unit_2 ?? '',
                            'quantity_2'               => $bi->quantity_2,
                            'unit_price'               => $bi->unit_price,
                            'remarks'                  => $bi->remarks ?? '',
                            'monthly_distribution'     => $bi->monthlies->pluck('amount', 'month')->map(fn ($v) => (float) $v)->toArray(),
                            'distribution_months'      => $bi->monthlies->pluck('month')->toArray(),
                            'cash_out_distribution'    => $bi->cashOuts->pluck('amount', 'month')->map(fn ($v) => (float) $v)->toArray(),
                            'cash_out_months'          => $bi->cashOuts->pluck('month')->toArray(),
                            'realization_distribution' => $bi->realizations->pluck('amount', 'month')->map(fn ($v) => (float) $v)->toArray(),
                            'realization_months'       => $bi->realizations->pluck('month')->toArray(),
                        ];
                    })->toArray(),
                ];
            }

            $this->workPlans[] = [
                'work_plan_id' => $wpId,
                'activities' => $activities,
            ];
        }

        if (empty($this->workPlans)) {
            $this->addWorkPlan();
        }
    }

    public function addWorkPlan(): void
    {
        // Don't allow adding more work plan cards than there are available work plans
        $usedIds   = $this->getUsedWorkPlanIds();
        $totalPlans = WorkPlan::count();
        if (count($this->workPlans) >= $totalPlans) {
            return;
        }

        $this->workPlans[] = [
            'work_plan_id'  => null,
            'activities'    => [
                $this->emptyActivityBlock(0)
            ],
        ];
    }

    public function removeWorkPlan(int $index): void
    {
        unset($this->workPlans[$index]);
        $this->workPlans = array_values($this->workPlans);
    }

    public function addActivity(int $wpIndex): void
    {
        // Forbid adding more activity slots than there are available (unselected) activities
        $availableActivities = $this->getActivitiesForIndex($wpIndex);
        if ($availableActivities->count() <= 0) {
            return;
        }

        $sortOrder = count($this->workPlans[$wpIndex]['activities']);
        $this->workPlans[$wpIndex]['activities'][] = $this->emptyActivityBlock($sortOrder);
    }

    public function removeActivity(int $wpIndex, int $actIndex): void
    {
        unset($this->workPlans[$wpIndex]['activities'][$actIndex]);
        $this->workPlans[$wpIndex]['activities'] = array_values($this->workPlans[$wpIndex]['activities']);

        if (empty($this->workPlans[$wpIndex]['activities'])) {
            $this->removeWorkPlan($wpIndex);
        }
    }

    public function addBudgetItem(int $wpIndex, int $actIndex): void
    {
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][] = $this->emptyBudgetItem();
    }

    public function removeBudgetItem(int $wpIndex, int $actIndex, int $biIndex): void
    {
        unset($this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]);
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'] = array_values($this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items']);
    }

    public function duplicateBudgetItem(int $wpIndex, int $actIndex, int $biIndex): void
    {
        $sourceItem = $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex];

        $newItem = array_merge($this->emptyBudgetItem(), [
            'coa_id' => $sourceItem['coa_id'] ?? null,
            'account_code' => $sourceItem['account_code'] ?? '',
            'description' => $sourceItem['description'] ?? '',
            'coa_group_name' => $sourceItem['coa_group_name'] ?? '',
        ]);

        array_splice($this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'], $biIndex + 1, 0, [$newItem]);
    }

    public function updateGroupCoa(int $wpIndex, int $actIndex, int $biIndex, ?int $coaId): void
    {
        $oldCoaId = $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['coa_id'] ?? null;

        $coa = null;
        if ($coaId) {
            $coa = Coa::with('coaGroup')->find($coaId);
        }

        $code = $coa ? $coa->code : '';
        $title = $coa ? $coa->title : '';
        $groupName = ($coa && $coa->coaGroup) ? $coa->coaGroup->name : '';

        if ($oldCoaId === null) {
            $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['coa_id'] = $coaId;
            $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['account_code'] = $code;
            $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['description'] = $title;
            $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['coa_group_name'] = $groupName;
            if ($coaId === null) {
                $this->clearBudgetItemDetails($wpIndex, $actIndex, $biIndex);
            }
            return;
        }

        $idx = $biIndex;
        while (
            $idx < count($this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items']) &&
            ($this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$idx]['coa_id'] ?? null) === $oldCoaId
        ) {
            $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$idx]['coa_id'] = $coaId;
            $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$idx]['account_code'] = $code;
            $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$idx]['description'] = $title;
            $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$idx]['coa_group_name'] = $groupName;
            if ($coaId === null) {
                $this->clearBudgetItemDetails($wpIndex, $actIndex, $idx);
            }
            $idx++;
        }

        $idx = $biIndex - 1;
        while (
            $idx >= 0 &&
            ($this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$idx]['coa_id'] ?? null) === $oldCoaId
        ) {
            $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$idx]['coa_id'] = $coaId;
            $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$idx]['account_code'] = $code;
            $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$idx]['description'] = $title;
            $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$idx]['coa_group_name'] = $groupName;
            if ($coaId === null) {
                $this->clearBudgetItemDetails($wpIndex, $actIndex, $idx);
            }
            $idx--;
        }
    }

    private function clearBudgetItemDetails(int $wpIndex, int $actIndex, int $biIndex): void
    {
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['unit']                      = '';
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['quantity']                  = 1;
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['unit_2']                    = '';
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['quantity_2']                  = null;
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['unit_price']                = 0;
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['remarks']                   = '';
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['monthly_distribution']      = [];
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['distribution_months']       = [];
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['cash_out_distribution']     = [];
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['cash_out_months']           = [];
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['realization_distribution']  = [];
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['realization_months']        = [];
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$biIndex]['coa_group_name']            = '';
    }

    public function removeGroup(int $wpIndex, int $actIndex, array $indices): void
    {
        rsort($indices);
        foreach ($indices as $idx) {
            unset($this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'][$idx]);
        }
        $this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items'] = array_values($this->workPlans[$wpIndex]['activities'][$actIndex]['budget_items']);
    }

    public function getGrandTotalProperty(): float
    {
        $total = 0;
        foreach ($this->workPlans as $wp) {
            foreach (($wp['activities'] ?? []) as $act) {
                foreach (($act['budget_items'] ?? []) as $bi) {
                    $qty2 = (!empty($bi['unit_2'])) ? (float) ($bi['quantity_2'] ?? 1) : 1;
                    $total += (float) ($bi['quantity'] ?? 0) * $qty2 * (float) ($bi['unit_price'] ?? 0);
                }
            }
        }
        return $total;
    }

    protected function rules(): array
    {
        return [
            'notes' => 'nullable|string',
            'workPlans' => 'required|array|min:1',
            'workPlans.*.work_plan_id' => 'required|integer|exists:work_plans,id',
            'workPlans.*.activities' => 'required|array|min:1',
            'workPlans.*.activities.*.activity_id' => 'nullable|integer|exists:activities,id',
            'workPlans.*.activities.*.quantity' => 'required|integer|min:1',
            'workPlans.*.activities.*.budget_items' => 'required|array|min:1',
            'workPlans.*.activities.*.budget_items.*.coa_id' => 'required|integer|exists:coas,id',
            'workPlans.*.activities.*.budget_items.*.quantity' => 'required|integer|min:1',
            'workPlans.*.activities.*.budget_items.*.unit_2' => 'nullable|string',
            'workPlans.*.activities.*.budget_items.*.quantity_2' => 'nullable|integer|min:1',
            'workPlans.*.activities.*.budget_items.*.unit_price' => 'required|numeric|min:0',
        ];
    }

    public function saveDraft(): void
    {
        $this->validate();
        $this->validateNoDuplicateWorkPlans();
        $this->validateNoDuplicateActivities();
        $this->validateBudgetItemsCoaMapping();
        $this->saveSubmission('draft');
        session()->flash('message', 'Draf RKAP berhasil disimpan.');
        $this->dispatch('form-saved', message: 'Draf RKAP berhasil disimpan.');
    }

    public function submitForReview(): void
    {
        $this->validate();
        $this->validateNoDuplicateWorkPlans();
        $this->validateNoDuplicateActivities();
        $this->validateBudgetItemsCoaMapping();
        $this->validateMonthlyDistribution();
        $this->validateCashOutPlan();

        $submission = $this->saveSubmission('draft');

        // Reset rejected activities back to pending for the new review cycle
        foreach ($submission->workPlans as $wp) {
            if ($wp->approval_status === 'rejected') {
                $wp->update(['approval_status' => 'pending']);
            }
        }

        if ($submission->status === 'draft') {
            $submission->submit();
        }

        session()->flash('message', 'RKAP berhasil diajukan untuk peninjauan (review).');
        $this->redirectRoute('rkap-submissions');
    }

    private function validateNoDuplicateWorkPlans(): void
    {
        $seen = [];
        foreach (($this->workPlans ?? []) as $wpIdx => $wpData) {
            $wpId = $wpData['work_plan_id'] ?? null;
            if (!$wpId) {
                continue;
            }
            if (in_array((int) $wpId, $seen, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "workPlans.{$wpIdx}.work_plan_id" => 'Program Kerja yang sama tidak boleh dipilih lebih dari satu kali dalam satu pengajuan RKAP.',
                ]);
            }
            $seen[] = (int) $wpId;
        }
    }

    private function validateNoDuplicateActivities(): void
    {
        foreach (($this->workPlans ?? []) as $wpIdx => $wpData) {
            $seen = [];
            foreach (($wpData['activities'] ?? []) as $actIdx => $actData) {
                $actId = $actData['activity_id'] ?? null;
                if (!$actId) {
                    continue;
                }
                if (in_array((int) $actId, $seen, true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "workPlans.{$wpIdx}.activities.{$actIdx}.activity_id" => 'Kegiatan yang sama tidak boleh dipilih lebih dari satu kali dalam Program Kerja yang sama.',
                    ]);
                }
                $seen[] = (int) $actId;
            }
        }
    }

    private function validateBudgetItemsCoaMapping(): void
    {
        foreach (($this->workPlans ?? []) as $wpIdx => $wpData) {
            foreach (($wpData['activities'] ?? []) as $actIdx => $actData) {
                $activityId = $actData['activity_id'] ?? null;

                foreach (($actData['budget_items'] ?? []) as $biIdx => $biData) {
                    $coaId = $biData['coa_id'] ?? null;

                    if (!$activityId && !empty($coaId)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'workPlans' => 'Activity harus dipilih jika COA telah dipilih pada salah satu baris.',
                        ]);
                    }
                }
            }
        }
    }

    private function validateMonthlyDistribution(): void
    {
        foreach ($this->workPlans as $wpIdx => $wpData) {
            foreach ($wpData['activities'] as $actIdx => $actData) {
                foreach ($actData['budget_items'] as $biIdx => $biData) {
                    $qty2 = (!empty($biData['unit_2'])) ? (float) ($biData['quantity_2'] ?? 1) : 1;
                    $total = (float) ($biData['quantity'] ?? 0) * $qty2 * (float) ($biData['unit_price'] ?? 0);
                    $months = $biData['distribution_months'] ?? [];
                    $distribution = $biData['monthly_distribution'] ?? [];

                    if (empty($months)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            "workPlans.{$wpIdx}.activities.{$actIdx}.budget_items.{$biIdx}.monthly" => 'Distribusi bulanan wajib diisi. Pilih minimal 1 bulan.',
                        ]);
                    }

                    $allocated = 0;
                    foreach ($distribution as $month => $amount) {
                        $allocated += (float) $amount;
                    }

                    if (abs($total - $allocated) > 0.01) {
                        $diff = $total - $allocated;
                        $diffFormatted = number_format(abs($diff), 0, ',', '.');
                        $direction = $diff > 0 ? "kurang Rp {$diffFormatted}" : "lebih Rp {$diffFormatted}";

                        throw \Illuminate\Validation\ValidationException::withMessages([
                            "workPlans.{$wpIdx}.activities.{$actIdx}.budget_items.{$biIdx}.monthly" => "Total distribusi bulanan harus sama dengan total item (Rp " . number_format($total, 0, ',', '.') . "). Saat ini {$direction}.",
                        ]);
                    }
                }
            }
        }
    }

    private function validateCashOutPlan(): void
    {
        foreach ($this->workPlans as $wpIdx => $wpData) {
            foreach ($wpData['activities'] as $actIdx => $actData) {
                foreach ($actData['budget_items'] as $biIdx => $biData) {
                    $qty2 = (!empty($biData['unit_2'])) ? (float) ($biData['quantity_2'] ?? 1) : 1;
                    $total = (float) ($biData['quantity'] ?? 0) * $qty2 * (float) ($biData['unit_price'] ?? 0);
                    $months = $biData['cash_out_months'] ?? [];
                    $distribution = $biData['cash_out_distribution'] ?? [];

                    if (empty($months)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            "workPlans.{$wpIdx}.activities.{$actIdx}.budget_items.{$biIdx}.cash_out" => 'Rencana kas keluar wajib diisi. Pilih minimal 1 bulan.',
                        ]);
                    }

                    $allocated = 0;
                    foreach ($distribution as $month => $amount) {
                        $allocated += (float) $amount;
                    }

                    if ($allocated - $total > 0.01) {
                        $diff = $allocated - $total;
                        $diffFormatted = number_format($diff, 0, ',', '.');

                        throw \Illuminate\Validation\ValidationException::withMessages([
                            "workPlans.{$wpIdx}.activities.{$actIdx}.budget_items.{$biIdx}.cash_out" => "Total rencana kas keluar tidak boleh melebihi total item (Rp " . number_format($total, 0, ',', '.') . "). Saat ini lebih Rp {$diffFormatted}.",
                        ]);
                    }

                    if ($allocated <= 0) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            "workPlans.{$wpIdx}.activities.{$actIdx}.budget_items.{$biIdx}.cash_out" => "Jumlah rencana kas keluar harus lebih besar dari Rp 0.",
                        ]);
                    }
                }
            }
        }
    }

    private function saveSubmission(string $status): RkapSubmission
    {
        return DB::transaction(function () use ($status) {
            $user = Auth::user();

            $data = [
                'rkap_period_id' => $this->periodId,
                'bureau_id' => $user->bureau_id,
                'created_by' => $user->id,
                'notes' => $this->notes ?: null,
            ];

            if (!$this->submissionId) {
                $data['status'] = $status;
            }

            $submission = RkapSubmission::updateOrCreate(
                ['id' => $this->submissionId],
                $data
            );

            // Collect IDs to pre-load and avoid N+1 queries
            $coaIds = [];
            $activityIds = [];
            $wpIds = [];
            $rkapWpIds = [];
            foreach ($this->workPlans as $wpGroup) {
                if (!empty($wpGroup['work_plan_id'])) {
                    $wpIds[] = (int) $wpGroup['work_plan_id'];
                }
                foreach ($wpGroup['activities'] as $actData) {
                    if (!empty($actData['id'])) {
                        $rkapWpIds[] = (int) $actData['id'];
                    }
                    if (!empty($actData['activity_id'])) {
                        $activityIds[] = (int) $actData['activity_id'];
                    }
                    foreach ($actData['budget_items'] as $biData) {
                        if (!empty($biData['coa_id'])) {
                            $coaIds[] = (int) $biData['coa_id'];
                        }
                    }
                }
            }

            $coasMap = Coa::whereIn('id', array_unique($coaIds))->get()->keyBy('id');
            $activitiesMap = Activity::whereIn('id', array_unique($activityIds))->get()->keyBy('id');
            $workPlansMap = WorkPlan::whereIn('id', array_unique($wpIds))->get()->keyBy('id');
            $rkapWorkPlansMap = RkapWorkPlan::whereIn('id', array_unique($rkapWpIds))->get()->keyBy('id');

            // Collect all activity IDs (RkapWorkPlan PKs) present in the form state
            $existingWpIds = [];
            foreach ($this->workPlans as $wpGroup) {
                foreach ($wpGroup['activities'] as $actData) {
                    if (!empty($actData['id'])) {
                        $existingWpIds[] = $actData['id'];
                    }
                }
            }
            $submission->workPlans()->whereNotIn('id', $existingWpIds)->where('approval_status', '!=', 'approved')->delete();

            $sortIdx = 0;
            foreach ($this->workPlans as $wpGroup) {
                $workPlanId = $wpGroup['work_plan_id'];

                foreach ($wpGroup['activities'] as $actData) {
                    if (!empty($actData['id'])) {
                        $dbWp = $rkapWorkPlansMap->get($actData['id']);
                        if ($dbWp && $dbWp->approval_status === 'approved') {
                            $sortIdx++;
                            continue;
                        }
                    }

                    // Resolve program_name from the selected Activity (or WorkPlan as fallback)
                    $programName = null;
                    if (!empty($actData['activity_id'])) {
                        $programName = $activitiesMap->get($actData['activity_id'])?->title;
                    }
                    if (!$programName && !empty($workPlanId)) {
                        $programName = $workPlansMap->get($workPlanId)?->title;
                    }

                    $workPlan = RkapWorkPlan::updateOrCreate(
                        ['id' => $actData['id'] ?? null],
                        [
                            'rkap_submission_id' => $submission->id,
                            'work_plan_id' => $workPlanId ?: null,
                            'activity_id' => $actData['activity_id'] ?: null,
                            'program_name' => $programName,
                            'description' => $actData['description'] ?: null,
                            'output_target' => $actData['output_target'] ?: null,
                            'unit' => $actData['unit'] ?: null,
                            'quantity' => $actData['quantity'],
                            'sort_order' => $sortIdx++,
                        ]
                    );

                    $existingBiIds = collect($actData['budget_items'])->pluck('id')->filter()->toArray();
                    $workPlan->budgetItems()->whereNotIn('id', $existingBiIds)->delete();

                    foreach ($actData['budget_items'] as $biData) {
                        $coa = $coasMap->get($biData['coa_id']);
                        $budgetItem = RkapBudgetItem::updateOrCreate(
                            ['id' => $biData['id'] ?? null],
                            [
                                'rkap_work_plan_id' => $workPlan->id,
                                'account_code' => $coa ? $coa->code : null,
                                'description' => $coa ? $coa->title : '',
                                'unit' => $biData['unit'] ?: null,
                                'quantity' => $biData['quantity'],
                                'unit_2' => $biData['unit_2'] ?: null,
                                'quantity_2' => $biData['quantity_2'] !== null && $biData['quantity_2'] !== '' ? (int) $biData['quantity_2'] : null,
                                'unit_price' => $biData['unit_price'],
                                'remarks' => $biData['remarks'] ?: null,
                            ]
                        );

                        // Save monthly distribution
                        $budgetItem->monthlies()->delete();
                        $distribution = $biData['monthly_distribution'] ?? [];
                        foreach ($distribution as $month => $amount) {
                            if ((float) $amount > 0) {
                                $budgetItem->monthlies()->create([
                                    'month'  => (int) $month,
                                    'amount' => (float) $amount,
                                ]);
                            }
                        }

                        // Save cash out plan
                        $budgetItem->cashOuts()->delete();
                        $cashOutDistribution = $biData['cash_out_distribution'] ?? [];
                        foreach ($cashOutDistribution as $month => $amount) {
                            if ((float) $amount > 0) {
                                $budgetItem->cashOuts()->create([
                                    'month'  => (int) $month,
                                    'amount' => (float) $amount,
                                ]);
                            }
                        }

                        // Save realization — scoped to this period
                        $budgetItem->realizations()
                            ->where('rkap_period_id', $this->periodId)
                            ->delete();
                        $realizationDistribution = $biData['realization_distribution'] ?? [];
                        foreach ($realizationDistribution as $month => $amount) {
                            if ((float) $amount >= 0 && isset($month)) {
                                $budgetItem->realizations()->updateOrCreate(
                                    [
                                        'month'          => (int) $month,
                                        'rkap_period_id' => $this->periodId,
                                    ],
                                    [
                                        'amount'         => (float) $amount,
                                        'rkap_period_id' => $this->periodId,
                                        'uploaded_by'    => auth()->id(),
                                        'uploaded_at'    => now(),
                                    ]
                                );
                            }
                        }
                    }
                }
            }

            $submission->calculateTotalBudget();
            $this->submissionId = $submission->id;
            return $submission->fresh();
        });
    }

    public function buildPreviousMap(): array
    {
        $user = Auth::user();
        $bureauId = $user->bureau_id;
        $currentPeriodYear = $this->period?->year ?? 0;

        if (!$bureauId || !$currentPeriodYear) {
            return [];
        }

        $service = app(\App\Services\RkapPreviousDataService::class);
        $prevSubmission = $service->getPreviousApprovedSubmission($bureauId, $currentPeriodYear);
        return $service->buildPreviousMap($prevSubmission);
    }

    public function render()
    {
        return view('livewire.rkap.rkap-submission-form', [
            'workPlanOptions' => $this->workPlanOptions,
            'coaOptions' => $this->coaOptions,
            'monthLabels' => self::MONTH_LABELS,
            'prevData' => $this->buildPreviousMap(),
        ])->layout('layouts.contentNavbarLayout');
    }
}
