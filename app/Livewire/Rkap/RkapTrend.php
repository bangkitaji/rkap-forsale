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
use App\Exports\RkapTrendSummaryExport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class RkapTrend extends Component
{
  public string $activeTab = 'trend'; // 'trend' | 'summary'

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
  public array $filterSummaryStatus = []; // Multi-select: ['Selesai', 'Sedang Diisi', 'Belum Diisi']

  public array $expandedRows = [];
  public array $justificationForm = [];

  protected $queryString = [
    'activeTab' => ['except' => 'trend'],
    'search' => ['except' => ''],
    'filterStatus' => ['except' => 'all'],
    'filterSummaryStatus' => ['except' => []],
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
    $allIds = collect($this->trendData['items'])
      ->pluck('work_plan_id')
      ->toArray();
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
        'justification_deviation_projection' => trim((string) ($form['projection'] ?? '')),
        'justification_deviation_proposal' => trim((string) ($form['proposal'] ?? '')),
        'updated_by' => Auth::id(),
      ]
    );

    $this->dispatch('trend-justification-saved', ['workPlanId' => $workPlanId]);
    session()->flash('success_' . $workPlanId, __('Justifikasi berhasil disimpan.'));
  }

  /**
   * Export current trend view data to Excel.
   */
  /**
   * Drilldown from summary row to trend detail tab for a specific bureau.
   */
  public function viewBureauDetail(int $bureauId): void
  {
    $bureau = Bureau::with('department')->find($bureauId);
    if ($bureau) {
      $this->directorateId = $bureau->department?->directorate_id ?? $this->directorateId;
      $this->departmentId = $bureau->department_id ?? $this->departmentId;
      $this->bureauId = $bureau->id;
    }
    $this->activeTab = 'trend';
    $this->search = '';
    $this->filterStatus = 'all';
    $this->expandedRows = [];
  }

  /**
   * Export current view data (Trend Details or Summary Progress) to Excel.
   */
  public function exportExcel()
  {
    $user = Auth::user();
    if (!$user || !$user->can('rkap.show')) {
      abort(403, __('Anda tidak memiliki akses untuk mengekspor data ini.'));
    }

    if ($this->activeTab === 'summary') {
      $data = $this->summaryData;
      $filename = 'Ringkasan_Progres_Justifikasi_RKAP_' . now()->format('YmdHis') . '.xlsx';

      return Excel::download(
        new RkapTrendSummaryExport(
          $data,
          $this->currentPeriodTitle ?? 'RKAP Berjalan',
          $this->proposalPeriodTitle ?? 'RKAP Usulan',
          Auth::user()->organization_name
        ),
        $filename
      );
    }

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
   * Get raw unfiltered trend items for both periods (Pass 1 and Pass 2 union).
   */
  public function getRawTrendItems(): array
  {
    if (!$this->proposalPeriodId || !$this->currentPeriodId) {
      return [];
    }

    $targetBureauIds = $this->getTargetBureauIds();
    if (empty($targetBureauIds)) {
      return [];
    }

    $validStatuses = [
      'draft',
      'submitted',
      'dept_approved',
      'dept_revision',
      'dir_review',
      'dir_approved',
      'dir_revision',
      'final_review',
      'final_revision',
      'verifikator_approved',
      'pdir_review',
      'pdir_revision',
      'approved',
    ];

    // 1. Fetch RKAP Usulan beserta relasi lengkap
    $proposedSubmissions = RkapSubmission::where('rkap_period_id', $this->proposalPeriodId)
      ->whereIn('bureau_id', $targetBureauIds)
      ->whereIn('status', $validStatuses)
      ->with([
        'bureau.department.directorate',
        'workPlans.activity',
        'workPlans.workPlan',
        'workPlans.budgetItems',
        'workPlans.trendJustifications' => fn($q) => $q
          ->where('current_period_id', $this->currentPeriodId)
          ->where('proposal_period_id', $this->proposalPeriodId)
          ->with('updater'),
      ])
      ->get();

    // 2. Fetch RKAP Berjalan beserta proyeksi & justifikasi trend
    $currentSubmissions = RkapSubmission::where('rkap_period_id', $this->currentPeriodId)
      ->whereIn('bureau_id', $targetBureauIds)
      ->whereIn('status', $validStatuses)
      ->with([
        'bureau.department.directorate',
        'workPlans.activity',
        'workPlans.workPlan',
        'workPlans.budgetItems.projections',
        'workPlans.budgetItems.monthlies',
        'workPlans.trendJustifications' => fn($q) => $q
          ->where('current_period_id', $this->currentPeriodId)
          ->where('proposal_period_id', $this->proposalPeriodId)
          ->with('updater'),
      ])
      ->get();

    // 3. Bangun map info bureau dari kedua sumber
    $bureauInfoMap = [];
    foreach ($currentSubmissions as $sub) {
      $bureauInfoMap[$sub->bureau_id] ??= [
        'bureau_name' => $sub->bureau?->name ?? '-',
        'department_name' => $sub->bureau?->department?->name ?? '-',
        'directorate_name' => $sub->bureau?->department?->directorate?->name ?? '-',
      ];
    }
    foreach ($proposedSubmissions as $sub) {
      $bureauInfoMap[$sub->bureau_id] ??= [
        'bureau_name' => $sub->bureau?->name ?? '-',
        'department_name' => $sub->bureau?->department?->name ?? '-',
        'directorate_name' => $sub->bureau?->department?->directorate?->name ?? '-',
      ];
    }

    // 4. Bangun map work plan berjalan: key = bureauId_activityId atau bureauId_c_programCode
    $currentWorkPlansMap = [];
    foreach ($currentSubmissions as $cSub) {
      $bId = $cSub->bureau_id;
      foreach ($cSub->workPlans as $cWp) {
        $cProj = 0.0;
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
          'bureau_id' => $bId,
          'total_budget' => (float) $cWp->total_budget,
          'total_projection' => $cProj,
          'matched' => false,
        ];

        if ($cWp->activity_id) {
          $mapKey = $bId . '_a_' . $cWp->activity_id;
          $currentWorkPlansMap[$mapKey] = $wpData;
        } elseif ($cWp->program_code) {
          $mapKey = $bId . '_c_' . $cWp->program_code;
          $currentWorkPlansMap[$mapKey] = $wpData;
        }
        $currentWorkPlansMap['id_' . $cWp->id] = $wpData;
      }
    }

    $items = [];
    $matchedWpIds = [];

    // ── PASS 1: Dari sisi RKAP Usulan ─────────────────────────────────────
    foreach ($proposedSubmissions as $pSub) {
      $bId = $pSub->bureau_id;
      $bureauInfo = $bureauInfoMap[$bId] ?? ['bureau_name' => '-', 'department_name' => '-', 'directorate_name' => '-'];

      foreach ($pSub->workPlans as $pWp) {
        $currData = null;
        if ($pWp->activity_id) {
          $currData = $currentWorkPlansMap[$bId . '_a_' . $pWp->activity_id] ?? null;
        } elseif ($pWp->program_code) {
          $currData = $currentWorkPlansMap[$bId . '_c_' . $pWp->program_code] ?? null;
        }

        if ($currData) {
          $cWpId = $currData['wp']->id;
          $matchedWpIds[$cWpId] = true;
          if ($currData['wp']->activity_id) {
            $currentWorkPlansMap[$bId . '_a_' . $currData['wp']->activity_id]['matched'] = true;
          } elseif ($currData['wp']->program_code) {
            $currentWorkPlansMap[$bId . '_c_' . $currData['wp']->program_code]['matched'] = true;
          }
          $currentWorkPlansMap['id_' . $cWpId]['matched'] = true;
        }

        $rkapCurrent = $currData ? (float) $currData['total_budget'] : 0.0;
        $projCurrent = $currData ? (float) $currData['total_projection'] : 0.0;
        $devProj = $rkapCurrent - $projCurrent;
        $rkapProposed = (float) $pWp->total_budget;
        $devProp = $projCurrent > 0 ? ($rkapProposed - $projCurrent) : ($rkapProposed - $rkapCurrent);

        $itemType = $currData ? 'matched' : 'new';
        $justification = $pWp->trendJustifications->first();
        $isFilled = $justification ? $justification->isFilled() : false;
        $isFullyFilled = $justification ? $justification->isFullyFilled($itemType) : false;

        if (!isset($this->justificationForm[$pWp->id])) {
          $this->justificationForm[$pWp->id] = [
            'projection' => $justification?->justification_deviation_projection ?? '',
            'proposal' => $justification?->justification_deviation_proposal ?? '',
          ];
        }

        $items[] = [
          'work_plan_id'             => $pWp->id,
          'activity_id'              => $pWp->activity_id,
          'program_code'             => $pWp->workPlan ? $pWp->workPlan->code : ($pWp->program_code ?? '-'),
          'program_name'             => $pWp->workPlan ? $pWp->workPlan->title : ($pWp->program_name ?? '-'),
          'code'                     => $pWp->activity ? $pWp->activity->code : ($pWp->workPlan ? $pWp->workPlan->code : ($pWp->program_code ?? '-')),
          'name'                     => $pWp->activity ? $pWp->activity->title : ($pWp->workPlan ? $pWp->workPlan->title : ($pWp->program_name ?? '-')),
          'description'              => $pWp->description,
          'bureau_id'                => $bId,
          'bureau_name'              => $bureauInfo['bureau_name'],
          'department_name'          => $bureauInfo['department_name'],
          'directorate_name'         => $bureauInfo['directorate_name'],
          'rkap_current'             => $rkapCurrent,
          'projection_current'       => $projCurrent,
          'dev_projection'           => $devProj,
          'rkap_proposed'            => $rkapProposed,
          'dev_proposal'             => $devProp,
          'is_filled'                => $isFilled,
          'is_fully_filled'          => $isFullyFilled,
          'justification_projection' => $justification?->justification_deviation_projection ?? '',
          'justification_proposal'   => $justification?->justification_deviation_proposal ?? '',
          'updated_at'               => $justification?->updated_at?->format('d/m/Y H:i'),
          'updater_name'             => $justification?->updater?->name,
          'can_edit'                 => $this->canEditJustification($bId),
          'item_type'                => $itemType,
        ];
      }
    }

    // ── PASS 2: Work plan berjalan yang tidak ada di RKAP Usulan ──────────
    $seenCurrentIds = [];
    foreach ($currentWorkPlansMap as $mapKey => $cwpData) {
      if (!str_starts_with($mapKey, 'id_')) {
        continue;
      }
      if ($cwpData['matched']) {
        continue;
      }

      $cWp = $cwpData['wp'];
      $cWpId = $cWp->id;
      if (isset($seenCurrentIds[$cWpId])) {
        continue;
      }
      $seenCurrentIds[$cWpId] = true;

      $bId = $cwpData['bureau_id'];
      $bureauInfo = $bureauInfoMap[$bId] ?? ['bureau_name' => '-', 'department_name' => '-', 'directorate_name' => '-'];

      $rkapCurrent = (float) $cwpData['total_budget'];
      $projCurrent = (float) $cwpData['total_projection'];
      $devProj = $rkapCurrent - $projCurrent;
      $rkapProposed = 0.0;
      $devProp = -$projCurrent;

      $justification = $cWp->trendJustifications->first();
      $isFilled = $justification ? $justification->isFilled() : false;
      $isFullyFilled = $justification ? $justification->isFullyFilled() : false;

      if (!isset($this->justificationForm[$cWpId])) {
        $this->justificationForm[$cWpId] = [
          'projection' => $justification?->justification_deviation_projection ?? '',
          'proposal' => $justification?->justification_deviation_proposal ?? '',
        ];
      }

      $items[] = [
        'work_plan_id'             => $cWpId,
        'activity_id'              => $cWp->activity_id,
        'program_code'             => $cWp->workPlan ? $cWp->workPlan->code : ($cWp->program_code ?? '-'),
        'program_name'             => $cWp->workPlan ? $cWp->workPlan->title : ($cWp->program_name ?? '-'),
        'code'                     => $cWp->activity ? $cWp->activity->code : ($cWp->workPlan ? $cWp->workPlan->code : ($cWp->program_code ?? '-')),
        'name'                     => $cWp->activity ? $cWp->activity->title : ($cWp->workPlan ? $cWp->workPlan->title : ($cWp->program_name ?? '-')),
        'description'              => $cWp->description,
        'bureau_id'                => $bId,
        'bureau_name'              => $bureauInfo['bureau_name'],
        'department_name'          => $bureauInfo['department_name'],
        'directorate_name'         => $bureauInfo['directorate_name'],
        'rkap_current'             => $rkapCurrent,
        'projection_current'       => $projCurrent,
        'dev_projection'           => $devProj,
        'rkap_proposed'            => $rkapProposed,
        'dev_proposal'             => $devProp,
        'is_filled'                => $isFilled,
        'is_fully_filled'          => $isFullyFilled,
        'justification_projection' => $justification?->justification_deviation_projection ?? '',
        'justification_proposal'   => $justification?->justification_deviation_proposal ?? '',
        'updated_at'               => $justification?->updated_at?->format('d/m/Y H:i'),
        'updater_name'             => $justification?->updater?->name,
        'can_edit'                 => $this->canEditJustification($bId),
        'item_type'                => 'discontinued',
      ];
    }

    return $items;
  }

  /**
   * Get computed trend data and summary metrics for Detail Trend tab.
   */
  public function getTrendDataProperty(): array
  {
    $items = $this->getRawTrendItems();
    if (empty($items)) {
      return $this->emptyTrendResult();
    }

    $totalActivities = count($items);
    $filledCount = 0;
    $totalRkapCurrent = 0.0;
    $totalProjCurrent = 0.0;
    $totalDevProj = 0.0;
    $totalRkapProposed = 0.0;
    $totalDevProp = 0.0;

    foreach ($items as $item) {
      if ($item['is_filled']) {
        $filledCount++;
      }
      $totalRkapCurrent += $item['rkap_current'];
      $totalProjCurrent += $item['projection_current'];
      $totalDevProj += $item['dev_projection'];
      $totalRkapProposed += $item['rkap_proposed'];
      $totalDevProp += $item['dev_proposal'];
    }

    $unfilledCount = $totalActivities - $filledCount;
    $percentage = $totalActivities > 0 ? round(($filledCount / $totalActivities) * 100) : 0;

    // Terapkan filter pencarian
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

    // Terapkan filter status justifikasi
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

  /**
   * Get progress report summary data per bureau for Ringkasan Trend tab.
   */
  public function getSummaryDataProperty(): array
  {
    if (!$this->proposalPeriodId || !$this->currentPeriodId) {
      return [
        'stats' => [
          'total_bureaus' => 0,
          'total_activities' => 0,
          'filled_activities' => 0,
          'unfilled_activities' => 0,
          'percentage' => 0,
        ],
        'rows' => [],
      ];
    }

    $targetBureauIds = $this->getTargetBureauIds();
    if (empty($targetBureauIds)) {
      return [
        'stats' => [
          'total_bureaus' => 0,
          'total_activities' => 0,
          'filled_activities' => 0,
          'unfilled_activities' => 0,
          'percentage' => 0,
        ],
        'rows' => [],
      ];
    }

    $rawTrendItems = $this->getRawTrendItems();
    $itemsByBureau = collect($rawTrendItems)->groupBy('bureau_id');

    $bureaus = Bureau::with('department.directorate')
      ->whereIn('id', $targetBureauIds)
      ->orderBy('code')
      ->get();

    $rows = [];
    foreach ($bureaus as $bur) {
      $bureauItems = $itemsByBureau->get($bur->id, collect());
      $bureauTotalActivities = $bureauItems->count();
      $bureauFilledActivities = $bureauItems->filter(fn($item) => $item['is_filled'])->count();
      $bureauUnfilledActivities = $bureauTotalActivities - $bureauFilledActivities;
      $percent = $bureauTotalActivities > 0
        ? round(($bureauFilledActivities / $bureauTotalActivities) * 100, 1)
        : 0.0;

      if ($bureauTotalActivities === 0) {
        $status = 'Belum Ada Kegiatan';
        $statusClass = 'bg-label-secondary';
      } elseif ($percent === 100.0) {
        $status = 'Selesai';
        $statusClass = 'bg-label-success';
      } elseif ($percent > 0.0) {
        $status = 'Sedang Diisi';
        $statusClass = 'bg-label-warning';
      } else {
        $status = 'Belum Diisi';
        $statusClass = 'bg-label-secondary';
      }

      $rows[] = [
        'bureau_id' => $bur->id,
        'directorate' => $bur->department?->directorate?->name ?? '-',
        'department' => $bur->department?->name ?? '-',
        'bureau_code' => $bur->code,
        'bureau_name' => $bur->name,
        'total_activities' => $bureauTotalActivities,
        'filled_activities' => $bureauFilledActivities,
        'unfilled_activities' => $bureauUnfilledActivities,
        'percentage' => $percent,
        'status' => $status,
        'status_class' => $statusClass,
      ];
    }

    // Apply multi-select status filter if active
    $collection = collect($rows);
    $validStatuses = ['Selesai', 'Sedang Diisi', 'Belum Diisi', 'Belum Ada Kegiatan'];
    $statuses = is_array($this->filterSummaryStatus)
      ? $this->filterSummaryStatus
      : (is_string($this->filterSummaryStatus) && $this->filterSummaryStatus !== '' ? [$this->filterSummaryStatus] : []);
    $activeFilters = array_filter($statuses, fn($s) => in_array($s, $validStatuses));

    if (!empty($activeFilters)) {
      $collection = $collection->filter(fn($row) => in_array($row['status'], $activeFilters));
    }

    $sortedRows = $collection->sortBy('bureau_code')->values()->all();

    $totalBureaus = count($sortedRows);
    $grandTotalActivities = collect($sortedRows)->sum('total_activities');
    $grandFilledActivities = collect($sortedRows)->sum('filled_activities');
    $grandUnfilledActivities = collect($sortedRows)->sum('unfilled_activities');
    $grandPercent = $grandTotalActivities > 0
      ? round(($grandFilledActivities / $grandTotalActivities) * 100, 1)
      : 0.0;

    return [
      'stats' => [
        'total_bureaus' => $totalBureaus,
        'total_activities' => $grandTotalActivities,
        'filled_activities' => $grandFilledActivities,
        'unfilled_activities' => $grandUnfilledActivities,
        'percentage' => $grandPercent,
      ],
      'rows' => $sortedRows,
    ];
  }

  /**
   * Return empty trend result structure.
   */
  private function emptyTrendResult(): array
  {
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

  public function render()
  {
    return view('livewire.rkap.rkap-trend', [
      'trendData' => $this->trendData,
      'summaryData' => $this->summaryData,
      'directorateOptions' => $this->directorateOptions,
      'departmentOptions' => $this->departmentOptions,
      'bureauOptions' => $this->bureauOptions,
    ])->layout('layouts.contentNavbarLayout');
  }
}
