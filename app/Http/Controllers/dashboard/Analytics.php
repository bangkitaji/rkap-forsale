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
  private function getAnalyticsData(Request $request): array
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

    $isReport = $request->routeIs('analytics-report') || $request->is('*report*');

    // Get periods for dropdown selection (include open/closed periods for report/PL page)
    if ($isReport) {
      $finalizedPeriods = RkapPeriod::orderBy('year', 'desc')
        ->orderBy('created_at', 'desc')
        ->get();
    } else {
      $finalizedPeriods = RkapPeriod::whereIn('status', ['finalized', 'open'])
        ->orderBy('year', 'desc')
        ->orderBy('created_at', 'desc')
        ->get();
    }

    // 1. Get the requested period
    $selectedPeriodId = $request->query('period_id');
    $activePeriod = null;

    if ($selectedPeriodId) {
      if ($isReport) {
        $activePeriod = RkapPeriod::find($selectedPeriodId);
      } else {
        $activePeriod = RkapPeriod::whereIn('status', ['finalized', 'open'])->find($selectedPeriodId);
      }
    }

    if (!$activePeriod) {
      if ($isReport) {
        $activePeriod = RkapPeriod::where('year', $currentYear)->first()
          ?? RkapPeriod::latest()->first();
      } else {
        $activePeriod = RkapPeriod::where('year', $currentYear)->whereIn('status', ['finalized', 'open'])->first()
          ?? RkapPeriod::whereIn('status', ['finalized', 'open'])->latest()->first()
          ?? RkapPeriod::latest()->first();
      }
    }

    // Determine whether to include all submission statuses (for report page or when active period is in draft/preparation status)
    $includeAllStatuses = $isReport || ($activePeriod && $activePeriod->status !== 'finalized');

    // High-level statistics
    $stats = [
      'total_budget' => 0.0,
      'total_realization' => 0.0,
      'total_projection' => 0.0,
      'absorption_rate' => 0.0,
      'outlook_rate' => 0.0,
    ];

    $income_stats = [
      'total_budget' => 0.0,
      'total_realization' => 0.0,
      'total_projection' => 0.0,
      'absorption_rate' => 0.0,
      'outlook_rate' => 0.0,
    ];

    $expense_stats = [
      'total_budget' => 0.0,
      'total_realization' => 0.0,
      'total_projection' => 0.0,
      'absorption_rate' => 0.0,
      'outlook_rate' => 0.0,
    ];

    $plGroups = [];
    $defaultSummaryItem = [
      'label' => '',
      'budget' => 0.0,
      'realization' => 0.0,
      'projection' => 0.0,
    ];
    $plSummary = [
      'revenue' => array_merge($defaultSummaryItem, ['label' => 'Total Pendapatan']),
      'direct_cost' => array_merge($defaultSummaryItem, ['label' => 'Total Beban Langsung']),
      'gross_profit' => array_merge($defaultSummaryItem, ['label' => 'Laba Kotor (Gross Profit)']),
      'indirect_cost' => array_merge($defaultSummaryItem, ['label' => 'Total Beban Tidak Langsung']),
      'operating_profit' => array_merge($defaultSummaryItem, ['label' => 'Laba (Rugi) Usaha']),
      'other_income' => array_merge($defaultSummaryItem, ['label' => 'Pendapatan Lain-lain (Other Income)']),
      'other_expense' => array_merge($defaultSummaryItem, ['label' => 'Beban Lain-lain (Other Expense)']),
      'net_profit' => array_merge($defaultSummaryItem, ['label' => 'Laba Bersih (Net Profit)']),
      'ebitda' => array_merge($defaultSummaryItem, ['label' => 'EBITDA']),
    ];
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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

      // --- Calculate separated stats for Pendapatan and Beban ---
      $activePeriodItems = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->join('report_groups', 'coa_groups.report_group_id', '=', 'report_groups.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
        ->select(
          'rkap_budget_items.id',
          'rkap_budget_items.total_price',
          'rkap_budget_items.projection',
          'coas.code as coa_code',
          'coa_groups.code as coa_group_code',
          'report_groups.code as report_group_code'
        )
        ->get();

      $activePeriodRealizationSums = DB::table('rkap_budget_item_realizations')
        ->where('rkap_period_id', $periodId)
        ->groupBy('rkap_budget_item_id')
        ->select('rkap_budget_item_id', DB::raw('SUM(amount) as total_amount'))
        ->pluck('total_amount', 'rkap_budget_item_id')
        ->toArray();

      foreach ($activePeriodItems as $item) {
        $isExpense = false;
        $rgCode = $item->report_group_code;
        $cgCode = $item->coa_group_code;
        $coaCode = $item->coa_code;

        $amountBudget = (float) $item->total_price;
        $amountReal = (float) ($activePeriodRealizationSums[$item->id] ?? 0.0);
        $amountProj = (float) $item->projection;

        if ($rgCode === 'PL0001') {
          $isExpense = false;
        } elseif ($rgCode === 'PL0002' || $rgCode === 'PL0003' || $rgCode === 'PL0005') {
          $isExpense = true;
        } elseif ($rgCode === 'PL0004') {
          if (in_array($cgCode, ['7000', '7001', '7001A', '7002', '7002A', '7004'])) {
            $isExpense = true;
          } elseif ($cgCode === '7005') {
            if (str_starts_with($coaCode, '76') || str_starts_with($coaCode, '79')) {
              $isExpense = true;
            } else {
              $isExpense = false;
            }
          } else {
            $isExpense = false;
          }
        }

        if ($isExpense) {
          $expense_stats['total_budget'] += $amountBudget;
          $expense_stats['total_realization'] += $amountReal;
          $expense_stats['total_projection'] += $amountProj;
        } else {
          $income_stats['total_budget'] += $amountBudget;
          $income_stats['total_realization'] += $amountReal;
          $income_stats['total_projection'] += $amountProj;
        }
      }

      $income_stats['absorption_rate'] = $income_stats['total_budget'] > 0
        ? round(($income_stats['total_realization'] / $income_stats['total_budget']) * 100, 1)
        : 0.0;

      $income_stats['outlook_rate'] = $income_stats['total_budget'] > 0
        ? round(($income_stats['total_projection'] / $income_stats['total_budget']) * 100, 1)
        : 0.0;

      $expense_stats['absorption_rate'] = $expense_stats['total_budget'] > 0
        ? round(($expense_stats['total_realization'] / $expense_stats['total_budget']) * 100, 1)
        : 0.0;

      $expense_stats['outlook_rate'] = $expense_stats['total_budget'] > 0
        ? round(($expense_stats['total_projection'] / $expense_stats['total_budget']) * 100, 1)
        : 0.0;

      // --- 1. Monthly Budget Allocations ---
      $budgets = DB::table('rkap_budget_item_monthlies')
        ->join('rkap_budget_items', 'rkap_budget_item_monthlies.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
          ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
          ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
          ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
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

      $operatingProfitBudget = $grossProfitBudget - $indirectCostBudget;
      $operatingProfitReal = $grossProfitReal - $indirectCostReal;
      $operatingProfitProj = $grossProfitProj - $indirectCostProj;

      // Extract Depreciation & Amortization (COA group codes: 5006 direct, 6005 indirect) and Rent (6001)
      $deprAmortCodes = ['5006', '6005'];
      $rentCode = '6001';
      $deprAmortBudget = 0.0;
      $deprAmortReal = 0.0;
      $deprAmortProj = 0.0;
      $rentBudget = 0.0;
      $rentReal = 0.0;
      $rentProj = 0.0;

      foreach (['Direct Cost', 'Indirect Cost'] as $groupName) {
        if (isset($plGroups[$groupName]['items'])) {
          foreach ($plGroups[$groupName]['items'] as $item) {
            if (in_array($item['key'], $deprAmortCodes)) {
              $deprAmortBudget += $item['budget'];
              $deprAmortReal += $item['realization'];
              $deprAmortProj += $item['projection'];
            }
            if ($item['key'] === $rentCode) {
              $rentBudget += $item['budget'];
              $rentReal += $item['realization'];
              $rentProj += $item['projection'];
            }
          }
        }
      }

      // EBITDA = Operating Profit + Depreciation & Amortization + Rent (net without 18% interest)
      $rentNetFactor = 0.180398820557498; // 1 - 0.18 18.0398820557498%
      $ebitdaBudget = $operatingProfitBudget + $deprAmortBudget + ($rentBudget - ($rentBudget * $rentNetFactor));
      $ebitdaReal = $operatingProfitReal + $deprAmortReal + ($rentReal * $rentNetFactor);
      $ebitdaProj = $operatingProfitProj + $deprAmortProj + ($rentProj * $rentNetFactor);

      $netProfitBudget = $operatingProfitBudget + $otherBudget - $otherBudgetExpenses;
      $netProfitReal = $operatingProfitReal + $otherReal - $otherRealExpenses;
      $netProfitProj = $operatingProfitProj + $otherProj - $otherProjExpenses;

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
          'budget' => $operatingProfitBudget,
          'realization' => $operatingProfitReal,
          'projection' => $operatingProfitProj,
        ],
        'other_income' => [
          'label' => 'Pendapatan Lain-lain (Other Income)',
          'budget' => $otherBudget,
          'realization' => $otherReal,
          'projection' => $otherProj,
        ],
        'other_expense' => [
          'label' => 'Beban Lain-lain (Other Expense)',
          'budget' => $otherBudgetExpenses,
          'realization' => $otherRealExpenses,
          'projection' => $otherProjExpenses,
        ],
        'net_profit' => [
          'label' => 'Laba Bersih (Net Profit)',
          'budget' => $netProfitBudget,
          'realization' => $netProfitReal,
          'projection' => $netProfitProj,
        ],
        'ebitda' => [
          'label' => 'EBITDA',
          'budget' => $ebitdaBudget,
          'realization' => $ebitdaReal,
          'projection' => $ebitdaProj,
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
      $incomeBudget = 0.0;
      $incomeReal = 0.0;
      $incomeProj = 0.0;
      $expenseBudget = 0.0;
      $expenseReal = 0.0;
      $expenseProj = 0.0;

      if ($period) {
        $isNextYear = ($yr === $currentYear + 1);

        $budgetItems = DB::table('rkap_budget_items')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
          ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
          ->join('report_groups', 'coa_groups.report_group_id', '=', 'report_groups.id')
          ->where('rkap_submissions.rkap_period_id', $period->id)
          ->unless($isNextYear, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->whereNull('coas.deleted_at')
          ->whereIn('coa_groups.report_group_id', $plReportGroupIds)
          ->select(
            'rkap_budget_items.id',
            'rkap_budget_items.total_price',
            'rkap_budget_items.projection',
            'coas.code as coa_code',
            'coa_groups.code as coa_group_code',
            'report_groups.code as report_group_code'
          )
          ->get();

        $realizationSums = DB::table('rkap_budget_item_realizations')
          ->where('rkap_period_id', $period->id)
          ->groupBy('rkap_budget_item_id')
          ->select('rkap_budget_item_id', DB::raw('SUM(amount) as total_amount'))
          ->pluck('total_amount', 'rkap_budget_item_id')
          ->toArray();

        foreach ($budgetItems as $item) {
          $isExpense = false;
          $rgCode = $item->report_group_code;
          $cgCode = $item->coa_group_code;
          $coaCode = $item->coa_code;

          $amountBudget = (float) $item->total_price;
          $amountReal = (float) ($realizationSums[$item->id] ?? 0.0);
          $amountProj = (float) $item->projection;

          if ($rgCode === 'PL0001') {
            $isExpense = false;
          } elseif ($rgCode === 'PL0002' || $rgCode === 'PL0003' || $rgCode === 'PL0005') {
            $isExpense = true;
          } elseif ($rgCode === 'PL0004') {
            if (in_array($cgCode, ['7000', '7001', '7001A', '7002', '7002A', '7004'])) {
              $isExpense = true;
            } elseif ($cgCode === '7005') {
              if (str_starts_with($coaCode, '76') || str_starts_with($coaCode, '79')) {
                $isExpense = true;
              } else {
                $isExpense = false;
              }
            } else {
              $isExpense = false;
            }
          }

          if ($isExpense) {
            $expenseBudget += $amountBudget;
            $expenseReal += $amountReal;
            $expenseProj += $amountProj;
          } else {
            $incomeBudget += $amountBudget;
            $incomeReal += $amountReal;
            $incomeProj += $amountProj;
          }
        }
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
        'income_budget' => $incomeBudget,
        'income_realization' => $incomeReal,
        'income_projection' => $incomeProj,
        'expense_budget' => $expenseBudget,
        'expense_realization' => $expenseReal,
        'expense_projection' => $expenseProj,
      ];
    }

    return compact(
      'activePeriod',
      'finalizedPeriods',
      'stats',
      'income_stats',
      'expense_stats',
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
    );
  }

  public function index(Request $request)
  {
    $data = $this->getAnalyticsData($request);
    return view('content.dashboard.dashboards-analytics', $data);
  }

  public function report(Request $request)
  {
    $data = $this->getAnalyticsData($request);
    return view('content.dashboard.analytics-report', $data);
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

  public function cashflow(Request $request)
  {
    $user = Auth::user();
    if (!$user) {
      abort(403);
    }

    if ($user->isKepalaBiro()) {
      abort(403, 'Anda tidak memiliki akses untuk melihat laporan ini.');
    }

    $finalizedPeriods = RkapPeriod::whereIn('status', ['finalized', 'open'])
      ->orderBy('year', 'desc')
      ->orderBy('created_at', 'desc')
      ->get();

    $selectedPeriodId = $request->query('period_id');
    $activePeriod = null;

    if ($selectedPeriodId) {
      $activePeriod = RkapPeriod::whereIn('status', ['finalized', 'open'])->find($selectedPeriodId);
    }

    if (!$activePeriod) {
      $currentYear = (int) date('Y');
      $activePeriod = RkapPeriod::where('year', $currentYear)->whereIn('status', ['finalized', 'open'])->first()
        ?? RkapPeriod::whereIn('status', ['finalized', 'open'])->latest()->first()
        ?? RkapPeriod::latest()->first();
    }

    $includeAllStatuses = ($activePeriod && $activePeriod->status !== 'finalized');

    $inflowGroups = [];
    $outflowGroups = [];
    $cfSummary = [];

    if ($activePeriod) {
      $bureauIds = null;
      if ($user->isKepalaBiro()) {
        $bureauIds = [$user->bureau_id];
      } elseif ($user->isKepalaDepartemen()) {
        $bureauIds = DB::table('bureaus')->where('department_id', $user->department_id)->pluck('id')->toArray();
      }

      $cashflowGroups = \App\Models\CashflowGroup::orderBy('code')->get();

      $cfBudgetsRaw = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->where('rkap_submissions.rkap_period_id', $activePeriod->id)
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereNotNull('coas.cashflow_group_id')
        ->selectRaw('coas.cashflow_group_id, SUM(rkap_budget_items.total_price) as total')
        ->groupBy('coas.cashflow_group_id')
        ->pluck('total', 'cashflow_group_id')
        ->toArray();

      $cfRealizationsRaw = DB::table('rkap_budget_item_realizations')
        ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->where('rkap_budget_item_realizations.rkap_period_id', $activePeriod->id)
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereNotNull('coas.cashflow_group_id')
        ->selectRaw('coas.cashflow_group_id, SUM(rkap_budget_item_realizations.amount) as total')
        ->groupBy('coas.cashflow_group_id')
        ->pluck('total', 'cashflow_group_id')
        ->toArray();

      $cfProjectionsRaw = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->where('rkap_submissions.rkap_period_id', $activePeriod->id)
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereNotNull('coas.cashflow_group_id')
        ->selectRaw('coas.cashflow_group_id, SUM(rkap_budget_items.projection) as total')
        ->groupBy('coas.cashflow_group_id')
        ->pluck('total', 'cashflow_group_id')
        ->toArray();

      $inflowBudgetTotal = 0.0;
      $inflowRealTotal = 0.0;
      $inflowProjTotal = 0.0;

      $outflowBudgetTotal = 0.0;
      $outflowRealTotal = 0.0;
      $outflowProjTotal = 0.0;

      $colorPalette = ['primary', 'info', 'success', 'warning', 'danger', 'secondary', 'dark'];

      foreach ($cashflowGroups as $idx => $cg) {
        $budget = (float) ($cfBudgetsRaw[$cg->id] ?? 0.0);
        $realization = (float) ($cfRealizationsRaw[$cg->id] ?? 0.0);
        $projection = (float) ($cfProjectionsRaw[$cg->id] ?? 0.0);

        $item = [
          'id' => $cg->id,
          'code' => $cg->code,
          'name' => $cg->name,
          'color' => $colorPalette[$idx % count($colorPalette)],
          'budget' => $budget,
          'realization' => $realization,
          'projection' => $projection,
        ];

        $isInflow = str_starts_with($cg->code, 'CF0A') || $cg->code === 'CF0B10';

        if ($isInflow) {
          $inflowGroups[] = $item;
          $inflowBudgetTotal += $budget;
          $inflowRealTotal += $realization;
          $inflowProjTotal += $projection;
        } else {
          $outflowGroups[] = $item;
          $outflowBudgetTotal += $budget;
          $outflowRealTotal += $realization;
          $outflowProjTotal += $projection;
        }
      }

      $netBudget = $inflowBudgetTotal - $outflowBudgetTotal;
      $netReal = $inflowRealTotal - $outflowRealTotal;
      $netProj = $inflowProjTotal - $outflowProjTotal;

      $cfSummary = [
        'inflow' => [
          'budget' => $inflowBudgetTotal,
          'realization' => $inflowRealTotal,
          'projection' => $inflowProjTotal,
        ],
        'outflow' => [
          'budget' => $outflowBudgetTotal,
          'realization' => $outflowRealTotal,
          'projection' => $outflowProjTotal,
        ],
        'net' => [
          'budget' => $netBudget,
          'realization' => $netReal,
          'projection' => $netProj,
        ],
      ];
    }

    return view('content.dashboard.analytics-cashflow', compact(
      'activePeriod',
      'finalizedPeriods',
      'inflowGroups',
      'outflowGroups',
      'cfSummary'
    ));
  }

  public function cashflowMatrix(Request $request)
  {
    $user = Auth::user();
    if (!$user) {
      abort(403);
    }

    if ($user->isKepalaBiro()) {
      abort(403, 'Anda tidak memiliki akses untuk melihat laporan ini.');
    }

    // Get all versions
    $versions = \App\Models\FinancialVersion::orderBy('version_id')->get();

    // Get all RKAP periods for the sync dialog (ordered by year desc so latest first)
    $rkapPeriods = RkapPeriod::orderByDesc('year')->orderByDesc('id')->get();

    // Get all categories, line items and facts
    $categories = \App\Models\CfCategory::with(['lineItems.facts'])->orderBy('category_id')->get();

    // Prepare a matrix mapping of [item_code][version_id] = amount
    $matrix = [];
    foreach ($categories as $category) {
      foreach ($category->lineItems as $item) {
        $matrix[$item->item_code] = [];
        // Pre-fill with 0 for all versions
        foreach ($versions as $version) {
          $matrix[$item->item_code][$version->version_id] = 0.0;
        }
        // Fill actual amounts from facts
        foreach ($item->facts as $fact) {
          $matrix[$item->item_code][$fact->version_id] = (float) $fact->amount;
        }
      }
    }

    // Calculate subtotals for each category per version
    $categorySubtotals = [];
    foreach ($categories as $category) {
      $categorySubtotals[$category->category_id] = [];
      foreach ($versions as $version) {
        $subtotal = 0.0;
        // Don't include category 4 (Rekonsiliasi Kas - Selisih Kurs and Saldo Awal) in standard subtotals
        if ($category->category_id <= 3) {
          foreach ($category->lineItems as $item) {
            $subtotal += $matrix[$item->item_code][$version->version_id] ?? 0.0;
          }
        }
        $categorySubtotals[$category->category_id][$version->version_id] = $subtotal;
      }
    }

    // Calculate Perubahan Kas Kas Bersih (Net Cash Flow Change) per version
    // Sum of subtotals of Category 1 + Category 2 + Category 3
    $netCashFlows = [];
    foreach ($versions as $version) {
      $netCashFlows[$version->version_id] =
        ($categorySubtotals[1][$version->version_id] ?? 0.0) +
        ($categorySubtotals[2][$version->version_id] ?? 0.0) +
        ($categorySubtotals[3][$version->version_id] ?? 0.0);
    }

    // Calculate Ending Balance (Saldo Akhir) per version
    // Saldo Akhir = Net Cash Flow + Selisih Kurs (CF0D1) + Saldo Awal (CF_BEGINNING)
    $endingBalances = [];
    foreach ($versions as $version) {
      $netCf = $netCashFlows[$version->version_id];
      $selisihKurs = $matrix['CF0D1'][$version->version_id] ?? 0.0;
      $saldoAwal = $matrix['CF_BEGINNING'][$version->version_id] ?? 0.0;
      $endingBalances[$version->version_id] = $netCf + $selisihKurs + $saldoAwal;
    }

    return view('content.dashboard.analytics-cashflow-matrix', compact(
      'versions',
      'categories',
      'matrix',
      'categorySubtotals',
      'netCashFlows',
      'endingBalances',
      'rkapPeriods'
    ));
  }

  public function cashflowGroupDetail(Request $request)
  {
    $user = Auth::user();
    if (!$user) {
      abort(403);
    }

    if ($user->isKepalaBiro()) {
      abort(403, 'Anda tidak memiliki akses untuk melihat laporan ini.');
    }

    $cashflowGroupId = (int) $request->query('cashflow_group_id');
    $periodId = (int) $request->query('period_id');

    if (!$cashflowGroupId || !$periodId) {
      return response()->json(['data' => []]);
    }

    $bureauIds = null;
    if ($user->isKepalaBiro()) {
      $bureauIds = [$user->bureau_id];
    } elseif ($user->isKepalaDepartemen()) {
      $bureauIds = DB::table('bureaus')
        ->where('department_id', $user->department_id)
        ->pluck('id')
        ->toArray();
    }

    $rows = DB::table('rkap_budget_items')
      ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
      ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
      ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
      ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
      ->join('departments', 'bureaus.department_id', '=', 'departments.id')
      ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
      ->leftJoin(DB::raw('(SELECT rkap_budget_item_id, SUM(amount) as realization_total FROM rkap_budget_item_realizations WHERE rkap_period_id = ' . $periodId . ' GROUP BY rkap_budget_item_id) as rl'), 'rl.rkap_budget_item_id', '=', 'rkap_budget_items.id')
      ->where('coas.cashflow_group_id', $cashflowGroupId)
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

  public function reconciliation(Request $request)
  {
    $user = Auth::user();
    if (!$user) {
      abort(403);
    }

    if ($user->isKepalaBiro()) {
      abort(403, 'Anda tidak memiliki akses untuk melihat laporan ini.');
    }

    $data = $this->getAnalyticsData($request);
    $activePeriod = $data['activePeriod'];
    $finalizedPeriods = $data['finalizedPeriods'];
    $plSummary = $data['plSummary'];

    $reconciliationItems = [];
    $bureauIds = null;
    if ($user->isKepalaBiro()) {
      $bureauIds = [$user->bureau_id];
    } elseif ($user->isKepalaDepartemen()) {
      $bureauIds = DB::table('bureaus')->where('department_id', $user->department_id)->pluck('id')->toArray();
    }

    if ($activePeriod) {
      $diffGroupsRaw = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('difference_groups', 'coas.difference_group_id', '=', 'difference_groups.id')
        ->where('rkap_submissions.rkap_period_id', $activePeriod->id)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->selectRaw('difference_groups.id, difference_groups.code, difference_groups.name, SUM(rkap_budget_items.total_price) as budget, SUM(rkap_budget_items.projection) as projection')
        ->groupBy('difference_groups.id', 'difference_groups.code', 'difference_groups.name')
        ->get();

      $diffRealizationsRaw = DB::table('rkap_budget_item_realizations')
        ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->where('rkap_budget_item_realizations.rkap_period_id', $activePeriod->id)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereNotNull('coas.difference_group_id')
        ->selectRaw('coas.difference_group_id, SUM(rkap_budget_item_realizations.amount) as total')
        ->groupBy('coas.difference_group_id')
        ->pluck('total', 'difference_group_id')
        ->toArray();

      foreach ($diffGroupsRaw as $dg) {
        $realization = (float) ($diffRealizationsRaw[$dg->id] ?? 0.0);
        $reconciliationItems[] = [
          'id' => $dg->id,
          'code' => $dg->code,
          'name' => $dg->name,
          'budget' => (float) $dg->budget,
          'realization' => $realization,
          'projection' => (float) $dg->projection,
        ];
      }
    }

    return view('content.dashboard.analytics-reconciliation', compact(
      'activePeriod',
      'finalizedPeriods',
      'plSummary',
      'reconciliationItems'
    ));
  }

  public function differenceGroupDetail(Request $request)
  {
    $user = Auth::user();
    if (!$user) {
      abort(403);
    }

    if ($user->isKepalaBiro()) {
      abort(403, 'Anda tidak memiliki akses untuk melihat laporan ini.');
    }

    $differenceGroupId = (int) $request->query('difference_group_id');
    $periodId = (int) $request->query('period_id');

    if (!$differenceGroupId || !$periodId) {
      return response()->json(['data' => []]);
    }

    $bureauIds = null;
    if ($user->isKepalaBiro()) {
      $bureauIds = [$user->bureau_id];
    } elseif ($user->isKepalaDepartemen()) {
      $bureauIds = DB::table('bureaus')
        ->where('department_id', $user->department_id)
        ->pluck('id')
        ->toArray();
    }

    $rows = DB::table('rkap_budget_items')
      ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
      ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
      ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
      ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
      ->join('departments', 'bureaus.department_id', '=', 'departments.id')
      ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
      ->leftJoin(DB::raw('(SELECT rkap_budget_item_id, SUM(amount) as realization_total FROM rkap_budget_item_realizations WHERE rkap_period_id = ' . $periodId . ' GROUP BY rkap_budget_item_id) as rl'), 'rl.rkap_budget_item_id', '=', 'rkap_budget_items.id')
      ->where('coas.difference_group_id', $differenceGroupId)
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
