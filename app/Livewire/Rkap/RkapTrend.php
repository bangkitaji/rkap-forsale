<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\RkapTrendJustification;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Exports\RkapTrendExport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class RkapTrend extends Component
{
    public ?int $currentPeriodId = null;
    public ?int $proposalPeriodId = null;
    public ?string $currentPeriodTitle = null;
    public ?string $proposalPeriodTitle = null;
    public ?int $currentPeriodYear = null;
    public ?int $proposalPeriodYear = null;

    public ?int $directorateId = null;
    public ?int $departmentId = null;
    public ?int $bureauId = null;

    public string $search = '';
    public string $filterStatus = 'all'; // 'all', 'filled', 'unfilled'

    public array $expandedRows = [];
    public array $justificationForm = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        $user = Auth::user();

        if (!$user || !$user->can('rkap.show')) {
            abort(403, __('Anda tidak memiliki akses untuk halaman ini.'));
        }

        // 1. Auto-detect Current Period (RKAP Berjalan - e.g. 2026 Finalized)
        $currentYear = (int) date('Y');
        $currentPeriod = RkapPeriod::where('year', $currentYear)->where('status', 'finalized')->first()
            ?? RkapPeriod::where('status', 'finalized')->orderByDesc('year')->first()
            ?? RkapPeriod::where('status', 'closed')->orderByDesc('year')->first();

        if ($currentPeriod) {
            $this->currentPeriodId = $currentPeriod->id;
            $this->currentPeriodTitle = $currentPeriod->title;
            $this->currentPeriodYear = (int) $currentPeriod->year;
        }

        // 2. Auto-detect Proposal Period (RKAP Usulan - e.g. 2027 Open)
        $proposalPeriod = RkapPeriod::where('status', 'open')
            ->when($currentPeriod, fn($q) => $q->where('year', '>', $currentPeriod->year))
            ->first()
            ?? RkapPeriod::where('status', 'open')->first()
            ?? RkapPeriod::where('id', '!=', $this->currentPeriodId)->orderByDesc('year')->first();

        if ($proposalPeriod) {
            $this->proposalPeriodId = $proposalPeriod->id;
            $this->proposalPeriodTitle = $proposalPeriod->title;
            $this->proposalPeriodYear = (int) $proposalPeriod->year;
        }

        // 3. Organization Filter Scoping based on Role
        if ($user->isKepalaBiro()) {
            $this->bureauId = $user->bureau_id;
            $this->departmentId = $user->department_id;
            $this->directorateId = $user->directorate_id;
        } elseif ($user->isKepalaDepartemen()) {
            $this->departmentId = $user->department_id;
            $this->directorateId = $user->directorate_id;
        } elseif ($user->isDireksi()) {
            $this->directorateId = $user->directorate_id;
        }
    }

    public function updatedDirectorateId(): void
    {
        $this->departmentId = null;
        $this->bureauId = null;
        $this->expandedRows = [];
    }

    public function updatedDepartmentId(): void
    {
        $this->bureauId = null;
        $this->expandedRows = [];
    }

    public function updatedBureauId(): void
    {
        $this->expandedRows = [];
    }

    public function getDirectorateOptionsProperty()
    {
        $user = Auth::user();
        if ($user->isKepalaBiro() || $user->isKepalaDepartemen() || $user->isDireksi()) {
            return Directorate::where('id', $user->directorate_id)->get();
        }
        return Directorate::where('is_active', true)->orderBy('name')->get();
    }

    public function getDepartmentOptionsProperty()
    {
        $user = Auth::user();
        if ($user->isKepalaBiro() || $user->isKepalaDepartemen()) {
            return Department::where('id', $user->department_id)->get();
        }

        $query = Department::where('is_active', true);

        if ($this->directorateId) {
            $query->where('directorate_id', $this->directorateId);
        } elseif ($user->isDireksi()) {
            $query->where('directorate_id', $user->directorate_id);
        }

        return $query->orderBy('name')->get();
    }

    public function getBureauOptionsProperty()
    {
        $user = Auth::user();
        if ($user->isKepalaBiro()) {
            return Bureau::where('id', $user->bureau_id)->get();
        }

        $query = Bureau::where('is_active', true);

        if ($this->departmentId) {
            $query->where('department_id', $this->departmentId);
        } elseif ($this->directorateId) {
            $query->whereHas('department', fn($q) => $q->where('directorate_id', $this->directorateId));
        } else {
            if ($user->isKepalaDepartemen()) {
                $query->where('department_id', $user->department_id);
            } elseif ($user->isDireksi()) {
                $query->whereHas('department', fn($q) => $q->where('directorate_id', $user->directorate_id));
            }
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Check if the authenticated user can edit justifications for a given bureau.
     */
    public function canEditJustification(?int $bureauId): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Administrator can edit
        if ($user->isAdmin()) {
            return true;
        }

        // Kepala Biro / user can edit for their own bureau
        if ($user->isKepalaBiro() && $user->bureau_id && $user->bureau_id === $bureauId) {
            return true;
        }

        return false;
    }

    /**
     * Get list of target bureau IDs to query based on active filters and user role.
     */
    protected function getTargetBureauIds(): array
    {
        $user = Auth::user();

        if ($this->bureauId) {
            return [$this->bureauId];
        }

        if ($this->departmentId) {
            return Bureau::where('department_id', $this->departmentId)->pluck('id')->toArray();
        }

        if ($this->directorateId) {
            return Bureau::whereHas('department', fn($q) => $q->where('directorate_id', $this->directorateId))->pluck('id')->toArray();
        }

        if ($user->isKepalaBiro()) {
            return $user->bureau_id ? [$user->bureau_id] : [];
        }

        if ($user->isKepalaDepartemen()) {
            return Bureau::where('department_id', $user->department_id)->pluck('id')->toArray();
        }

        if ($user->isDireksi()) {
            return Bureau::whereHas('department', fn($q) => $q->where('directorate_id', $user->directorate_id))->pluck('id')->toArray();
        }

        // Administrator & Verificator: all bureaus
        return Bureau::pluck('id')->toArray();
    }

    /**
     * Toggle accordion expansion for a specific work plan.
     */
    public function toggleRow(int $workPlanId): void
    {
        if (in_array($workPlanId, $this->expandedRows)) {
            $this->expandedRows = array_diff($this->expandedRows, [$workPlanId]);
        } else {
            $this->expandedRows[] = $workPlanId;

            // Preload existing justification if not already set in form state
            if (!isset($this->justificationForm[$workPlanId])) {
                $justification = RkapTrendJustification::where('rkap_work_plan_id', $workPlanId)
                    ->where('current_period_id', $this->currentPeriodId)
                    ->where('proposal_period_id', $this->proposalPeriodId)
                    ->first();

                $this->justificationForm[$workPlanId] = [
                    'projection' => $justification?->justification_deviation_projection ?? '',
                    'proposal' => $justification?->justification_deviation_proposal ?? '',
                ];
            }
        }
    }

    /**
     * Expand all rows.
     */
    public function expandAll(): void
    {
        $allIds = collect($this->trendData['items'])->pluck('work_plan_id')->toArray();
        $this->expandedRows = $allIds;

        foreach ($allIds as $wpId) {
            if (!isset($this->justificationForm[$wpId])) {
                $justification = RkapTrendJustification::where('rkap_work_plan_id', $wpId)
                    ->where('current_period_id', $this->currentPeriodId)
                    ->where('proposal_period_id', $this->proposalPeriodId)
                    ->first();

                $this->justificationForm[$wpId] = [
                    'projection' => $justification?->justification_deviation_projection ?? '',
                    'proposal' => $justification?->justification_deviation_proposal ?? '',
                ];
            }
        }
    }

    /**
     * Collapse all rows.
     */
    public function collapseAll(): void
    {
        $this->expandedRows = [];
    }

    /**
     * Save justification for a specific work plan.
     */
    public function saveJustification(int $workPlanId): void
    {
        $wp = RkapWorkPlan::with('submission')->find($workPlanId);
        if (!$wp) {
            session()->flash('error', __('Kegiatan tidak ditemukan.'));
            return;
        }

        if (!$this->canEditJustification($wp->submission?->bureau_id)) {
            session()->flash('error', __('Anda tidak memiliki hak akses untuk mengubah justifikasi biro ini.'));
            return;
        }

        $form = $this->justificationForm[$workPlanId] ?? ['projection' => '', 'proposal' => ''];

        RkapTrendJustification::updateOrCreate(
            [
                'rkap_work_plan_id' => $workPlanId,
                'current_period_id' => $this->currentPeriodId,
                'proposal_period_id' => $this->proposalPeriodId,
            ],
            [
                'justification_deviation_projection' => trim((string)($form['projection'] ?? '')),
                'justification_deviation_proposal' => trim((string)($form['proposal'] ?? '')),
                'updated_by' => Auth::id(),
            ]
        );

        $this->dispatch('trend-justification-saved', ['workPlanId' => $workPlanId]);
        session()->flash('success_' . $workPlanId, __('Justifikasi berhasil disimpan.'));
    }

    /**
     * Export current trend view data to Excel.
     */
    public function exportExcel()
    {
        $data = $this->trendData;
        $filename = 'Trend_Justifikasi_RKAP_' . now()->format('YmdHis') . '.xlsx';

        return Excel::download(
            new RkapTrendExport(
                $data['items'],
                $data['summary'],
                $this->currentPeriodTitle ?? 'RKAP Berjalan',
                $this->proposalPeriodTitle ?? 'RKAP Usulan',
                Auth::user()->organization_name
            ),
            $filename
        );
    }

    /**
     * Get computed trend data and summary metrics.
     */
    public function getTrendDataProperty(): array
    {
        if (!$this->proposalPeriodId || !$this->currentPeriodId) {
            return [
                'items' => [],
                'summary' => [
                    'total_activities' => 0,
                    'filled_count' => 0,
                    'unfilled_count' => 0,
                    'percentage' => 0,
                    'total_rkap_current' => 0,
                    'total_proj_current' => 0,
                    'total_dev_proj' => 0,
                    'total_rkap_proposed' => 0,
                    'total_dev_prop' => 0,
                ],
            ];
        }

        $targetBureauIds = $this->getTargetBureauIds();
        if (empty($targetBureauIds)) {
            return [
                'items' => [],
                'summary' => [
                    'total_activities' => 0,
                    'filled_count' => 0,
                    'unfilled_count' => 0,
                    'percentage' => 0,
                    'total_rkap_current' => 0,
                    'total_proj_current' => 0,
                    'total_dev_proj' => 0,
                    'total_rkap_proposed' => 0,
                    'total_dev_prop' => 0,
                ],
            ];
        }

        // 1. Fetch Proposed Submissions (e.g. 2027)
        $proposedSubmissions = RkapSubmission::where('rkap_period_id', $this->proposalPeriodId)
            ->whereIn('bureau_id', $targetBureauIds)
            ->with([
                'bureau.department.directorate',
                'workPlans.budgetItems',
                'workPlans.trendJustifications' => fn($q) => $q->where('current_period_id', $this->currentPeriodId)
                    ->where('proposal_period_id', $this->proposalPeriodId)
                    ->with('updater'),
            ])
            ->get();

        // 2. Fetch Current Submissions (e.g. 2026)
        $currentSubmissions = RkapSubmission::where('rkap_period_id', $this->currentPeriodId)
            ->whereIn('bureau_id', $targetBureauIds)
            ->with([
                'workPlans.budgetItems.projections',
                'workPlans.budgetItems.monthlies',
            ])
            ->get();

        // Index current work plans by bureau_id + activity_id / program_code
        $currentWorkPlansMap = [];
        foreach ($currentSubmissions as $cSub) {
            $bId = $cSub->bureau_id;
            if (!isset($currentWorkPlansMap[$bId])) {
                $currentWorkPlansMap[$bId] = [
                    'by_activity' => [],
                    'by_code' => [],
                ];
            }

            foreach ($cSub->workPlans as $cWp) {
                $cProj = 0;
                foreach ($cWp->budgetItems as $bi) {
                    if ($bi->projections->isNotEmpty()) {
                        $cProj += (float) $bi->projections->sum('amount');
                    } elseif ((float) $bi->projection > 0) {
                        $cProj += (float) $bi->projection;
                    } else {
                        $cProj += (float) $bi->total_price;
                    }
                }

                $wpData = [
                    'wp' => $cWp,
                    'total_budget' => (float) $cWp->total_budget,
                    'total_projection' => (float) $cProj,
                ];

                if ($cWp->activity_id) {
                    $currentWorkPlansMap[$bId]['by_activity'][$cWp->activity_id] = $wpData;
                }
                if ($cWp->program_code) {
                    $currentWorkPlansMap[$bId]['by_code'][$cWp->program_code] = $wpData;
                }
            }
        }

        $items = [];
        $totalRkapCurrent = 0;
        $totalProjCurrent = 0;
        $totalDevProj = 0;
        $totalRkapProposed = 0;
        $totalDevProp = 0;
        $filledCount = 0;

        foreach ($proposedSubmissions as $pSub) {
            $bId = $pSub->bureau_id;
            $bureauName = $pSub->bureau?->name ?? '-';
            $departmentName = $pSub->bureau?->department?->name ?? '-';
            $directorateName = $pSub->bureau?->department?->directorate?->name ?? '-';

            foreach ($pSub->workPlans as $pWp) {
                // Check if matches current work plan
                $currData = null;
                if ($pWp->activity_id && isset($currentWorkPlansMap[$bId]['by_activity'][$pWp->activity_id])) {
                    $currData = $currentWorkPlansMap[$bId]['by_activity'][$pWp->activity_id];
                } elseif ($pWp->program_code && isset($currentWorkPlansMap[$bId]['by_code'][$pWp->program_code])) {
                    $currData = $currentWorkPlansMap[$bId]['by_code'][$pWp->program_code];
                }

                $rkapCurrent = $currData ? (float) $currData['total_budget'] : 0.0;
                $projCurrent = $currData ? (float) $currData['total_projection'] : 0.0;
                $devProj = $rkapCurrent - $projCurrent;
                $rkapProposed = (float) $pWp->total_budget;
                // Deviation proposed vs current RKAP / projection
                $devProp = $projCurrent > 0 ? ($rkapProposed - $projCurrent) : ($rkapProposed - $rkapCurrent);

                $justification = $pWp->trendJustifications->first();
                $isFilled = $justification ? $justification->isFilled() : false;
                $isFullyFilled = $justification ? $justification->isFullyFilled() : false;

                if ($isFilled) {
                    $filledCount++;
                }

                $totalRkapCurrent += $rkapCurrent;
                $totalProjCurrent += $projCurrent;
                $totalDevProj += $devProj;
                $totalRkapProposed += $rkapProposed;
                $totalDevProp += $devProp;

                // Prepopulate form if needed
                if (!isset($this->justificationForm[$pWp->id])) {
                    $this->justificationForm[$pWp->id] = [
                        'projection' => $justification?->justification_deviation_projection ?? '',
                        'proposal' => $justification?->justification_deviation_proposal ?? '',
                    ];
                }

                $items[] = [
                    'work_plan_id' => $pWp->id,
                    'activity_id' => $pWp->activity_id,
                    'code' => $pWp->program_code ?? '-',
                    'name' => $pWp->program_name ?? '-',
                    'description' => $pWp->description,
                    'bureau_id' => $bId,
                    'bureau_name' => $bureauName,
                    'department_name' => $departmentName,
                    'directorate_name' => $directorateName,
                    'rkap_current' => $rkapCurrent,
                    'projection_current' => $projCurrent,
                    'dev_projection' => $devProj,
                    'rkap_proposed' => $rkapProposed,
                    'dev_proposal' => $devProp,
                    'is_filled' => $isFilled,
                    'is_fully_filled' => $isFullyFilled,
                    'justification_projection' => $justification?->justification_deviation_projection ?? '',
                    'justification_proposal' => $justification?->justification_deviation_proposal ?? '',
                    'updated_at' => $justification?->updated_at?->format('d/m/Y H:i'),
                    'updater_name' => $justification?->updater?->name,
                    'can_edit' => $this->canEditJustification($bId),
                ];
            }
        }

        $totalActivities = count($items);
        $unfilledCount = $totalActivities - $filledCount;
        $percentage = $totalActivities > 0 ? round(($filledCount / $totalActivities) * 100) : 0;

        // Apply search filter
        if (!empty($this->search)) {
            $s = strtolower($this->search);
            $items = array_values(array_filter($items, function ($item) use ($s) {
                return str_contains(strtolower($item['code']), $s)
                    || str_contains(strtolower($item['name']), $s)
                    || str_contains(strtolower($item['bureau_name']), $s)
                    || str_contains(strtolower($item['justification_projection']), $s)
                    || str_contains(strtolower($item['justification_proposal']), $s);
            }));
        }

        // Apply status filter
        if ($this->filterStatus === 'filled') {
            $items = array_values(array_filter($items, fn($item) => $item['is_filled']));
        } elseif ($this->filterStatus === 'unfilled') {
            $items = array_values(array_filter($items, fn($item) => !$item['is_filled']));
        }

        return [
            'items' => $items,
            'summary' => [
                'total_activities' => $totalActivities,
                'filled_count' => $filledCount,
                'unfilled_count' => $unfilledCount,
                'percentage' => $percentage,
                'total_rkap_current' => $totalRkapCurrent,
                'total_proj_current' => $totalProjCurrent,
                'total_dev_proj' => $totalDevProj,
                'total_rkap_proposed' => $totalRkapProposed,
                'total_dev_prop' => $totalDevProp,
            ],
        ];
    }

    public function render()
    {
        return view('livewire.rkap.rkap-trend', [
            'trendData' => $this->trendData,
            'directorateOptions' => $this->directorateOptions,
            'departmentOptions' => $this->departmentOptions,
            'bureauOptions' => $this->bureauOptions,
        ])->layout('layouts.contentNavbarLayout');
    }
}
