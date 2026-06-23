<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Analytics extends Controller
{
  public function index(Request $request)
  {
    $user = Auth::user();
    if (!$user) {
      abort(403);
    }

    $currentYear = (int) date('Y');

    // Define scoped bureau IDs based on user role
    $bureauIds = null;
    if ($user->isKepalaBiro()) {
      $bureauIds = [$user->bureau_id];
    } elseif ($user->isKepalaDepartemen()) {
      $bureauIds = DB::table('bureaus')->where('department_id', $user->department_id)->pluck('id')->toArray();
    }

    // Get all finalized periods for dropdown selection
    $finalizedPeriods = RkapPeriod::where('status', 'finalized')
      ->orderBy('year', 'desc')
      ->orderBy('created_at', 'desc')
      ->get();

    // 1. Get the requested period if specified and finalized, otherwise default
    $selectedPeriodId = $request->query('period_id');
    $activePeriod = null;

    if ($selectedPeriodId) {
      $activePeriod = RkapPeriod::where('status', 'finalized')->find($selectedPeriodId);
    }

    if (!$activePeriod) {
      $activePeriod = RkapPeriod::where('year', $currentYear)->first()
        ?? RkapPeriod::where('status', 'finalized')->latest()->first()
        ?? RkapPeriod::latest()->first();
    }

    // High-level statistics
    $stats = [
      'total_budget' => 0.0,
      'total_realization' => 0.0,
      'total_projection' => 0.0,
      'absorption_rate' => 0.0,
      'outlook_rate' => 0.0,
    ];

    $plGroups = [];
    $plSummary = [];
    $unmappedGroup = null;

    $monthlyBudgetData = array_fill(1, 12, 0.0);
    $monthlyRealizationData = array_fill(1, 12, 0.0);
    $monthlyProjectionData = array_fill(1, 12, 0.0);

    $directorateData = [];
    $departmentData = [];
    $bureauData = [];
    $coaData = [];

    if ($activePeriod) {
      $periodId = $activePeriod->id;

      // Define scoped filters for high-level comparison charts (directorate and department level)
      // Both views should use the same directorate scope so totals are consistent
      $userDirectorateId = null;
      $deptFilterField = null;
      $deptFilterVal = null;
      if ($user->isKepalaBiro()) {
        $userDirectorateId = $user->directorate_id;
        $deptFilterField = 'departments.directorate_id';
        $deptFilterVal = $userDirectorateId;
      } elseif ($user->isKepalaDepartemen()) {
        $userDirectorateId = $user->directorate_id;
        $deptFilterField = 'departments.id';
        $deptFilterVal = $user->department_id;
      }

      // Total Budget (Approved Submissions only)
      $stats['total_budget'] = (float) DB::table('rkap_submissions')
        ->where('rkap_period_id', $periodId)
        ->where('status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('bureau_id', $bureauIds))
        ->sum('total_budget');

      // Total Realization YTD
      $stats['total_realization'] = (float) DB::table('rkap_budget_item_realizations')
        ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->sum('rkap_budget_item_realizations.amount');

      // Total Projection
      $stats['total_projection'] = (float) DB::table('rkap_budget_item_projections')
        ->join('rkap_budget_items', 'rkap_budget_item_projections.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->where('rkap_budget_item_projections.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->sum('rkap_budget_item_projections.amount');

      $stats['absorption_rate'] = $stats['total_budget'] > 0 
        ? round(($stats['total_realization'] / $stats['total_budget']) * 100, 1) 
        : 0.0;

      $stats['outlook_rate'] = $stats['total_budget'] > 0 
        ? round(($stats['total_projection'] / $stats['total_budget']) * 100, 1) 
        : 0.0;

      // --- 1. Monthly Budget Allocations ---
      $budgets = DB::table('rkap_budget_item_monthlies')
        ->join('rkap_budget_items', 'rkap_budget_item_monthlies.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->selectRaw('month, SUM(rkap_budget_item_monthlies.amount) as total')
        ->groupBy('month')
        ->pluck('total', 'month')
        ->toArray();

      foreach ($budgets as $m => $val) {
        $monthlyBudgetData[$m] = (float) $val;
      }

      // --- 2. Monthly Realization Data ---
      $realizations = DB::table('rkap_budget_item_realizations')
        ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->selectRaw('month, SUM(rkap_budget_item_realizations.amount) as total')
        ->groupBy('month')
        ->pluck('total', 'month')
        ->toArray();

      foreach ($realizations as $m => $val) {
        $monthlyRealizationData[$m] = (float) $val;
      }

      // --- 3. Monthly Projection Data ---
      $projections = DB::table('rkap_budget_item_projections')
        ->join('rkap_budget_items', 'rkap_budget_item_projections.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->where('rkap_budget_item_projections.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->selectRaw('month, SUM(rkap_budget_item_projections.amount) as total')
        ->groupBy('month')
        ->pluck('total', 'month')
        ->toArray();

      foreach ($projections as $m => $val) {
        $monthlyProjectionData[$m] = (float) $val;
      }

      // --- 4a. Directorate-level budgets, realizations, projections ---
      $directorateBudgets = DB::table('rkap_submissions')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->join('departments', 'bureaus.department_id', '=', 'departments.id')
        ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($userDirectorateId, fn($q) => $q->where('departments.directorate_id', $userDirectorateId))
        ->selectRaw('directorates.name as label, SUM(rkap_submissions.total_budget) as budget')
        ->groupBy('directorates.name')
        ->get()
        ->keyBy('label')
        ->toArray();

      $directorateRealizations = DB::table('rkap_budget_item_realizations')
        ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->join('departments', 'bureaus.department_id', '=', 'departments.id')
        ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
        ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($userDirectorateId, fn($q) => $q->where('departments.directorate_id', $userDirectorateId))
        ->selectRaw('directorates.name as label, SUM(rkap_budget_item_realizations.amount) as amount')
        ->groupBy('directorates.name')
        ->get()
        ->keyBy('label')
        ->toArray();

      $directorateProjections = DB::table('rkap_budget_item_projections')
        ->join('rkap_budget_items', 'rkap_budget_item_projections.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->join('departments', 'bureaus.department_id', '=', 'departments.id')
        ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
        ->where('rkap_budget_item_projections.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($userDirectorateId, fn($q) => $q->where('departments.directorate_id', $userDirectorateId))
        ->selectRaw('directorates.name as label, SUM(rkap_budget_item_projections.amount) as amount')
        ->groupBy('directorates.name')
        ->get()
        ->keyBy('label')
        ->toArray();

      $allDirLabels = array_unique(array_merge(
        array_keys($directorateBudgets),
        array_keys($directorateRealizations),
        array_keys($directorateProjections)
      ));

      $directorateData = [];
      foreach ($allDirLabels as $lbl) {
        $directorateData[] = [
          'label'       => $lbl,
          'budget'      => isset($directorateBudgets[$lbl]) ? (float) $directorateBudgets[$lbl]->budget : 0.0,
          'realization' => isset($directorateRealizations[$lbl]) ? (float) $directorateRealizations[$lbl]->amount : 0.0,
          'projection'  => isset($directorateProjections[$lbl]) ? (float) $directorateProjections[$lbl]->amount : 0.0,
        ];
      }

      // --- 4b. Department-level budgets, realizations, projections ---
      $departmentBudgets = DB::table('rkap_submissions')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->join('departments', 'bureaus.department_id', '=', 'departments.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($deptFilterField, fn($q) => $q->where($deptFilterField, $deptFilterVal))
        ->selectRaw('departments.name as label, SUM(rkap_submissions.total_budget) as budget')
        ->groupBy('departments.name')
        ->get()
        ->keyBy('label')
        ->toArray();

      $departmentRealizations = DB::table('rkap_budget_item_realizations')
        ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->join('departments', 'bureaus.department_id', '=', 'departments.id')
        ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($deptFilterField, fn($q) => $q->where($deptFilterField, $deptFilterVal))
        ->selectRaw('departments.name as label, SUM(rkap_budget_item_realizations.amount) as amount')
        ->groupBy('departments.name')
        ->get()
        ->keyBy('label')
        ->toArray();

      $departmentProjections = DB::table('rkap_budget_item_projections')
        ->join('rkap_budget_items', 'rkap_budget_item_projections.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->join('departments', 'bureaus.department_id', '=', 'departments.id')
        ->where('rkap_budget_item_projections.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($deptFilterField, fn($q) => $q->where($deptFilterField, $deptFilterVal))
        ->selectRaw('departments.name as label, SUM(rkap_budget_item_projections.amount) as amount')
        ->groupBy('departments.name')
        ->get()
        ->keyBy('label')
        ->toArray();

      $allDeptLabels = array_unique(array_merge(
        array_keys($departmentBudgets),
        array_keys($departmentRealizations),
        array_keys($departmentProjections)
      ));

      $departmentData = [];
      foreach ($allDeptLabels as $lbl) {
        $departmentData[] = [
          'label'       => $lbl,
          'budget'      => isset($departmentBudgets[$lbl]) ? (float) $departmentBudgets[$lbl]->budget : 0.0,
          'realization' => isset($departmentRealizations[$lbl]) ? (float) $departmentRealizations[$lbl]->amount : 0.0,
          'projection'  => isset($departmentProjections[$lbl]) ? (float) $departmentProjections[$lbl]->amount : 0.0,
        ];
      }

      // Sort by budget descending so largest departments appear first
      usort($departmentData, fn($a, $b) => $b['budget'] <=> $a['budget']);

      // --- 4c. Bureau-level budgets, realizations, projections ---
      if ($user->isKepalaDepartemen()) {
        $bureauBudgets = DB::table('rkap_submissions')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->where('rkap_submissions.rkap_period_id', $periodId)
          ->where('rkap_submissions.status', 'approved')
          ->when($bureauIds, fn($q) => $q->whereIn('bureau_id', $bureauIds))
          ->selectRaw('bureaus.name as label, SUM(rkap_submissions.total_budget) as budget')
          ->groupBy('bureaus.name')
          ->get()
          ->keyBy('label')
          ->toArray();

        $bureauRealizations = DB::table('rkap_budget_item_realizations')
          ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
          ->where('rkap_submissions.status', 'approved')
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->selectRaw('bureaus.name as label, SUM(rkap_budget_item_realizations.amount) as amount')
          ->groupBy('bureaus.name')
          ->get()
          ->keyBy('label')
          ->toArray();

        $bureauProjections = DB::table('rkap_budget_item_projections')
          ->join('rkap_budget_items', 'rkap_budget_item_projections.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->where('rkap_budget_item_projections.rkap_period_id', $periodId)
          ->where('rkap_submissions.status', 'approved')
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->selectRaw('bureaus.name as label, SUM(rkap_budget_item_projections.amount) as amount')
          ->groupBy('bureaus.name')
          ->get()
          ->keyBy('label')
          ->toArray();

        $allBureauLabels = array_unique(array_merge(
          array_keys($bureauBudgets),
          array_keys($bureauRealizations),
          array_keys($bureauProjections)
        ));

        foreach ($allBureauLabels as $lbl) {
          $bureauData[] = [
            'label'       => $lbl,
            'budget'      => isset($bureauBudgets[$lbl]) ? (float) $bureauBudgets[$lbl]->budget : 0.0,
            'realization' => isset($bureauRealizations[$lbl]) ? (float) $bureauRealizations[$lbl]->amount : 0.0,
            'projection'  => isset($bureauProjections[$lbl]) ? (float) $bureauProjections[$lbl]->amount : 0.0,
          ];
        }

        usort($bureauData, fn($a, $b) => $b['budget'] <=> $a['budget']);
      }

      // --- 5. COA Category breakdown in PHP (database dialect safe) ---
      $items = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->selectRaw('rkap_budget_items.account_code, SUM(rkap_budget_items.total_price) as total')
        ->groupBy('rkap_budget_items.account_code')
        ->get();

      $coaGroups = [];
      foreach ($items as $item) {
        $prefix = substr($item->account_code, 0, 3) ?: 'Others';
        $coaGroups[$prefix] = ($coaGroups[$prefix] ?? 0.0) + (float) $item->total;
      }

      $coaLabelMap = [
        '521' => 'Belanja Pegawai',
        '522' => 'Belanja Barang',
        '523' => 'Belanja Jasa',
        '524' => 'Belanja Pemeliharaan',
        '525' => 'Belanja Perjalanan Dinas',
        '531' => 'Investasi Gedung',
        '532' => 'Investasi Peralatan/Mesin',
      ];

      arsort($coaGroups);
      $count = 0;
      $otherTotal = 0.0;
      foreach ($coaGroups as $prefix => $total) {
        if ($count < 5) {
          $coa = \App\Models\Coa::where('code', 'like', $prefix . '%')->orderBy('code')->first();
          $label = $coa ? $coa->title : ($coaLabelMap[$prefix] ?? $prefix);
          $coaData[] = [
            'label' => $label,
            'total' => $total,
          ];
          $count++;
        } else {
          $otherTotal += $total;
        }
      }
      if ($otherTotal > 0) {
        $coaData[] = [
          'label' => 'Belanja Lainnya',
          'total' => $otherTotal,
        ];
      }

      // --- 6. Profit and Loss Summary using report_groups (type = PL) ---
      // Fetch all PL report groups with their mapped COA groups
      $plReportGroups = \App\Models\ReportGroup::where('type', 'PL')
        ->orderBy('code')
        ->with(['coaGroups' => fn($q) => $q->orderBy('code')])
        ->get();

      // Get all COA group IDs that belong to PL report groups
      $plCoaGroupIds = $plReportGroups->flatMap(fn($rg) => $rg->coaGroups->pluck('id'))->toArray();

      // Build the base query joining budget items → coas → coa_groups for PL report groups
      $baseJoin = fn() => DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroups->pluck('id')->toArray());

      // Budget per COA group
      $cgBudgets = $baseJoin()
        ->selectRaw('coa_groups.id as coa_group_id, SUM(rkap_budget_items.total_price) as total')
        ->groupBy('coa_groups.id')
        ->pluck('total', 'coa_group_id')
        ->toArray();

      // Realization per COA group
      $cgRealizations = DB::table('rkap_budget_item_realizations')
        ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroups->pluck('id')->toArray())
        ->selectRaw('coa_groups.id as coa_group_id, SUM(rkap_budget_item_realizations.amount) as total')
        ->groupBy('coa_groups.id')
        ->pluck('total', 'coa_group_id')
        ->toArray();

      // Projection per COA group
      $cgProjections = DB::table('rkap_budget_item_projections')
        ->join('rkap_budget_items', 'rkap_budget_item_projections.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_budget_item_projections.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroups->pluck('id')->toArray())
        ->selectRaw('coa_groups.id as coa_group_id, SUM(rkap_budget_item_projections.amount) as total')
        ->groupBy('coa_groups.id')
        ->pluck('total', 'coa_group_id')
        ->toArray();

      // Color palette for COA group items within each report group
      $colorPalette = ['primary', 'info', 'success', 'warning', 'danger', 'secondary', 'dark'];

      // Build plGroups structure from report_groups
      $mappedBudgetSum = 0.0;
      $mappedRealizationSum = 0.0;
      $mappedProjectionSum = 0.0;

      foreach ($plReportGroups as $rg) {
        $items = [];
        $budgetSubtotal = 0.0;
        $realizationSubtotal = 0.0;
        $projectionSubtotal = 0.0;

        foreach ($rg->coaGroups as $idx => $cg) {
          $budget = (float) ($cgBudgets[$cg->id] ?? 0.0);
          $realization = (float) ($cgRealizations[$cg->id] ?? 0.0);
          $projection = (float) ($cgProjections[$cg->id] ?? 0.0);

          $budgetSubtotal += $budget;
          $realizationSubtotal += $realization;
          $projectionSubtotal += $projection;

          $items[] = [
            'id' => $cg->id,
            'key' => $cg->code,
            'label' => $cg->name,
            'color' => $colorPalette[$idx % count($colorPalette)],
            'budget' => $budget,
            'realization' => $realization,
            'projection' => $projection,
          ];
        }

        $mappedBudgetSum += $budgetSubtotal;
        $mappedRealizationSum += $realizationSubtotal;
        $mappedProjectionSum += $projectionSubtotal;

        $plGroups[$rg->name] = [
          'label' => $rg->name,
          'items' => $items,
          'budget_subtotal' => $budgetSubtotal,
          'realization_subtotal' => $realizationSubtotal,
          'projection_subtotal' => $projectionSubtotal,
        ];
      }

      // Calculate unmapped amounts
      $unmappedBudget = max(0.0, $stats['total_budget'] - $mappedBudgetSum);
      $unmappedRealization = max(0.0, $stats['total_realization'] - $mappedRealizationSum);
      $unmappedProjection = max(0.0, $stats['total_projection'] - $mappedProjectionSum);

      if ($unmappedBudget > 0 || $unmappedRealization > 0 || $unmappedProjection > 0) {
        $unmappedGroup = [
          'label' => 'Belum Dipetakan / Lainnya',
          'items' => [
            [
              'label' => 'Lainnya / Belum Dipetakan',
              'group' => 'Unmapped',
              'color' => 'secondary',
              'budget' => $unmappedBudget,
              'realization' => $unmappedRealization,
              'projection' => $unmappedProjection,
            ]
          ],
          'budget_subtotal' => $unmappedBudget,
          'realization_subtotal' => $unmappedRealization,
          'projection_subtotal' => $unmappedProjection,
        ];
      }

      // Build P&L summary (Revenue = PL0001, Direct Cost = PL0002, Indirect Cost = PL0003, Other Income (exp) = PL0004)
      $revenueBudget = $plGroups['Revenue']['budget_subtotal'] ?? 0.0;
      $revenueReal = $plGroups['Revenue']['realization_subtotal'] ?? 0.0;
      $revenueProj = $plGroups['Revenue']['projection_subtotal'] ?? 0.0;

      $directCostBudget = $plGroups['Direct Cost']['budget_subtotal'] ?? 0.0;
      $directCostReal = $plGroups['Direct Cost']['realization_subtotal'] ?? 0.0;
      $directCostProj = $plGroups['Direct Cost']['projection_subtotal'] ?? 0.0;

      $indirectCostBudget = $plGroups['Indirect Cost']['budget_subtotal'] ?? 0.0;
      $indirectCostReal = $plGroups['Indirect Cost']['realization_subtotal'] ?? 0.0;
      $indirectCostProj = $plGroups['Indirect Cost']['projection_subtotal'] ?? 0.0;

      $otherBudget = $plGroups['Other Income (exp)']['budget_subtotal'] ?? 0.0;
      $otherReal = $plGroups['Other Income (exp)']['realization_subtotal'] ?? 0.0;
      $otherProj = $plGroups['Other Income (exp)']['projection_subtotal'] ?? 0.0;

      $grossProfitBudget = $revenueBudget - $directCostBudget;
      $grossProfitReal = $revenueReal - $directCostReal;
      $grossProfitProj = $revenueProj - $directCostProj;

      $ebitdaBudget = $grossProfitBudget - $indirectCostBudget;
      $ebitdaReal = $grossProfitReal - $indirectCostReal;
      $ebitdaProj = $grossProfitProj - $indirectCostProj;

      $netProfitBudget = $ebitdaBudget + $otherBudget;
      $netProfitReal = $ebitdaReal + $otherReal;
      $netProfitProj = $ebitdaProj + $otherProj;

      $plSummary = [
        'revenue' => [
          'label' => 'Total Pendapatan',
          'budget' => $revenueBudget,
          'realization' => $revenueReal,
          'projection' => $revenueProj,
        ],
        'direct_cost' => [
          'label' => 'Total Beban Langsung',
          'budget' => $directCostBudget,
          'realization' => $directCostReal,
          'projection' => $directCostProj,
        ],
        'gross_profit' => [
          'label' => 'Laba Kotor (Gross Profit)',
          'budget' => $grossProfitBudget,
          'realization' => $grossProfitReal,
          'projection' => $grossProfitProj,
        ],
        'indirect_cost' => [
          'label' => 'Total Beban Tidak Langsung',
          'budget' => $indirectCostBudget,
          'realization' => $indirectCostReal,
          'projection' => $indirectCostProj,
        ],
        'operating_profit' => [
          'label' => 'Laba Usaha (EBITDA)',
          'budget' => $ebitdaBudget,
          'realization' => $ebitdaReal,
          'projection' => $ebitdaProj,
        ],
        'other_income_exp' => [
          'label' => 'Pendapatan / (Beban) Lainnya',
          'budget' => $otherBudget,
          'realization' => $otherReal,
          'projection' => $otherProj,
        ],
        'net_profit' => [
          'label' => 'Laba Bersih (Net Profit)',
          'budget' => $netProfitBudget,
          'realization' => $netProfitReal,
          'projection' => $netProfitProj,
        ],
      ];
    }

    // Calculate cumulative arrays for the line trend
    $cumulativeBudget = [];
    $cumulativeRealization = [];
    $cumulativeProjection = [];

    $sumBudget = 0.0;
    $sumReal = 0.0;
    $sumProj = 0.0;
    $currentMonth = (int) date('n');
    $isPastPeriod = $activePeriod && ($activePeriod->year < $currentYear);

    for ($i = 1; $i <= 12; $i++) {
      $sumBudget += $monthlyBudgetData[$i];
      $cumulativeBudget[] = $sumBudget;

      if ($isPastPeriod || $i <= $currentMonth) {
        $sumReal += $monthlyRealizationData[$i];
        $cumulativeRealization[] = $sumReal;
      } else {
        $cumulativeRealization[] = null;
      }

      $sumProj += $monthlyProjectionData[$i];
      $cumulativeProjection[] = $sumProj;
    }

    // Calculate annual comparison data (Tahun Lalu, Tahun Berjalan, Tahun Depan)
    $yearsToCompare = [$currentYear - 1, $currentYear, $currentYear + 1];
    $comparisonData = [];

    foreach ($yearsToCompare as $yr) {
      $period = RkapPeriod::where('year', $yr)->first();
      if ($period) {
        $isNextYear = ($yr === $currentYear + 1);

        $budget = (float) DB::table('rkap_submissions')
          ->where('rkap_period_id', $period->id)
          ->unless($isNextYear, fn($q) => $q->where('status', 'approved'))
          ->when($bureauIds, fn($q) => $q->whereIn('bureau_id', $bureauIds))
          ->sum('total_budget');

        $realization = (float) DB::table('rkap_budget_item_realizations')
          ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->where('rkap_budget_item_realizations.rkap_period_id', $period->id)
          ->unless($isNextYear, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->sum('rkap_budget_item_realizations.amount');

        $projection = (float) DB::table('rkap_budget_item_projections')
          ->join('rkap_budget_items', 'rkap_budget_item_projections.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->where('rkap_budget_item_projections.rkap_period_id', $period->id)
          ->unless($isNextYear, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->sum('rkap_budget_item_projections.amount');
      } else {
        $budget = 0.0;
        $realization = 0.0;
        $projection = 0.0;
      }

      if ($yr === $currentYear - 1) {
        $label = $yr . ' (Tahun Lalu)';
      } elseif ($yr === $currentYear) {
        $label = $yr . ' (Tahun Berjalan)';
      } else {
        $label = $yr . ' (Tahun Depan)';
      }

      $comparisonData[] = [
        'year' => $yr,
        'label' => $label,
        'budget' => $budget,
        'realization' => $realization,
        'projection' => $projection,
      ];
    }

    return view('content.dashboard.dashboards-analytics', compact(
      'activePeriod',
      'finalizedPeriods',
      'stats',
      'monthlyBudgetData',
      'monthlyRealizationData',
      'monthlyProjectionData',
      'cumulativeBudget',
      'cumulativeRealization',
      'cumulativeProjection',
      'directorateData',
      'departmentData',
      'bureauData',
      'coaData',
      'plGroups',
      'plSummary',
      'unmappedGroup',
      'comparisonData'
    ));
  }
}

