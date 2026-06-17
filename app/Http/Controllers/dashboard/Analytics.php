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
  public function index()
  {
    $user = Auth::user();
    if (!$user) {
      abort(403);
    }

    // 1. Get the current year RKAP period (or latest finalized, or latest period)
    $currentYear = (int) date('Y');
    $activePeriod = RkapPeriod::where('year', $currentYear)->first()
      ?? RkapPeriod::where('status', 'finalized')->latest()->first()
      ?? RkapPeriod::latest()->first();

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
    $coaData = [];

    if ($activePeriod) {
      $periodId = $activePeriod->id;

      // Define scoped bureau IDs based on user role
      $bureauIds = null;
      if ($user->isKepalaBiro()) {
        $bureauIds = [$user->bureau_id];
      } elseif ($user->isKepalaDepartemen()) {
        $bureauIds = DB::table('bureaus')->where('department_id', $user->department_id)->pluck('id')->toArray();
      }

      // Define scoped filters for high-level comparison charts (directorate and department level)
      // Both views should use the same directorate scope so totals are consistent
      $userDirectorateId = null;
      $deptFilterField = null;
      $deptFilterVal = null;
      if ($user->isKepalaBiro() || $user->isKepalaDepartemen()) {
        $userDirectorateId = $user->directorate_id;
        // Always filter departments by directorate so both views show the same scope
        $deptFilterField = 'departments.directorate_id';
        $deptFilterVal = $userDirectorateId;
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

      // --- 6. Profit and Loss Category Summaries ---
      $categoryBudgets = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->selectRaw('coas.coa_category_id, SUM(rkap_budget_items.total_price) as total')
        ->groupBy('coas.coa_category_id')
        ->pluck('total', 'coa_category_id')
        ->toArray();

      $categoryRealizations = DB::table('rkap_budget_item_realizations')
        ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->selectRaw('coas.coa_category_id, SUM(rkap_budget_item_realizations.amount) as total')
        ->groupBy('coas.coa_category_id')
        ->pluck('total', 'coa_category_id')
        ->toArray();

      $categoryProjections = DB::table('rkap_budget_item_projections')
        ->join('rkap_budget_items', 'rkap_budget_item_projections.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->where('rkap_budget_item_projections.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->selectRaw('coas.coa_category_id, SUM(rkap_budget_item_projections.amount) as total')
        ->groupBy('coas.coa_category_id')
        ->pluck('total', 'coa_category_id')
        ->toArray();

      $coaCategories = \App\Models\CoaCategory::ordered()->get();
      $plCategories = [];
      $mappedBudgetSum = 0.0;
      $mappedRealizationSum = 0.0;
      $mappedProjectionSum = 0.0;

      foreach ($coaCategories as $cat) {
        $budget = (float) ($categoryBudgets[$cat->id] ?? 0.0);
        $realization = (float) ($categoryRealizations[$cat->id] ?? 0.0);
        $projection = (float) ($categoryProjections[$cat->id] ?? 0.0);

        $mappedBudgetSum += $budget;
        $mappedRealizationSum += $realization;
        $mappedProjectionSum += $projection;

        $plCategories[] = [
          'id' => $cat->id,
          'key' => $cat->key,
          'label' => $cat->label,
          'group' => $cat->group,
          'color' => $cat->color,
          'budget' => $budget,
          'realization' => $realization,
          'projection' => $projection,
        ];
      }

      $unmappedBudget = max(0.0, $stats['total_budget'] - $mappedBudgetSum);
      $unmappedRealization = max(0.0, $stats['total_realization'] - $mappedRealizationSum);
      $unmappedProjection = max(0.0, $stats['total_projection'] - $mappedProjectionSum);

      $plUnmapped = null;
      if ($unmappedBudget > 0 || $unmappedRealization > 0 || $unmappedProjection > 0) {
        $plUnmapped = [
          'label' => 'Lainnya / Belum Dipetakan',
          'group' => 'Unmapped',
          'color' => 'secondary',
          'budget' => $unmappedBudget,
          'realization' => $unmappedRealization,
          'projection' => $unmappedProjection,
        ];
      }

      $plGroups = [
        'Revenue' => [
          'label' => 'Pendapatan',
          'items' => [],
          'budget_subtotal' => 0.0,
          'realization_subtotal' => 0.0,
          'projection_subtotal' => 0.0,
        ],
        'Direct Cost' => [
          'label' => 'Beban Langsung',
          'items' => [],
          'budget_subtotal' => 0.0,
          'realization_subtotal' => 0.0,
          'projection_subtotal' => 0.0,
        ],
        'Indirect Cost' => [
          'label' => 'Beban Tidak Langsung',
          'items' => [],
          'budget_subtotal' => 0.0,
          'realization_subtotal' => 0.0,
          'projection_subtotal' => 0.0,
        ],
        'Non-Operating' => [
          'label' => 'Non-Operasional',
          'items' => [],
          'budget_subtotal' => 0.0,
          'realization_subtotal' => 0.0,
          'projection_subtotal' => 0.0,
        ],
      ];

      foreach ($plCategories as $item) {
        $grp = $item['group'];
        if (isset($plGroups[$grp])) {
          $plGroups[$grp]['items'][] = $item;
          $plGroups[$grp]['budget_subtotal'] += $item['budget'];
          $plGroups[$grp]['realization_subtotal'] += $item['realization'];
          $plGroups[$grp]['projection_subtotal'] += $item['projection'];
        }
      }

      if ($plUnmapped) {
        $unmappedGroup = [
          'label' => 'Belum Dipetakan / Lainnya',
          'items' => [$plUnmapped],
          'budget_subtotal' => $unmappedBudget,
          'realization_subtotal' => $unmappedRealization,
          'projection_subtotal' => $unmappedProjection,
        ];
      }

      $noRevenueBudget = 0.0; $noRevenueReal = 0.0; $noRevenueProj = 0.0;
      $noExpenseBudget = 0.0; $noExpenseReal = 0.0; $noExpenseProj = 0.0;
      foreach ($plGroups['Non-Operating']['items'] as $item) {
        if ($item['key'] === 'non_operating_revenue') {
          $noRevenueBudget = $item['budget'];
          $noRevenueReal = $item['realization'];
          $noRevenueProj = $item['projection'];
        } elseif ($item['key'] === 'non_operating_expense') {
          $noExpenseBudget = $item['budget'];
          $noExpenseReal = $item['realization'];
          $noExpenseProj = $item['projection'];
        }
      }

      $plSummary = [
        'revenue' => [
          'label' => 'Total Pendapatan',
          'budget' => $plGroups['Revenue']['budget_subtotal'],
          'realization' => $plGroups['Revenue']['realization_subtotal'],
          'projection' => $plGroups['Revenue']['projection_subtotal'],
        ],
        'direct_cost' => [
          'label' => 'Total Beban Langsung',
          'budget' => $plGroups['Direct Cost']['budget_subtotal'],
          'realization' => $plGroups['Direct Cost']['realization_subtotal'],
          'projection' => $plGroups['Direct Cost']['projection_subtotal'],
        ],
        'gross_profit' => [
          'label' => 'Laba Kotor (Gross Profit)',
          'budget' => $plGroups['Revenue']['budget_subtotal'] - $plGroups['Direct Cost']['budget_subtotal'],
          'realization' => $plGroups['Revenue']['realization_subtotal'] - $plGroups['Direct Cost']['realization_subtotal'],
          'projection' => $plGroups['Revenue']['projection_subtotal'] - $plGroups['Direct Cost']['projection_subtotal'],
        ],
        'indirect_cost' => [
          'label' => 'Total Beban Tidak Langsung',
          'budget' => $plGroups['Indirect Cost']['budget_subtotal'],
          'realization' => $plGroups['Indirect Cost']['realization_subtotal'],
          'projection' => $plGroups['Indirect Cost']['projection_subtotal'],
        ],
        'operating_profit' => [
          'label' => 'Laba Usaha (EBITDA)',
          'budget' => ($plGroups['Revenue']['budget_subtotal'] - $plGroups['Direct Cost']['budget_subtotal']) - $plGroups['Indirect Cost']['budget_subtotal'],
          'realization' => ($plGroups['Revenue']['realization_subtotal'] - $plGroups['Direct Cost']['realization_subtotal']) - $plGroups['Indirect Cost']['realization_subtotal'],
          'projection' => ($plGroups['Revenue']['projection_subtotal'] - $plGroups['Direct Cost']['projection_subtotal']) - $plGroups['Indirect Cost']['projection_subtotal'],
        ],
        'non_operating_revenue' => [
          'label' => 'Pendapatan Non-Operasional',
          'budget' => $noRevenueBudget,
          'realization' => $noRevenueReal,
          'projection' => $noRevenueProj,
        ],
        'non_operating_expense' => [
          'label' => 'Beban Non-Operasional / Keuangan',
          'budget' => $noExpenseBudget,
          'realization' => $noExpenseReal,
          'projection' => $noExpenseProj,
        ],
        'net_profit' => [
          'label' => 'Laba Bersih (Net Profit)',
          'budget' => (($plGroups['Revenue']['budget_subtotal'] - $plGroups['Direct Cost']['budget_subtotal']) - $plGroups['Indirect Cost']['budget_subtotal']) + $noRevenueBudget - $noExpenseBudget,
          'realization' => (($plGroups['Revenue']['realization_subtotal'] - $plGroups['Direct Cost']['realization_subtotal']) - $plGroups['Indirect Cost']['realization_subtotal']) + $noRevenueReal - $noExpenseReal,
          'projection' => (($plGroups['Revenue']['projection_subtotal'] - $plGroups['Direct Cost']['projection_subtotal']) - $plGroups['Indirect Cost']['projection_subtotal']) + $noRevenueProj - $noExpenseProj,
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

    for ($i = 1; $i <= 12; $i++) {
      $sumBudget += $monthlyBudgetData[$i];
      $cumulativeBudget[] = $sumBudget;

      if ($monthlyRealizationData[$i] > 0 || $i <= $currentMonth) {
        $sumReal += $monthlyRealizationData[$i];
        $cumulativeRealization[] = $sumReal;
      } else {
        $cumulativeRealization[] = null;
      }

      $sumProj += $monthlyProjectionData[$i];
      $cumulativeProjection[] = $sumProj;
    }

    return view('content.dashboard.dashboards-analytics', compact(
      'activePeriod',
      'stats',
      'monthlyBudgetData',
      'monthlyRealizationData',
      'monthlyProjectionData',
      'cumulativeBudget',
      'cumulativeRealization',
      'cumulativeProjection',
      'directorateData',
      'departmentData',
      'coaData',
      'plGroups',
      'plSummary',
      'unmappedGroup'
    ));
  }
}

