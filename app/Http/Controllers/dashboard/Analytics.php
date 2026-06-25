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

    // Fetch all PL report groups and their IDs
    $plReportGroups = \App\Models\ReportGroup::where('type', 'PL')
      ->orderBy('code')
      ->with(['coaGroups' => fn($q) => $q->orderBy('code')])
      ->get();
    $plReportGroupIds = $plReportGroups->pluck('id')->toArray();

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

      // Total Budget (Approved Submissions only, filtered by PL report groups)
      $stats['total_budget'] = (float) DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
        ->sum('rkap_budget_items.total_price');

      // Total Realization YTD (filtered by PL report groups)
      $stats['total_realization'] = (float) DB::table('rkap_budget_item_realizations')
        ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
        ->sum('rkap_budget_item_realizations.amount');

      // Total Projection (filtered by PL report groups)
      $stats['total_projection'] = (float) DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
        ->sum('rkap_budget_items.projection');

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
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
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
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
        ->selectRaw('month, SUM(rkap_budget_item_realizations.amount) as total')
        ->groupBy('month')
        ->pluck('total', 'month')
        ->toArray();

      foreach ($realizations as $m => $val) {
        $monthlyRealizationData[$m] = (float) $val;
      }

      // --- 3. Monthly Projection Data ---
      $monthlyProjSums = DB::table('rkap_budget_item_projections')
        ->join('rkap_budget_items', 'rkap_budget_item_projections.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
        ->selectRaw('month, SUM(rkap_budget_item_projections.amount) as total')
        ->groupBy('month')
        ->pluck('total', 'month')
        ->toArray();

      foreach ($monthlyProjSums as $m => $val) {
        $monthlyProjectionData[$m] += (float) $val;
      }

      $yearlyItems = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
        ->where('rkap_budget_items.projection', '>', 0)
        ->whereNotExists(function ($query) {
          $query->select(DB::raw(1))
            ->from('rkap_budget_item_projections')
            ->whereRaw('rkap_budget_item_projections.rkap_budget_item_id = rkap_budget_items.id');
        })
        ->select('rkap_budget_items.id', 'rkap_budget_items.projection')
        ->get();

      $yearlyItemIds = $yearlyItems->pluck('id')->toArray();
      $monthlyPlans = [];
      if (!empty($yearlyItemIds)) {
        $monthlyPlans = DB::table('rkap_budget_item_monthlies')
          ->whereIn('rkap_budget_item_id', $yearlyItemIds)
          ->select('rkap_budget_item_id', 'month', 'amount')
          ->get()
          ->groupBy('rkap_budget_item_id');
      }

      foreach ($yearlyItems as $item) {
        $itemId = $item->id;
        $projectionAmount = (float) $item->projection;

        $itemPlans = isset($monthlyPlans[$itemId]) ? $monthlyPlans[$itemId] : collect();
        $totalPlanAmount = $itemPlans->sum('amount');

        if ($totalPlanAmount > 0) {
          foreach ($itemPlans as $plan) {
            $m = (int) $plan->month;
            $monthlyProjectionData[$m] += $projectionAmount * ((float) $plan->amount / $totalPlanAmount);
          }
        } else {
          for ($m = 1; $m <= 12; $m++) {
            $monthlyProjectionData[$m] += $projectionAmount / 12;
          }
        }
      }

      // --- 4a. Directorate-level budgets, realizations, projections ---
      $directorateBudgets = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->join('departments', 'bureaus.department_id', '=', 'departments.id')
        ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($userDirectorateId, fn($q) => $q->where('departments.directorate_id', $userDirectorateId))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
        ->selectRaw('directorates.name as label, SUM(rkap_budget_items.total_price) as budget')
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
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($userDirectorateId, fn($q) => $q->where('departments.directorate_id', $userDirectorateId))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
        ->selectRaw('directorates.name as label, SUM(rkap_budget_item_realizations.amount) as amount')
        ->groupBy('directorates.name')
        ->get()
        ->keyBy('label')
        ->toArray();

      $directorateProjections = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->join('departments', 'bureaus.department_id', '=', 'departments.id')
        ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($userDirectorateId, fn($q) => $q->where('departments.directorate_id', $userDirectorateId))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
        ->selectRaw('directorates.name as label, SUM(rkap_budget_items.projection) as amount')
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
          'label' => $lbl,
          'budget' => isset($directorateBudgets[$lbl]) ? (float) $directorateBudgets[$lbl]->budget : 0.0,
          'realization' => isset($directorateRealizations[$lbl]) ? (float) $directorateRealizations[$lbl]->amount : 0.0,
          'projection' => isset($directorateProjections[$lbl]) ? (float) $directorateProjections[$lbl]->amount : 0.0,
        ];
      }

      // --- 4b. Department-level budgets, realizations, projections ---
      $departmentBudgets = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->join('departments', 'bureaus.department_id', '=', 'departments.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($deptFilterField, fn($q) => $q->where($deptFilterField, $deptFilterVal))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
        ->selectRaw('departments.name as label, SUM(rkap_budget_items.total_price) as budget')
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
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($deptFilterField, fn($q) => $q->where($deptFilterField, $deptFilterVal))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
        ->selectRaw('departments.name as label, SUM(rkap_budget_item_realizations.amount) as amount')
        ->groupBy('departments.name')
        ->get()
        ->keyBy('label')
        ->toArray();

      $departmentProjections = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->join('departments', 'bureaus.department_id', '=', 'departments.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($deptFilterField, fn($q) => $q->where($deptFilterField, $deptFilterVal))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
        ->selectRaw('departments.name as label, SUM(rkap_budget_items.projection) as amount')
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
          'label' => $lbl,
          'budget' => isset($departmentBudgets[$lbl]) ? (float) $departmentBudgets[$lbl]->budget : 0.0,
          'realization' => isset($departmentRealizations[$lbl]) ? (float) $departmentRealizations[$lbl]->amount : 0.0,
          'projection' => isset($departmentProjections[$lbl]) ? (float) $departmentProjections[$lbl]->amount : 0.0,
        ];
      }

      // Sort by budget descending so largest departments appear first
      usort($departmentData, fn($a, $b) => $b['budget'] <=> $a['budget']);

      // --- 4c. Bureau-level budgets, realizations, projections ---
      if ($user->isKepalaDepartemen()) {
        $bureauBudgets = DB::table('rkap_budget_items')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
          ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
          ->where('rkap_submissions.rkap_period_id', $periodId)
          ->where('rkap_submissions.status', 'approved')
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->whereNull('coas.deleted_at')
          ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
          ->selectRaw('bureaus.name as label, SUM(rkap_budget_items.total_price) as budget')
          ->groupBy('bureaus.name')
          ->get()
          ->keyBy('label')
          ->toArray();

        $bureauRealizations = DB::table('rkap_budget_item_realizations')
          ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
          ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
          ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
          ->where('rkap_submissions.status', 'approved')
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->whereNull('coas.deleted_at')
          ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
          ->selectRaw('bureaus.name as label, SUM(rkap_budget_item_realizations.amount) as amount')
          ->groupBy('bureaus.name')
          ->get()
          ->keyBy('label')
          ->toArray();

        $bureauProjections = DB::table('rkap_budget_items')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
          ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
          ->where('rkap_submissions.rkap_period_id', $periodId)
          ->where('rkap_submissions.status', 'approved')
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->whereNull('coas.deleted_at')
          ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
          ->selectRaw('bureaus.name as label, SUM(rkap_budget_items.projection) as amount')
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
            'label' => $lbl,
            'budget' => isset($bureauBudgets[$lbl]) ? (float) $bureauBudgets[$lbl]->budget : 0.0,
            'realization' => isset($bureauRealizations[$lbl]) ? (float) $bureauRealizations[$lbl]->amount : 0.0,
            'projection' => isset($bureauProjections[$lbl]) ? (float) $bureauProjections[$lbl]->amount : 0.0,
          ];
        }

        usort($bureauData, fn($a, $b) => $b['budget'] <=> $a['budget']);
      }

      // --- 5. COA Category breakdown in PHP (database dialect safe) ---
      $items = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
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
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds);

      // Budget per COA group and code
      $cgBudgetsRaw = $baseJoin()
        ->selectRaw('coa_groups.id as coa_group_id, coa_groups.code as coa_group_code, coas.code as coa_code, SUM(rkap_budget_items.total_price) as total')
        ->groupBy('coa_groups.id', 'coa_groups.code', 'coas.code')
        ->get();

      // Realization per COA group and code
      $cgRealizationsRaw = DB::table('rkap_budget_item_realizations')
        ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
        ->selectRaw('coa_groups.id as coa_group_id, coa_groups.code as coa_group_code, coas.code as coa_code, SUM(rkap_budget_item_realizations.amount) as total')
        ->groupBy('coa_groups.id', 'coa_groups.code', 'coas.code')
        ->get();

      // Projection per COA group and code
      $cgProjectionsRaw = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
        ->selectRaw('coa_groups.id as coa_group_id, coa_groups.code as coa_group_code, coas.code as coa_code, SUM(rkap_budget_items.projection) as total')
        ->groupBy('coa_groups.id', 'coa_groups.code', 'coas.code')
        ->get();

      $aggregateCgData = function ($rawItems) {
        $aggregated = [];
        foreach ($rawItems as $item) {
          $cgId = $item->coa_group_id;
          $cgCode = $item->coa_group_code;
          $coaCode = $item->coa_code;
          $amount = (float) $item->total;

          if (!isset($aggregated[$cgId])) {
            $aggregated[$cgId] = 0.0;
          }

          $isOtherGroup = in_array($cgCode, ['7000', '7001', '7001A', '7002', '7002A', '7003', '7004', '7005']);

          if ($isOtherGroup) {
            if ($cgCode === '7005') {
              if (str_starts_with($coaCode, '71')) {
                $aggregated[$cgId] -= $amount;
              } elseif (str_starts_with($coaCode, '76') || str_starts_with($coaCode, '79')) {
                $aggregated[$cgId] += $amount;
              }
            } else {
              $aggregated[$cgId] += $amount;
            }
          } else {
            $aggregated[$cgId] += $amount;
          }
        }
        return $aggregated;
      };

      $cgBudgets = $aggregateCgData($cgBudgetsRaw);
      $cgRealizations = $aggregateCgData($cgRealizationsRaw);
      $cgProjections = $aggregateCgData($cgProjectionsRaw);

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

          if ($rg->code === 'PL0004') {
            $isExpenseGroup = in_array($cg->code, ['7000', '7001', '7001A', '7002', '7002A', '7004']);
            $budgetSubtotal += $isExpenseGroup ? -$budget : $budget;
            $realizationSubtotal += $isExpenseGroup ? -$realization : $realization;
            $projectionSubtotal += $isExpenseGroup ? -$projection : $projection;
          } else {
            $budgetSubtotal += $budget;
            $realizationSubtotal += $realization;
            $projectionSubtotal += $projection;
          }

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

      // Build P&L summary (Revenue = PL0001, Direct Cost = PL0002, Indirect Cost = PL0003, Other Income = PL0004)
      $revenueBudget = $plGroups['Revenue']['budget_subtotal'] ?? 0.0;
      $revenueReal = $plGroups['Revenue']['realization_subtotal'] ?? 0.0;
      $revenueProj = $plGroups['Revenue']['projection_subtotal'] ?? 0.0;

      $directCostBudget = $plGroups['Direct Cost']['budget_subtotal'] ?? 0.0;
      $directCostReal = $plGroups['Direct Cost']['realization_subtotal'] ?? 0.0;
      $directCostProj = $plGroups['Direct Cost']['projection_subtotal'] ?? 0.0;

      $indirectCostBudget = $plGroups['Indirect Cost']['budget_subtotal'] ?? 0.0;
      $indirectCostReal = $plGroups['Indirect Cost']['realization_subtotal'] ?? 0.0;
      $indirectCostProj = $plGroups['Indirect Cost']['projection_subtotal'] ?? 0.0;

      $otherBudget = $plGroups['Other Income']['budget_subtotal'] ?? 0.0;
      $otherBudgetExpenses = $plGroups['Other Expense']['budget_subtotal'] ?? 0.0;
      $otherReal = $plGroups['Other Income']['realization_subtotal'] ?? 0.0;
      $otherRealExpenses = $plGroups['Other Expense']['realization_subtotal'] ?? 0.0;
      $otherProj = $plGroups['Other Income']['projection_subtotal'] ?? 0.0;
      $otherProjExpenses = $plGroups['Other Expense']['projection_subtotal'] ?? 0.0;

      $grossProfitBudget = $revenueBudget - $directCostBudget;
      $grossProfitReal = $revenueReal - $directCostReal;
      $grossProfitProj = $revenueProj - $directCostProj;

      $ebitdaBudget = $grossProfitBudget - $indirectCostBudget;
      $ebitdaReal = $grossProfitReal - $indirectCostReal;
      $ebitdaProj = $grossProfitProj - $indirectCostProj;

      $netProfitBudget = $ebitdaBudget + $otherBudget - $otherBudgetExpenses;
      $netProfitReal = $ebitdaReal + $otherReal - $otherRealExpenses;
      $netProfitProj = $ebitdaProj + $otherProj - $otherProjExpenses;

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
          'label' => 'Laba (Rugi) Usaha',
          'budget' => $ebitdaBudget,
          'realization' => $ebitdaReal,
          'projection' => $ebitdaProj,
        ],
        'other_income_exp' => [
          'label' => 'Pendapatan / (Beban) Lainnya',
          'budget' => $otherBudget - $otherBudgetExpenses,
          'realization' => $otherReal - $otherRealExpenses,
          'projection' => $otherProj - $otherProjExpenses,
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

        $budget = (float) DB::table('rkap_budget_items')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
          ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
          ->where('rkap_submissions.rkap_period_id', $period->id)
          ->unless($isNextYear, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->whereNull('coas.deleted_at')
          ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
          ->sum('rkap_budget_items.total_price');

        $realization = (float) DB::table('rkap_budget_item_realizations')
          ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
          ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
          ->where('rkap_budget_item_realizations.rkap_period_id', $period->id)
          ->unless($isNextYear, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->whereNull('coas.deleted_at')
          ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
          ->sum('rkap_budget_item_realizations.amount');

        $projection = (float) DB::table('rkap_budget_items')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
          ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
          ->where('rkap_submissions.rkap_period_id', $period->id)
          ->unless($isNextYear, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->whereNull('coas.deleted_at')
          ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
          ->sum('rkap_budget_items.projection');
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

  public function coaGroupDetail(Request $request)
  {
    $user = Auth::user();
    if (!$user) {
      abort(403);
    }

    $coaGroupId = (int) $request->query('coa_group_id');
    $periodId = (int) $request->query('period_id');

    if (!$coaGroupId || !$periodId) {
      return response()->json(['data' => []]);
    }

    // Resolve bureau scoping same as index()
    $bureauIds = null;
    if ($user->isKepalaBiro()) {
      $bureauIds = [$user->bureau_id];
    } elseif ($user->isKepalaDepartemen()) {
      $bureauIds = DB::table('bureaus')
        ->where('department_id', $user->department_id)
        ->pluck('id')
        ->toArray();
    }

    // Fetch budget item rows for this coa_group in the given period
    $rows = DB::table('rkap_budget_items')
      ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
      ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
      ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
      ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
      ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
      ->join('departments', 'bureaus.department_id', '=', 'departments.id')
      ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
      ->leftJoin(DB::raw('(SELECT rkap_budget_item_id, SUM(amount) as realization_total FROM rkap_budget_item_realizations WHERE rkap_period_id = ' . $periodId . ' GROUP BY rkap_budget_item_id) as rl'), 'rl.rkap_budget_item_id', '=', 'rkap_budget_items.id')
      ->where('coa_groups.id', $coaGroupId)
      ->where('rkap_submissions.rkap_period_id', $periodId)
      ->where('rkap_submissions.status', 'approved')
      ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
      ->whereNull('coas.deleted_at')
      ->selectRaw('
        coas.code as coa_code,
        coas.title as coa_title,
        rkap_work_plans.program_code,
        rkap_work_plans.program_name,
        directorates.code as directorate_code,
        departments.code as department_code,
        bureaus.code as bureau_code,
        SUM(rkap_budget_items.total_price) as budget,
        SUM(COALESCE(rl.realization_total, 0)) as realization,
        SUM(rkap_budget_items.projection) as projection
      ')
      ->groupBy(
        'coas.code',
        'coas.title',
        'rkap_work_plans.program_code',
        'rkap_work_plans.program_name',
        'directorates.code',
        'departments.code',
        'bureaus.code'
      )
      ->orderBy('coas.code')
      ->orderBy('rkap_work_plans.program_code')
      ->get();

    return response()->json(['data' => $rows]);
  }
}
