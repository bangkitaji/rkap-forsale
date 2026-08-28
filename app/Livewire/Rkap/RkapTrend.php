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
   * ID negatif = work plan berjalan tanpa pasangan usulan (tidak dapat diedit).
   */
  public function toggleRow(int $workPlanId): void
  {
    if (in_array($workPlanId, $this->expandedRows)) {
      $this->expandedRows = array_diff($this->expandedRows, [$workPlanId]);
    } else {
      $this->expandedRows[] = $workPlanId;

      // Hanya load justifikasi untuk work plan usulan (ID positif)
      if ($workPlanId > 0 && !isset($this->justificationForm[$workPlanId])) {
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
   * Hanya expand work plan usulan (ID positif) yang dapat dijustifikasi.
   */
  public function expandAll(): void
  {
    $allIds = collect($this->trendData['items'])
      ->pluck('work_plan_id')
      ->filter(fn($id) => $id > 0) // hanya ID positif (work plan usulan)
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
   * Menggunakan strategi union: menampilkan semua work plan dari KEDUA periode
   * (berjalan & usulan) sehingga tidak ada data yang tertinggal.
   */
  public function getTrendDataProperty(): array
  {
    if (!$this->proposalPeriodId || !$this->currentPeriodId) {
      return $this->emptyTrendResult();
    }

    $targetBureauIds = $this->getTargetBureauIds();
    if (empty($targetBureauIds)) {
      return $this->emptyTrendResult();
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

    // 2. Fetch RKAP Berjalan beserta proyeksi
    $currentSubmissions = RkapSubmission::where('rkap_period_id', $this->currentPeriodId)
      ->whereIn('bureau_id', $targetBureauIds)
      ->whereIn('status', $validStatuses)
      ->with([
        'bureau.department.directorate',
        'workPlans.activity',
        'workPlans.workPlan',
        'workPlans.budgetItems.projections',
        'workPlans.budgetItems.monthlies',
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
    //    Kunci ganda (activity_id DAN program_code) agar pencocokan lebih fleksibel
    $currentWorkPlansMap = []; // key => wpData
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
        }
        if ($cWp->program_code) {
          $mapKey = $bId . '_c_' . $cWp->program_code;
          // Jangan timpa jika sudah ada dari activity_id
          $currentWorkPlansMap[$mapKey] ??= $wpData;
        }
        // Selalu simpan juga per ID agar pass-2 tidak kehilangan data
        $currentWorkPlansMap['id_' . $cWp->id] = $wpData;
      }
    }

    $items = [];
    $totalRkapCurrent = 0.0;
    $totalProjCurrent = 0.0;
    $totalDevProj = 0.0;
    $totalRkapProposed = 0.0;
    $totalDevProp = 0.0;
    $filledCount = 0;
    $matchedWpIds = []; // mencatat work plan berjalan yang sudah dipakai

    // ── PASS 1: Dari sisi RKAP Usulan ─────────────────────────────────────
    foreach ($proposedSubmissions as $pSub) {
      $bId = $pSub->bureau_id;
      $bureauInfo = $bureauInfoMap[$bId] ?? ['bureau_name' => '-', 'department_name' => '-', 'directorate_name' => '-'];

      foreach ($pSub->workPlans as $pWp) {
        // Cari pasangan di RKAP berjalan (activity_id lebih prioritas)
        $currData = null;
        if ($pWp->activity_id) {
          $currData = $currentWorkPlansMap[$bId . '_a_' . $pWp->activity_id] ?? null;
        }
        if (!$currData && $pWp->program_code) {
          $currData = $currentWorkPlansMap[$bId . '_c_' . $pWp->program_code] ?? null;
        }

        // Tandai work plan berjalan yang sudah dicocokkan
        if ($currData) {
          $cWpId = $currData['wp']->id;
          $matchedWpIds[$cWpId] = true;
          // Tandai di semua map key agar tidak muncul dobel di pass 2
          if ($currData['wp']->activity_id) {
            $currentWorkPlansMap[$bId . '_a_' . $currData['wp']->activity_id]['matched'] = true;
          }
          if ($currData['wp']->program_code) {
            $currentWorkPlansMap[$bId . '_c_' . $currData['wp']->program_code]['matched'] = true;
          }
          $currentWorkPlansMap['id_' . $cWpId]['matched'] = true;
        }

        $rkapCurrent = $currData ? (float) $currData['total_budget'] : 0.0;
        $projCurrent = $currData ? (float) $currData['total_projection'] : 0.0;
        $devProj = $rkapCurrent - $projCurrent;
        $rkapProposed = (float) $pWp->total_budget;
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
          // 'matched'  = ada di kedua periode
          // 'new'      = kegiatan baru (hanya di usulan, tidak ada di berjalan)
          'item_type'                => $currData ? 'matched' : 'new',
        ];
      }
    }

    // ── PASS 2: Work plan berjalan yang tidak ada di RKAP Usulan ──────────
    // Ditampilkan dengan rkap_proposed = 0 sehingga deviation = -projCurrent
    $seenCurrentIds = []; // hindari duplikat jika satu WP terdaftar di dua key
    foreach ($currentWorkPlansMap as $mapKey => $cwpData) {
      // Hanya proses entry per-ID, bukan per activity/code (hindari dobel)
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
      $devProp = -$projCurrent; // tidak ada usulan = selisih negatif sebesar proyeksi

      $totalRkapCurrent += $rkapCurrent;
      $totalProjCurrent += $projCurrent;
      $totalDevProj += $devProj;
      $totalDevProp += $devProp;
      // $totalRkapProposed tidak bertambah (tidak ada usulan)

      // Gunakan ID negatif agar tidak bentrok dengan work plan usulan
      $pseudoId = -$cWpId;

      if (!isset($this->justificationForm[$pseudoId])) {
        $this->justificationForm[$pseudoId] = [
          'projection' => '',
          'proposal' => '',
        ];
      }

      $items[] = [
        'work_plan_id'             => $pseudoId,
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
        'is_filled'                => false,
        'is_fully_filled'          => false,
        'justification_projection' => '',
        'justification_proposal'   => '',
        'updated_at'               => null,
        'updater_name'             => null,
        'can_edit'                 => false,
        // 'discontinued' = kegiatan tidak diusulkan kembali (hanya di berjalan)
        'item_type'                => 'discontinued',
      ];
    }

    $totalActivities = count($items);
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
      'directorateOptions' => $this->directorateOptions,
      'departmentOptions' => $this->departmentOptions,
      'bureauOptions' => $this->bureauOptions,
    ])->layout('layouts.contentNavbarLayout');
  }
}
