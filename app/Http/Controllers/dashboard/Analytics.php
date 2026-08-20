<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsCacheService;
use Illuminate\Http\Request;
use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
      'revenue' => array_merge($defaultSummaryItem, ['label' => __('Total Pendapatan')]),
      'direct_cost' => array_merge($defaultSummaryItem, ['label' => __('Total Beban Langsung')]),
      'gross_profit' => array_merge($defaultSummaryItem, ['label' => __('Laba Kotor (Gross Profit)')]),
      'indirect_cost' => array_merge($defaultSummaryItem, ['label' => __('Total Beban Tidak Langsung')]),
      'operating_profit' => array_merge($defaultSummaryItem, ['label' => __('Laba (Rugi) Usaha')]),
      'other_income' => array_merge($defaultSummaryItem, ['label' => __('Pendapatan Lain-lain (Other Income)')]),
      'other_expense' => array_merge($defaultSummaryItem, ['label' => __('Beban Lain-lain (Other Expense)')]),
      'net_profit' => array_merge($defaultSummaryItem, ['label' => __('Laba Bersih (Net Profit)')]),
      'ebitda' => array_merge($defaultSummaryItem, ['label' => __('EBITDA')]),
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
          'rkap_budget_items.is_gain',
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

        if ($coaCode === '7603000001' && isset($item->is_gain) && (bool) $item->is_gain) {
          // Gain: negate so it reduces the expense bucket (adds back to net income)
          $amountBudget = -$amountBudget;
          $amountReal = -$amountReal;
          $amountProj = -$amountProj;
        }
        // Loss (is_gain=false): keep positive — adds to expense bucket (reduces net income)

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
        ->selectRaw("coa_groups.id as coa_group_id, coa_groups.code as coa_group_code, coas.code as coa_code, SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN rkap_budget_items.total_price WHEN rkap_budget_items.account_code = '7603000001' THEN -rkap_budget_items.total_price ELSE rkap_budget_items.total_price END) as total")
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
        ->selectRaw("coa_groups.id as coa_group_id, coa_groups.code as coa_group_code, coas.code as coa_code, SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN rkap_budget_item_realizations.amount WHEN rkap_budget_items.account_code = '7603000001' THEN -rkap_budget_item_realizations.amount ELSE rkap_budget_item_realizations.amount END) as total")
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
        ->selectRaw("coa_groups.id as coa_group_id, coa_groups.code as coa_group_code, coas.code as coa_code, SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN rkap_budget_items.projection WHEN rkap_budget_items.account_code = '7603000001' THEN -rkap_budget_items.projection ELSE rkap_budget_items.projection END) as total")
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

      // --- 6.1. Monthly Calculations for P&L ---
      $cgBudgetsMonthlyRaw = DB::table('rkap_budget_item_monthlies')
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
        ->selectRaw("coa_groups.id as coa_group_id, coa_groups.code as coa_group_code, coas.code as coa_code, rkap_budget_item_monthlies.month, SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN rkap_budget_item_monthlies.amount WHEN rkap_budget_items.account_code = '7603000001' THEN -rkap_budget_item_monthlies.amount ELSE rkap_budget_item_monthlies.amount END) as total")
        ->groupBy('coa_groups.id', 'coa_groups.code', 'coas.code', 'rkap_budget_item_monthlies.month')
        ->get();

      $cgRealizationsMonthlyRaw = DB::table('rkap_budget_item_realizations')
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
        ->selectRaw("coa_groups.id as coa_group_id, coa_groups.code as coa_group_code, coas.code as coa_code, rkap_budget_item_realizations.month, SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN rkap_budget_item_realizations.amount WHEN rkap_budget_items.account_code = '7603000001' THEN -rkap_budget_item_realizations.amount ELSE rkap_budget_item_realizations.amount END) as total")
        ->groupBy('coa_groups.id', 'coa_groups.code', 'coas.code', 'rkap_budget_item_realizations.month')
        ->get();

      $cgProjectionsMonthlyRaw = DB::table('rkap_budget_item_projections')
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
        ->selectRaw("coa_groups.id as coa_group_id, coa_groups.code as coa_group_code, coas.code as coa_code, rkap_budget_item_projections.month, SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN rkap_budget_item_projections.amount WHEN rkap_budget_items.account_code = '7603000001' THEN -rkap_budget_item_projections.amount ELSE rkap_budget_item_projections.amount END) as total")
        ->groupBy('coa_groups.id', 'coa_groups.code', 'coas.code', 'rkap_budget_item_projections.month')
        ->get();

      $aggregateMonthlyCgData = function ($rawItems) {
        $aggregated = [];
        foreach ($rawItems as $item) {
          $cgId = $item->coa_group_id;
          $cgCode = $item->coa_group_code;
          $coaCode = $item->coa_code;
          $month = (int) $item->month;
          $amount = (float) $item->total;

          if (!isset($aggregated[$cgId])) {
            $aggregated[$cgId] = array_fill(1, 12, 0.0);
          }

          $isOtherGroup = in_array($cgCode, ['7000', '7001', '7001A', '7002', '7002A', '7003', '7004', '7005']);

          if ($isOtherGroup) {
            if ($cgCode === '7005') {
              if (str_starts_with($coaCode, '71')) {
                $aggregated[$cgId][$month] -= $amount;
              } elseif (str_starts_with($coaCode, '76') || str_starts_with($coaCode, '79')) {
                $aggregated[$cgId][$month] += $amount;
              }
            } else {
              $aggregated[$cgId][$month] += $amount;
            }
          } else {
            $aggregated[$cgId][$month] += $amount;
          }
        }
        return $aggregated;
      };

      $cgBudgetsMonthly = $aggregateMonthlyCgData($cgBudgetsMonthlyRaw);
      $cgRealizationsMonthly = $aggregateMonthlyCgData($cgRealizationsMonthlyRaw);
      $cgProjectionsMonthly = $aggregateMonthlyCgData($cgProjectionsMonthlyRaw);

      $yearlyItemsMonthly = DB::table('rkap_budget_items')
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
        ->select(
          'rkap_budget_items.id',
          'rkap_budget_items.projection',
          'coa_groups.id as coa_group_id',
          'coa_groups.code as coa_group_code',
          'coas.code as coa_code'
        )
        ->get();

      $yearlyItemIdsMonthly = $yearlyItemsMonthly->pluck('id')->toArray();
      $monthlyPlans = [];
      if (!empty($yearlyItemIdsMonthly)) {
        $monthlyPlans = DB::table('rkap_budget_item_monthlies')
          ->whereIn('rkap_budget_item_id', $yearlyItemIdsMonthly)
          ->select('rkap_budget_item_id', 'month', 'amount')
          ->get()
          ->groupBy('rkap_budget_item_id');
      }

      foreach ($yearlyItemsMonthly as $item) {
        $itemId = $item->id;
        $cgId = $item->coa_group_id;
        $cgCode = $item->coa_group_code;
        $coaCode = $item->coa_code;
        $projectionAmount = (float) $item->projection;

        $itemPlans = isset($monthlyPlans[$itemId]) ? $monthlyPlans[$itemId] : collect();
        $totalPlanAmount = $itemPlans->sum('amount');

        if (!isset($cgProjectionsMonthly[$cgId])) {
          $cgProjectionsMonthly[$cgId] = array_fill(1, 12, 0.0);
        }

        $is7005 = ($cgCode === '7005');

        if ($totalPlanAmount > 0) {
          foreach ($itemPlans as $plan) {
            $m = (int) $plan->month;
            $alloc = $projectionAmount * ((float) $plan->amount / $totalPlanAmount);
            if ($is7005) {
              if (str_starts_with($coaCode, '71')) {
                $cgProjectionsMonthly[$cgId][$m] -= $alloc;
              } elseif (str_starts_with($coaCode, '76') || str_starts_with($coaCode, '79')) {
                $cgProjectionsMonthly[$cgId][$m] += $alloc;
              }
            } else {
              $cgProjectionsMonthly[$cgId][$m] += $alloc;
            }
          }
        } else {
          for ($m = 1; $m <= 12; $m++) {
            $alloc = $projectionAmount / 12;
            if ($is7005) {
              if (str_starts_with($coaCode, '71')) {
                $cgProjectionsMonthly[$cgId][$m] -= $alloc;
              } elseif (str_starts_with($coaCode, '76') || str_starts_with($coaCode, '79')) {
                $cgProjectionsMonthly[$cgId][$m] += $alloc;
              }
            } else {
              $cgProjectionsMonthly[$cgId][$m] += $alloc;
            }
          }
        }
      }

      // Build plGroupsMonthly structure from report_groups
      $plGroupsMonthly = [];
      $colorPalette = ['primary', 'info', 'success', 'warning', 'danger', 'secondary', 'dark'];
      $deprAmortCodes = ['5006', '6005'];
      $rentCode = '6001';

      foreach ($plReportGroups as $rg) {
        $itemsMonthly = [];
        $budgetSubtotalMonthly = array_fill(1, 12, 0.0);
        $realizationSubtotalMonthly = array_fill(1, 12, 0.0);
        $projectionSubtotalMonthly = array_fill(1, 12, 0.0);

        $hasFareboxMerged = false;
        $fareboxCg = $rg->coaGroups->first(fn($c) => $c->code === '5007' || strcasecmp($c->name, 'Kom Farebox') === 0);
        $nonFareboxCg = $rg->coaGroups->first(fn($c) => $c->code === '5008' || strcasecmp(str_replace(' ', '', $c->name), 'KomNonFarebox') === 0);

        foreach ($rg->coaGroups as $idx => $cg) {
          $budget = $cgBudgetsMonthly[$cg->id] ?? array_fill(1, 12, 0.0);
          $realization = $cgRealizationsMonthly[$cg->id] ?? array_fill(1, 12, 0.0);
          $projection = $cgProjectionsMonthly[$cg->id] ?? array_fill(1, 12, 0.0);

          for ($m = 1; $m <= 12; $m++) {
            if ($rg->code === 'PL0004') {
              $isExpenseGroup = in_array($cg->code, ['7000', '7001', '7001A', '7002', '7002A', '7004']);
              $budgetSubtotalMonthly[$m] += $isExpenseGroup ? -$budget[$m] : $budget[$m];
              $realizationSubtotalMonthly[$m] += $isExpenseGroup ? -$realization[$m] : $realization[$m];
              $projectionSubtotalMonthly[$m] += $isExpenseGroup ? -$projection[$m] : $projection[$m];
            } else {
              $budgetSubtotalMonthly[$m] += $budget[$m];
              $realizationSubtotalMonthly[$m] += $realization[$m];
              $projectionSubtotalMonthly[$m] += $projection[$m];
            }
          }

          $isFareboxItem = ($cg->code === '5007' || strcasecmp($cg->name, 'Kom Farebox') === 0);
          $isNonFareboxItem = ($cg->code === '5008' || strcasecmp(str_replace(' ', '', $cg->name), 'KomNonFarebox') === 0);

          // In Direct Cost (PL0002), merge Kom Farebox and Kom Non Farebox into Komersial
          if ($rg->code === 'PL0002' || strcasecmp($rg->name, 'Direct Cost') === 0) {
            if ($isFareboxItem || $isNonFareboxItem) {
              if ($hasFareboxMerged) {
                continue; // Skip the second item since it's already merged
              }
              $hasFareboxMerged = true;

              $mergedBudget = array_fill(1, 12, 0.0);
              $mergedRealization = array_fill(1, 12, 0.0);
              $mergedProjection = array_fill(1, 12, 0.0);
              $mergedIds = [];

              if ($fareboxCg) {
                $mergedIds[] = $fareboxCg->id;
                $fbB = $cgBudgetsMonthly[$fareboxCg->id] ?? [];
                $fbR = $cgRealizationsMonthly[$fareboxCg->id] ?? [];
                $fbP = $cgProjectionsMonthly[$fareboxCg->id] ?? [];
                for ($m = 1; $m <= 12; $m++) {
                  $mergedBudget[$m] += ($fbB[$m] ?? 0.0);
                  $mergedRealization[$m] += ($fbR[$m] ?? 0.0);
                  $mergedProjection[$m] += ($fbP[$m] ?? 0.0);
                }
              }

              if ($nonFareboxCg) {
                $mergedIds[] = $nonFareboxCg->id;
                $nfbB = $cgBudgetsMonthly[$nonFareboxCg->id] ?? [];
                $nfbR = $cgRealizationsMonthly[$nonFareboxCg->id] ?? [];
                $nfbP = $cgProjectionsMonthly[$nonFareboxCg->id] ?? [];
                for ($m = 1; $m <= 12; $m++) {
                  $mergedBudget[$m] += ($nfbB[$m] ?? 0.0);
                  $mergedRealization[$m] += ($nfbR[$m] ?? 0.0);
                  $mergedProjection[$m] += ($nfbP[$m] ?? 0.0);
                }
              }

              $itemsMonthly[] = [
                'id' => implode(',', $mergedIds),
                'key' => '5007_5008',
                'label' => 'KOMERSIAL',
                'color' => $colorPalette[$idx % count($colorPalette)],
                'budget' => $mergedBudget,
                'realization' => $mergedRealization,
                'projection' => $mergedProjection,
              ];
              continue;
            }
          }

          $itemsMonthly[] = [
            'id' => $cg->id,
            'key' => $cg->code,
            'label' => $cg->name,
            'color' => $colorPalette[$idx % count($colorPalette)],
            'budget' => $budget,
            'realization' => $realization,
            'projection' => $projection,
          ];
        }

        $plGroupsMonthly[$rg->name] = [
          'label' => $rg->name,
          'items' => $itemsMonthly,
          'budget_subtotal' => $budgetSubtotalMonthly,
          'realization_subtotal' => $realizationSubtotalMonthly,
          'projection_subtotal' => $projectionSubtotalMonthly,
        ];
      }

      $revenueBudgetMonthly = $plGroupsMonthly['Revenue']['budget_subtotal'] ?? array_fill(1, 12, 0.0);
      $revenueRealMonthly = $plGroupsMonthly['Revenue']['realization_subtotal'] ?? array_fill(1, 12, 0.0);
      $revenueProjMonthly = $plGroupsMonthly['Revenue']['projection_subtotal'] ?? array_fill(1, 12, 0.0);

      $directCostBudgetMonthly = $plGroupsMonthly['Direct Cost']['budget_subtotal'] ?? array_fill(1, 12, 0.0);
      $directCostRealMonthly = $plGroupsMonthly['Direct Cost']['realization_subtotal'] ?? array_fill(1, 12, 0.0);
      $directCostProjMonthly = $plGroupsMonthly['Direct Cost']['projection_subtotal'] ?? array_fill(1, 12, 0.0);

      $indirectCostBudgetMonthly = $plGroupsMonthly['Indirect Cost']['budget_subtotal'] ?? array_fill(1, 12, 0.0);
      $indirectCostRealMonthly = $plGroupsMonthly['Indirect Cost']['realization_subtotal'] ?? array_fill(1, 12, 0.0);
      $indirectCostProjMonthly = $plGroupsMonthly['Indirect Cost']['projection_subtotal'] ?? array_fill(1, 12, 0.0);

      $otherBudgetMonthly = $plGroupsMonthly['Other Income']['budget_subtotal'] ?? array_fill(1, 12, 0.0);
      $otherBudgetExpensesMonthly = $plGroupsMonthly['Other Expense']['budget_subtotal'] ?? array_fill(1, 12, 0.0);
      $otherRealMonthly = $plGroupsMonthly['Other Income']['realization_subtotal'] ?? array_fill(1, 12, 0.0);
      $otherRealExpensesMonthly = $plGroupsMonthly['Other Expense']['realization_subtotal'] ?? array_fill(1, 12, 0.0);
      $otherProjMonthly = $plGroupsMonthly['Other Income']['projection_subtotal'] ?? array_fill(1, 12, 0.0);
      $otherProjExpensesMonthly = $plGroupsMonthly['Other Expense']['projection_subtotal'] ?? array_fill(1, 12, 0.0);

      $deprAmortBudgetMonthly = array_fill(1, 12, 0.0);
      $deprAmortRealMonthly = array_fill(1, 12, 0.0);
      $deprAmortProjMonthly = array_fill(1, 12, 0.0);
      $rentBudgetMonthly = array_fill(1, 12, 0.0);
      $rentRealMonthly = array_fill(1, 12, 0.0);
      $rentProjMonthly = array_fill(1, 12, 0.0);

      foreach (['Direct Cost', 'Indirect Cost'] as $groupName) {
        if (isset($plGroupsMonthly[$groupName]['items'])) {
          foreach ($plGroupsMonthly[$groupName]['items'] as $item) {
            if (in_array($item['key'], $deprAmortCodes)) {
              for ($m = 1; $m <= 12; $m++) {
                $deprAmortBudgetMonthly[$m] += $item['budget'][$m];
                $deprAmortRealMonthly[$m] += $item['realization'][$m];
                $deprAmortProjMonthly[$m] += $item['projection'][$m];
              }
            }
            if ($item['key'] === $rentCode) {
              for ($m = 1; $m <= 12; $m++) {
                $rentBudgetMonthly[$m] += $item['budget'][$m];
                $rentRealMonthly[$m] += $item['realization'][$m];
                $rentProjMonthly[$m] += $item['projection'][$m];
              }
            }
          }
        }
      }

      $grossProfitBudgetMonthly = array_fill(1, 12, 0.0);
      $grossProfitRealMonthly = array_fill(1, 12, 0.0);
      $grossProfitProjMonthly = array_fill(1, 12, 0.0);
      $operatingProfitBudgetMonthly = array_fill(1, 12, 0.0);
      $operatingProfitRealMonthly = array_fill(1, 12, 0.0);
      $operatingProfitProjMonthly = array_fill(1, 12, 0.0);
      $netProfitBudgetMonthly = array_fill(1, 12, 0.0);
      $netProfitRealMonthly = array_fill(1, 12, 0.0);
      $netProfitProjMonthly = array_fill(1, 12, 0.0);
      $ebitdaBudgetMonthly = array_fill(1, 12, 0.0);
      $ebitdaRealMonthly = array_fill(1, 12, 0.0);
      $ebitdaProjMonthly = array_fill(1, 12, 0.0);

      $rentNetFactor = 0.180398820557498;

      for ($m = 1; $m <= 12; $m++) {
        $grossProfitBudgetMonthly[$m] = $revenueBudgetMonthly[$m] - $directCostBudgetMonthly[$m];
        $grossProfitRealMonthly[$m] = $revenueRealMonthly[$m] - $directCostRealMonthly[$m];
        $grossProfitProjMonthly[$m] = $revenueProjMonthly[$m] - $directCostProjMonthly[$m];

        $operatingProfitBudgetMonthly[$m] = $grossProfitBudgetMonthly[$m] - $indirectCostBudgetMonthly[$m];
        $operatingProfitRealMonthly[$m] = $grossProfitRealMonthly[$m] - $indirectCostRealMonthly[$m];
        $operatingProfitProjMonthly[$m] = $grossProfitProjMonthly[$m] - $indirectCostProjMonthly[$m];

        $ebitdaBudgetMonthly[$m] = $operatingProfitBudgetMonthly[$m] + $deprAmortBudgetMonthly[$m] + ($rentBudgetMonthly[$m] - ($rentBudgetMonthly[$m] * $rentNetFactor));
        $ebitdaRealMonthly[$m] = $operatingProfitRealMonthly[$m] + $deprAmortRealMonthly[$m] + ($rentRealMonthly[$m] * $rentNetFactor);
        $ebitdaProjMonthly[$m] = $operatingProfitProjMonthly[$m] + $deprAmortProjMonthly[$m] + ($rentProjMonthly[$m] * $rentNetFactor);

        $netProfitBudgetMonthly[$m] = $operatingProfitBudgetMonthly[$m] + $otherBudgetMonthly[$m] - $otherBudgetExpensesMonthly[$m];
        $netProfitRealMonthly[$m] = $operatingProfitRealMonthly[$m] + $otherRealMonthly[$m] - $otherRealExpensesMonthly[$m];
        $netProfitProjMonthly[$m] = $operatingProfitProjMonthly[$m] + $otherProjMonthly[$m] - $otherProjExpensesMonthly[$m];
      }

      $plSummaryMonthly = [
        'revenue' => [
          'label' => __('Total Pendapatan'),
          'budget' => $revenueBudgetMonthly,
          'realization' => $revenueRealMonthly,
          'projection' => $revenueProjMonthly,
        ],
        'direct_cost' => [
          'label' => __('Total Beban Langsung'),
          'budget' => $directCostBudgetMonthly,
          'realization' => $directCostRealMonthly,
          'projection' => $directCostProjMonthly,
        ],
        'gross_profit' => [
          'label' => __('Laba Kotor (Gross Profit)'),
          'budget' => $grossProfitBudgetMonthly,
          'realization' => $grossProfitRealMonthly,
          'projection' => $grossProfitProjMonthly,
        ],
        'indirect_cost' => [
          'label' => __('Total Beban Tidak Langsung'),
          'budget' => $indirectCostBudgetMonthly,
          'realization' => $indirectCostRealMonthly,
          'projection' => $indirectCostProjMonthly,
        ],
        'operating_profit' => [
          'label' => __('Laba (Rugi) Usaha'),
          'budget' => $operatingProfitBudgetMonthly,
          'realization' => $operatingProfitRealMonthly,
          'projection' => $operatingProfitProjMonthly,
        ],
        'other_income' => [
          'label' => __('Pendapatan Lain-lain (Other Income)'),
          'budget' => $otherBudgetMonthly,
          'realization' => $otherRealMonthly,
          'projection' => $otherProjMonthly,
        ],
        'other_expense' => [
          'label' => __('Beban Lain-lain (Other Expense)'),
          'budget' => $otherBudgetExpensesMonthly,
          'realization' => $otherRealExpensesMonthly,
          'projection' => $otherProjExpensesMonthly,
        ],
        'net_profit' => [
          'label' => __('Laba Bersih (Net Profit)'),
          'budget' => $netProfitBudgetMonthly,
          'realization' => $netProfitRealMonthly,
          'projection' => $netProfitProjMonthly,
        ],
        'ebitda' => [
          'label' => __('EBITDA'),
          'budget' => $ebitdaBudgetMonthly,
          'realization' => $ebitdaRealMonthly,
          'projection' => $ebitdaProjMonthly,
        ],
      ];

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

        $hasFareboxMerged = false;
        $fareboxCg = $rg->coaGroups->first(fn($c) => $c->code === '5007' || strcasecmp($c->name, 'Kom Farebox') === 0);
        $nonFareboxCg = $rg->coaGroups->first(fn($c) => $c->code === '5008' || strcasecmp(str_replace(' ', '', $c->name), 'KomNonFarebox') === 0);

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

          $isFareboxItem = ($cg->code === '5007' || strcasecmp($cg->name, 'Kom Farebox') === 0);
          $isNonFareboxItem = ($cg->code === '5008' || strcasecmp(str_replace(' ', '', $cg->name), 'KomNonFarebox') === 0);

          // In Direct Cost (PL0002), merge Kom Farebox and Kom Non Farebox into Komersial
          if ($rg->code === 'PL0002' || strcasecmp($rg->name, 'Direct Cost') === 0) {
            if ($isFareboxItem || $isNonFareboxItem) {
              if ($hasFareboxMerged) {
                continue; // Skip the second item since it's already merged
              }
              $hasFareboxMerged = true;

              $mergedBudget = 0.0;
              $mergedRealization = 0.0;
              $mergedProjection = 0.0;
              $mergedIds = [];

              if ($fareboxCg) {
                $mergedIds[] = $fareboxCg->id;
                $mergedBudget += (float) ($cgBudgets[$fareboxCg->id] ?? 0.0);
                $mergedRealization += (float) ($cgRealizations[$fareboxCg->id] ?? 0.0);
                $mergedProjection += (float) ($cgProjections[$fareboxCg->id] ?? 0.0);
              }

              if ($nonFareboxCg) {
                $mergedIds[] = $nonFareboxCg->id;
                $mergedBudget += (float) ($cgBudgets[$nonFareboxCg->id] ?? 0.0);
                $mergedRealization += (float) ($cgRealizations[$nonFareboxCg->id] ?? 0.0);
                $mergedProjection += (float) ($cgProjections[$nonFareboxCg->id] ?? 0.0);
              }

              $items[] = [
                'id' => implode(',', $mergedIds),
                'key' => '5007_5008',
                'label' => 'KOMERSIAL',
                'color' => $colorPalette[$idx % count($colorPalette)],
                'budget' => $mergedBudget,
                'realization' => $mergedRealization,
                'projection' => $mergedProjection,
              ];
              continue;
            }
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
          'label' => __('Total Pendapatan'),
          'budget' => $revenueBudget,
          'realization' => $revenueReal,
          'projection' => $revenueProj,
        ],
        'direct_cost' => [
          'label' => __('Total Beban Langsung'),
          'budget' => $directCostBudget,
          'realization' => $directCostReal,
          'projection' => $directCostProj,
        ],
        'gross_profit' => [
          'label' => __('Laba Kotor (Gross Profit)'),
          'budget' => $grossProfitBudget,
          'realization' => $grossProfitReal,
          'projection' => $grossProfitProj,
        ],
        'indirect_cost' => [
          'label' => __('Total Beban Tidak Langsung'),
          'budget' => $indirectCostBudget,
          'realization' => $indirectCostReal,
          'projection' => $indirectCostProj,
        ],
        'operating_profit' => [
          'label' => __('Laba (Rugi) Usaha'),
          'budget' => $operatingProfitBudget,
          'realization' => $operatingProfitReal,
          'projection' => $operatingProfitProj,
        ],
        'other_income' => [
          'label' => __('Pendapatan Lain-lain (Other Income)'),
          'budget' => $otherBudget,
          'realization' => $otherReal,
          'projection' => $otherProj,
        ],
        'other_expense' => [
          'label' => __('Beban Lain-lain (Other Expense)'),
          'budget' => $otherBudgetExpenses,
          'realization' => $otherRealExpenses,
          'projection' => $otherProjExpenses,
        ],
        'net_profit' => [
          'label' => __('Laba Bersih (Net Profit)'),
          'budget' => $netProfitBudget,
          'realization' => $netProfitReal,
          'projection' => $netProfitProj,
        ],
        'ebitda' => [
          'label' => __('EBITDA'),
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
            'rkap_budget_items.is_gain',
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

          if ($coaCode === '7603000001' && isset($item->is_gain) && (bool) $item->is_gain) {
            // Gain: negate so it reduces the expense bucket (adds back to net income)
            $amountBudget = -$amountBudget;
            $amountReal = -$amountReal;
            $amountProj = -$amountProj;
          }
          // Loss (is_gain=false): keep positive — adds to expense bucket (reduces net income)

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
      'plGroupsMonthly',
      'plSummaryMonthly',
      'unmappedGroup',
      'comparisonData'
    );
  }

  public function index(Request $request)
  {
    $user = Auth::user();
    $periodId = (int) ($request->query('period_id') ?: 0);
    $cacheKey = AnalyticsCacheService::key($periodId, $user->id, false);

    $data = Cache::remember($cacheKey, AnalyticsCacheService::TTL, function () use ($request) {
      return $this->getAnalyticsData($request);
    });

    return view('content.dashboard.dashboards-analytics', $data);
  }

  public function report(Request $request)
  {
    $user = Auth::user();
    $periodId = (int) ($request->query('period_id') ?: 0);
    $cacheKey = AnalyticsCacheService::key($periodId, $user->id, true);

    $data = Cache::remember($cacheKey, AnalyticsCacheService::TTL, function () use ($request) {
      return $this->getAnalyticsData($request);
    });

    return view('content.dashboard.analytics-report', $data);
  }

  public function coaGroupDetail(Request $request)
  {
    $user = Auth::user();
    if (!$user) {
      abort(403);
    }

    $rawCoaGroupId = $request->query('coa_group_id');
    $periodId = (int) $request->query('period_id');

    if (!$rawCoaGroupId || !$periodId) {
      return response()->json(['data' => []]);
    }

    $coaGroupIds = array_filter(array_map('intval', explode(',', (string) $rawCoaGroupId)));
    if (empty($coaGroupIds)) {
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

    // Determine whether to include all submission statuses based on the period's status
    $period = RkapPeriod::find($periodId);
    $includeAllStatuses = ($period && $period->status !== 'finalized');

    $cacheKey = AnalyticsCacheService::detailKey('coa-group', $periodId, implode('-', $coaGroupIds), $bureauIds);

    $rows = Cache::remember($cacheKey, AnalyticsCacheService::DETAIL_TTL, function () use ($periodId, $coaGroupIds, $bureauIds, $includeAllStatuses) {
      return DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->join('departments', 'bureaus.department_id', '=', 'departments.id')
        ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
        ->leftJoin('activities', 'rkap_work_plans.activity_id', '=', 'activities.id')
        ->leftJoin('work_plans', 'rkap_work_plans.work_plan_id', '=', 'work_plans.id')
        ->leftJoin(DB::raw('(SELECT rkap_budget_item_id, SUM(amount) as realization_total FROM rkap_budget_item_realizations WHERE rkap_period_id = ' . $periodId . ' GROUP BY rkap_budget_item_id) as rl'), 'rl.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->whereIn('coa_groups.id', $coaGroupIds)
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->selectRaw("
          rkap_submissions.id as submission_id,
          rkap_work_plans.id as rkap_work_plan_id,
          rkap_work_plans.work_plan_id,
          rkap_work_plans.activity_id,
          coas.code as coa_code,
          coas.title as coa_title,
          COALESCE(work_plans.code, rkap_work_plans.program_code) as program_code,
          COALESCE(work_plans.title, rkap_work_plans.program_name) as program_name,
          COALESCE(activities.code, rkap_work_plans.program_code) as activity_code,
          COALESCE(activities.title, rkap_work_plans.program_name) as activity_title,
          directorates.code as directorate_code,
          departments.code as department_code,
          bureaus.code as bureau_code,
          SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN -rkap_budget_items.total_price ELSE rkap_budget_items.total_price END) as budget,
          SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN -COALESCE(rl.realization_total, 0) ELSE COALESCE(rl.realization_total, 0) END) as realization,
          SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN -rkap_budget_items.projection ELSE rkap_budget_items.projection END) as projection
        ")
        ->groupBy(
          'rkap_submissions.id',
          'rkap_work_plans.id',
          'rkap_work_plans.work_plan_id',
          'rkap_work_plans.activity_id',
          'coas.code',
          'coas.title',
          'work_plans.code',
          'work_plans.title',
          'rkap_work_plans.program_code',
          'rkap_work_plans.program_name',
          'activities.code',
          'activities.title',
          'directorates.code',
          'departments.code',
          'bureaus.code'
        )
        ->orderBy('coas.code')
        ->orderBy('rkap_work_plans.id')
        ->get();
    });

    if ($request->query('export') === 'excel') {
      if (count($coaGroupIds) > 1) {
        $groupName = 'Komersial';
      } else {
        $coaGroup = \Illuminate\Support\Facades\DB::table('coa_groups')->find($coaGroupIds[0]);
        $groupName = $coaGroup ? $coaGroup->name : 'Detail';
      }
      return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\CoaGroupDetailExport($rows->toArray(), $groupName), 'coa_group_detail.xlsx');
    }

    return response()->json(['data' => $rows]);
  }

  public function cashflow(Request $request)
  {
    $user = Auth::user();
    if (!$user) {
      abort(403);
    }

    if ($user->isKepalaBiro()) {
      abort(403, __('Anda tidak memiliki akses untuk melihat laporan ini.'));
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
    $cfGroupsMonthly = ['inflow' => ['items' => [], 'budget_subtotal' => [], 'realization_subtotal' => [], 'projection_subtotal' => []], 'outflow' => ['items' => [], 'budget_subtotal' => [], 'realization_subtotal' => [], 'projection_subtotal' => []]];
    $cfSummaryMonthly = ['inflow' => ['budget' => [], 'realization' => [], 'projection' => []], 'outflow' => ['budget' => [], 'realization' => [], 'projection' => []], 'net' => ['budget' => [], 'realization' => [], 'projection' => []]];

    if ($activePeriod) {
      $bureauIds = null;
      if ($user->isKepalaBiro()) {
        $bureauIds = [$user->bureau_id];
      } elseif ($user->isKepalaDepartemen()) {
        $bureauIds = DB::table('bureaus')->where('department_id', $user->department_id)->pluck('id')->toArray();
      }

      $cashflowGroups = \App\Models\CashflowGroup::orderBy('code')->get();

      $cf0b9GroupId = $cashflowGroups->firstWhere('code', 'CF0B9')?->id;

      $cfBudgetsRaw = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->where('rkap_submissions.rkap_period_id', $activePeriod->id)
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereNotNull('coas.cashflow_group_id')
        ->selectRaw(
          $cf0b9GroupId
          ? "coas.cashflow_group_id, SUM(CASE WHEN coas.cashflow_group_id = {$cf0b9GroupId} THEN (CASE WHEN rkap_budget_items.flow_direction = 'OUT' THEN -rkap_budget_items.total_price ELSE rkap_budget_items.total_price END) ELSE rkap_budget_items.total_price END) as total"
          : "coas.cashflow_group_id, SUM(rkap_budget_items.total_price) as total"
        )
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
        ->selectRaw(
          $cf0b9GroupId
          ? "coas.cashflow_group_id, SUM(CASE WHEN coas.cashflow_group_id = {$cf0b9GroupId} THEN (CASE WHEN rkap_budget_items.flow_direction = 'OUT' THEN -rkap_budget_item_realizations.amount ELSE rkap_budget_item_realizations.amount END) ELSE rkap_budget_item_realizations.amount END) as total"
          : "coas.cashflow_group_id, SUM(rkap_budget_item_realizations.amount) as total"
        )
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
        ->selectRaw(
          $cf0b9GroupId
          ? "coas.cashflow_group_id, SUM(CASE WHEN coas.cashflow_group_id = {$cf0b9GroupId} THEN (CASE WHEN rkap_budget_items.flow_direction = 'OUT' THEN -rkap_budget_items.projection ELSE rkap_budget_items.projection END) ELSE rkap_budget_items.projection END) as total"
          : "coas.cashflow_group_id, SUM(rkap_budget_items.projection) as total"
        )
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
          if ($cg->code === 'CF0B9') {
            $outflowBudgetTotal -= $budget;
            $outflowRealTotal -= $realization;
            $outflowProjTotal -= $projection;
          } else {
            $outflowBudgetTotal += $budget;
            $outflowRealTotal += $realization;
            $outflowProjTotal += $projection;
          }
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

      // --- Monthly CF Calculations ---

      // 1. Monthly budget grouped by cashflow_group_id + month
      $cfBudgetsMonthlyRaw = DB::table('rkap_budget_item_monthlies')
        ->join('rkap_budget_items', 'rkap_budget_item_monthlies.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->where('rkap_submissions.rkap_period_id', $activePeriod->id)
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereNotNull('coas.cashflow_group_id')
        ->selectRaw(
          $cf0b9GroupId
          ? "coas.cashflow_group_id, rkap_budget_item_monthlies.month, SUM(CASE WHEN coas.cashflow_group_id = {$cf0b9GroupId} THEN (CASE WHEN rkap_budget_items.flow_direction = 'OUT' THEN -rkap_budget_item_monthlies.amount ELSE rkap_budget_item_monthlies.amount END) ELSE rkap_budget_item_monthlies.amount END) as total"
          : "coas.cashflow_group_id, rkap_budget_item_monthlies.month, SUM(rkap_budget_item_monthlies.amount) as total"
        )
        ->groupBy('coas.cashflow_group_id', 'rkap_budget_item_monthlies.month')
        ->get();

      // 2. Monthly realizations grouped by cashflow_group_id + month
      $cfRealizationsMonthlyRaw = DB::table('rkap_budget_item_realizations')
        ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->where('rkap_budget_item_realizations.rkap_period_id', $activePeriod->id)
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereNotNull('coas.cashflow_group_id')
        ->selectRaw(
          $cf0b9GroupId
          ? "coas.cashflow_group_id, rkap_budget_item_realizations.month, SUM(CASE WHEN coas.cashflow_group_id = {$cf0b9GroupId} THEN (CASE WHEN rkap_budget_items.flow_direction = 'OUT' THEN -rkap_budget_item_realizations.amount ELSE rkap_budget_item_realizations.amount END) ELSE rkap_budget_item_realizations.amount END) as total"
          : "coas.cashflow_group_id, rkap_budget_item_realizations.month, SUM(rkap_budget_item_realizations.amount) as total"
        )
        ->groupBy('coas.cashflow_group_id', 'rkap_budget_item_realizations.month')
        ->get();

      // 3. Monthly projections grouped by cashflow_group_id + month
      $cfProjectionsMonthlyRaw = DB::table('rkap_budget_item_projections')
        ->join('rkap_budget_items', 'rkap_budget_item_projections.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->where('rkap_submissions.rkap_period_id', $activePeriod->id)
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereNotNull('coas.cashflow_group_id')
        ->selectRaw(
          $cf0b9GroupId
          ? "coas.cashflow_group_id, rkap_budget_item_projections.month, SUM(CASE WHEN coas.cashflow_group_id = {$cf0b9GroupId} THEN (CASE WHEN rkap_budget_items.flow_direction = 'OUT' THEN -rkap_budget_item_projections.amount ELSE rkap_budget_item_projections.amount END) ELSE rkap_budget_item_projections.amount END) as total"
          : "coas.cashflow_group_id, rkap_budget_item_projections.month, SUM(rkap_budget_item_projections.amount) as total"
        )
        ->groupBy('coas.cashflow_group_id', 'rkap_budget_item_projections.month')
        ->get();

      // Aggregate into [cashflow_group_id][month] => total
      $cfBudgetsMonthly = [];
      foreach ($cfBudgetsMonthlyRaw as $row) {
        $cgId = $row->cashflow_group_id;
        $month = (int) $row->month;
        if (!isset($cfBudgetsMonthly[$cgId])) {
          $cfBudgetsMonthly[$cgId] = array_fill(1, 12, 0.0);
        }
        $cfBudgetsMonthly[$cgId][$month] += (float) $row->total;
      }

      $cfRealizationsMonthly = [];
      foreach ($cfRealizationsMonthlyRaw as $row) {
        $cgId = $row->cashflow_group_id;
        $month = (int) $row->month;
        if (!isset($cfRealizationsMonthly[$cgId])) {
          $cfRealizationsMonthly[$cgId] = array_fill(1, 12, 0.0);
        }
        $cfRealizationsMonthly[$cgId][$month] += (float) $row->total;
      }

      $cfProjectionsMonthly = [];
      foreach ($cfProjectionsMonthlyRaw as $row) {
        $cgId = $row->cashflow_group_id;
        $month = (int) $row->month;
        if (!isset($cfProjectionsMonthly[$cgId])) {
          $cfProjectionsMonthly[$cgId] = array_fill(1, 12, 0.0);
        }
        $cfProjectionsMonthly[$cgId][$month] += (float) $row->total;
      }

      // 4. Handle yearly-only projections (no monthly projection records) — proportional distribution
      $cfYearlyItems = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->where('rkap_submissions.rkap_period_id', $activePeriod->id)
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->whereNotNull('coas.cashflow_group_id')
        ->where('rkap_budget_items.projection', '>', 0)
        ->whereNotExists(function ($query) use ($activePeriod) {
          $query->select(DB::raw(1))
            ->from('rkap_budget_item_projections')
            ->whereRaw('rkap_budget_item_projections.rkap_budget_item_id = rkap_budget_items.id')
            ->where('rkap_budget_item_projections.rkap_period_id', $activePeriod->id);
        })
        ->select(
          'rkap_budget_items.id',
          'rkap_budget_items.projection',
          'rkap_budget_items.flow_direction',
          'coas.cashflow_group_id'
        )
        ->get();

      $cfYearlyItemIds = $cfYearlyItems->pluck('id')->toArray();
      $cfMonthlyPlans = [];
      if (!empty($cfYearlyItemIds)) {
        $cfMonthlyPlans = DB::table('rkap_budget_item_monthlies')
          ->whereIn('rkap_budget_item_id', $cfYearlyItemIds)
          ->select('rkap_budget_item_id', 'month', 'amount')
          ->get()
          ->groupBy('rkap_budget_item_id');
      }

      foreach ($cfYearlyItems as $item) {
        $itemId = $item->id;
        $cgId = $item->cashflow_group_id;
        $yearlyProj = (float) $item->projection;
        if ($cf0b9GroupId !== null && (int) $cgId === (int) $cf0b9GroupId) {
          if (($item->flow_direction ?? 'OUT') === 'OUT') {
            $yearlyProj = -$yearlyProj;
          }
        }

        if (!isset($cfProjectionsMonthly[$cgId])) {
          $cfProjectionsMonthly[$cgId] = array_fill(1, 12, 0.0);
        }

        $plans = $cfMonthlyPlans[$itemId] ?? collect([]);
        $planTotal = $plans->sum('amount');

        if ($planTotal > 0) {
          foreach ($plans as $plan) {
            $m = (int) $plan->month;
            $cfProjectionsMonthly[$cgId][$m] += $yearlyProj * ((float) $plan->amount / $planTotal);
          }
        } else {
          $share = $yearlyProj / 12.0;
          for ($m = 1; $m <= 12; $m++) {
            $cfProjectionsMonthly[$cgId][$m] += $share;
          }
        }
      }

      // 5. Build $cfGroupsMonthly
      $cfGroupsMonthly = [
        'inflow' => ['items' => [], 'budget_subtotal' => array_fill(1, 12, 0.0), 'realization_subtotal' => array_fill(1, 12, 0.0), 'projection_subtotal' => array_fill(1, 12, 0.0)],
        'outflow' => ['items' => [], 'budget_subtotal' => array_fill(1, 12, 0.0), 'realization_subtotal' => array_fill(1, 12, 0.0), 'projection_subtotal' => array_fill(1, 12, 0.0)],
      ];

      foreach ($cashflowGroups as $idx => $cg) {
        $budget = $cfBudgetsMonthly[$cg->id] ?? array_fill(1, 12, 0.0);
        $realization = $cfRealizationsMonthly[$cg->id] ?? array_fill(1, 12, 0.0);
        $projection = $cfProjectionsMonthly[$cg->id] ?? array_fill(1, 12, 0.0);

        $monthlyItem = [
          'id' => $cg->id,
          'code' => $cg->code,
          'name' => $cg->name,
          'color' => $colorPalette[$idx % count($colorPalette)],
          'budget' => $budget,
          'realization' => $realization,
          'projection' => $projection,
        ];

        $isInflow = str_starts_with($cg->code, 'CF0A') || $cg->code === 'CF0B10';
        $side = $isInflow ? 'inflow' : 'outflow';

        $cfGroupsMonthly[$side]['items'][] = $monthlyItem;
        for ($m = 1; $m <= 12; $m++) {
          if ($cg->code === 'CF0B9') {
            $cfGroupsMonthly[$side]['budget_subtotal'][$m] -= $budget[$m];
            $cfGroupsMonthly[$side]['realization_subtotal'][$m] -= $realization[$m];
            $cfGroupsMonthly[$side]['projection_subtotal'][$m] -= $projection[$m];
          } else {
            $cfGroupsMonthly[$side]['budget_subtotal'][$m] += $budget[$m];
            $cfGroupsMonthly[$side]['realization_subtotal'][$m] += $realization[$m];
            $cfGroupsMonthly[$side]['projection_subtotal'][$m] += $projection[$m];
          }
        }
      }

      // 6. Build $cfSummaryMonthly
      $cfSummaryMonthly = [
        'inflow' => [
          'budget' => $cfGroupsMonthly['inflow']['budget_subtotal'],
          'realization' => $cfGroupsMonthly['inflow']['realization_subtotal'],
          'projection' => $cfGroupsMonthly['inflow']['projection_subtotal'],
        ],
        'outflow' => [
          'budget' => $cfGroupsMonthly['outflow']['budget_subtotal'],
          'realization' => $cfGroupsMonthly['outflow']['realization_subtotal'],
          'projection' => $cfGroupsMonthly['outflow']['projection_subtotal'],
        ],
        'net' => ['budget' => array_fill(1, 12, 0.0), 'realization' => array_fill(1, 12, 0.0), 'projection' => array_fill(1, 12, 0.0)],
      ];
      for ($m = 1; $m <= 12; $m++) {
        $cfSummaryMonthly['net']['budget'][$m] = $cfGroupsMonthly['inflow']['budget_subtotal'][$m] - $cfGroupsMonthly['outflow']['budget_subtotal'][$m];
        $cfSummaryMonthly['net']['realization'][$m] = $cfGroupsMonthly['inflow']['realization_subtotal'][$m] - $cfGroupsMonthly['outflow']['realization_subtotal'][$m];
        $cfSummaryMonthly['net']['projection'][$m] = $cfGroupsMonthly['inflow']['projection_subtotal'][$m] - $cfGroupsMonthly['outflow']['projection_subtotal'][$m];
      }
    }

    return view('content.dashboard.analytics-cashflow', compact(
      'activePeriod',
      'finalizedPeriods',
      'inflowGroups',
      'outflowGroups',
      'cfSummary',
      'cfGroupsMonthly',
      'cfSummaryMonthly'
    ));
  }

  public function cashflowMatrix(Request $request)
  {
    $user = Auth::user();
    if (!$user) {
      abort(403);
    }

    if ($user->isKepalaBiro()) {
      abort(403, __('Anda tidak memiliki akses untuk melihat laporan ini.'));
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
      abort(403, __('Anda tidak memiliki akses untuk melihat laporan ini.'));
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

    $cacheKey = AnalyticsCacheService::detailKey('cashflow-group', $periodId, $cashflowGroupId, $bureauIds);

    $rows = Cache::remember($cacheKey, AnalyticsCacheService::DETAIL_TTL, function () use ($periodId, $cashflowGroupId, $bureauIds) {
      return DB::table('rkap_budget_items')
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
    });

    return response()->json(['data' => $rows]);
  }

  public function reconciliation(Request $request)
  {
    $user = Auth::user();
    if (!$user) {
      abort(403);
    }

    if ($user->isKepalaBiro()) {
      abort(403, __('Anda tidak memiliki akses untuk melihat laporan ini.'));
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
        ->leftJoin('difference_group_coa', function ($join) {
          $join->on('coas.id', '=', 'difference_group_coa.coa_id')
            ->whereNull('rkap_budget_items.difference_group_id');
        })
        ->join('difference_groups', function ($join) {
          $join->on('difference_groups.id', '=', DB::raw('COALESCE(rkap_budget_items.difference_group_id, difference_group_coa.difference_group_id)'));
        })
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
        ->leftJoin('difference_group_coa', function ($join) {
          $join->on('coas.id', '=', 'difference_group_coa.coa_id')
            ->whereNull('rkap_budget_items.difference_group_id');
        })
        ->where('rkap_budget_item_realizations.rkap_period_id', $activePeriod->id)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->selectRaw('COALESCE(rkap_budget_items.difference_group_id, difference_group_coa.difference_group_id) as difference_group_id, SUM(rkap_budget_item_realizations.amount) as total')
        ->groupBy(DB::raw('COALESCE(rkap_budget_items.difference_group_id, difference_group_coa.difference_group_id)'))
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
      abort(403, __('Anda tidak memiliki akses untuk melihat laporan ini.'));
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

    $cacheKey = AnalyticsCacheService::detailKey('difference-group', $periodId, $differenceGroupId, $bureauIds);

    $rows = Cache::remember($cacheKey, AnalyticsCacheService::DETAIL_TTL, function () use ($periodId, $differenceGroupId, $bureauIds) {
      return DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->leftJoin('difference_group_coa', function ($join) {
          $join->on('coas.id', '=', 'difference_group_coa.coa_id')
            ->whereNull('rkap_budget_items.difference_group_id');
        })
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->join('departments', 'bureaus.department_id', '=', 'departments.id')
        ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
        ->leftJoin(DB::raw('(SELECT rkap_budget_item_id, SUM(amount) as realization_total FROM rkap_budget_item_realizations WHERE rkap_period_id = ' . $periodId . ' GROUP BY rkap_budget_item_id) as rl'), 'rl.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->where(DB::raw('COALESCE(rkap_budget_items.difference_group_id, difference_group_coa.difference_group_id)'), $differenceGroupId)
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
    });

    return response()->json(['data' => $rows]);
  }

  public function capex(Request $request)
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

    // Get periods for dropdown selection (include finalized & open)
    $finalizedPeriods = RkapPeriod::orderBy('year', 'desc')
      ->orderBy('created_at', 'desc')
      ->get();

    // 1. Get the requested period
    $selectedPeriodId = $request->query('period_id');
    $activePeriod = null;

    if ($selectedPeriodId) {
      $activePeriod = RkapPeriod::find($selectedPeriodId);
    }

    if (!$activePeriod) {
      $activePeriod = RkapPeriod::where('year', $currentYear)->first()
        ?? RkapPeriod::latest()->first();
    }

    $stats = [
      'total_budget' => 0.0,
      'total_realization' => 0.0,
      'total_projection' => 0.0,
      'absorption_rate' => 0.0,
      'outlook_rate' => 0.0,
      'variance' => 0.0,
    ];

    $coaGroupSummary = [];
    $detailedCoas = [];

    if ($activePeriod) {
      $periodId = $activePeriod->id;
      $includeAllStatuses = $activePeriod->status !== 'finalized';

      // Caching data using AnalyticsCacheService key pattern
      $cacheKey = AnalyticsCacheService::detailKey('capex-dashboard', $periodId, 0, $bureauIds);

      $cachedData = Cache::remember($cacheKey, AnalyticsCacheService::TTL, function () use ($periodId, $bureauIds, $includeAllStatuses) {
        $capexCoaCodes = self::getCapexCoaCodes();

        // 1. Total Capex Budget
        $total_budget = (float) DB::table('rkap_budget_items')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
          ->where('rkap_submissions.rkap_period_id', $periodId)
          ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->whereNull('coas.deleted_at')
          ->whereIn('coas.code', $capexCoaCodes)
          ->sum('rkap_budget_items.total_price');

        // 2. Total Capex Realization
        $total_realization = (float) DB::table('rkap_budget_item_realizations')
          ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
          ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
          ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->whereNull('coas.deleted_at')
          ->whereIn('coas.code', $capexCoaCodes)
          ->sum('rkap_budget_item_realizations.amount');

        // 3. Total Capex Projection
        $total_projection = (float) DB::table('rkap_budget_items')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
          ->where('rkap_submissions.rkap_period_id', $periodId)
          ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->whereNull('coas.deleted_at')
          ->whereIn('coas.code', $capexCoaCodes)
          ->sum('rkap_budget_items.projection');

        $absorption_rate = $total_budget > 0
          ? round(($total_realization / $total_budget) * 100, 1)
          : 0.0;

        $outlook_rate = $total_budget > 0
          ? round(($total_projection / $total_budget) * 100, 1)
          : 0.0;

        $variance = $total_budget - $total_projection;

        // Fetch COA group sums for the list of Capex COAs
        $coaGroupDataRaw = DB::table('rkap_budget_items')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
          ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
          ->where('rkap_submissions.rkap_period_id', $periodId)
          ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->whereNull('coas.deleted_at')
          ->whereIn('coas.code', $capexCoaCodes)
          ->selectRaw('
            coa_groups.id as id,
            coa_groups.code as code,
            coa_groups.name as name,
            SUM(rkap_budget_items.total_price) as budget,
            SUM(rkap_budget_items.projection) as projection
          ')
          ->groupBy('coa_groups.id', 'coa_groups.code', 'coa_groups.name')
          ->get()
          ->keyBy('id')
          ->toArray();

        $coaGroupRealizationsRaw = DB::table('rkap_budget_item_realizations')
          ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
          ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
          ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
          ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->whereNull('coas.deleted_at')
          ->whereIn('coas.code', $capexCoaCodes)
          ->selectRaw('coa_groups.id as id, SUM(rkap_budget_item_realizations.amount) as total')
          ->groupBy('coa_groups.id')
          ->pluck('total', 'id')
          ->toArray();

        // Get all coa groups that contain our Capex COAs
        $allCoaGroups = DB::table('coas')
          ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
          ->whereIn('coas.code', $capexCoaCodes)
          ->select('coa_groups.id', 'coa_groups.code', 'coa_groups.name')
          ->distinct()
          ->get();

        $coaGroupSummary = [];
        foreach ($allCoaGroups as $cg) {
          $cgBudget = (float) (isset($coaGroupDataRaw[$cg->id]) ? $coaGroupDataRaw[$cg->id]->budget : 0.0);
          $cgProjection = (float) (isset($coaGroupDataRaw[$cg->id]) ? $coaGroupDataRaw[$cg->id]->projection : 0.0);
          $cgRealization = (float) ($coaGroupRealizationsRaw[$cg->id] ?? 0.0);

          $coaGroupSummary[] = [
            'id' => $cg->id,
            'code' => $cg->code,
            'name' => $cg->name,
            'budget' => $cgBudget,
            'realization' => $cgRealization,
            'projection' => $cgProjection,
            'variance' => $cgBudget - $cgProjection,
            'absorption_rate' => $cgBudget > 0 ? round(($cgRealization / $cgBudget) * 100, 1) : 0.0,
          ];
        }

        // Fetch list of COAs with detail counts (all of them from our user list)
        $coaList = DB::table('coas')
          ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
          ->whereIn('coas.code', $capexCoaCodes)
          ->whereNull('coas.deleted_at')
          ->select('coas.code', 'coas.title', 'coa_groups.name as group_name', 'coa_groups.id as group_id')
          ->get()
          ->keyBy('code');

        $coaDataRaw = DB::table('rkap_budget_items')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->where('rkap_submissions.rkap_period_id', $periodId)
          ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->whereIn('rkap_budget_items.account_code', $capexCoaCodes)
          ->selectRaw('
            rkap_budget_items.account_code as code,
            SUM(rkap_budget_items.total_price) as budget,
            SUM(rkap_budget_items.projection) as projection
          ')
          ->groupBy('rkap_budget_items.account_code')
          ->get()
          ->keyBy('code')
          ->toArray();

        $coaRealizationsRaw = DB::table('rkap_budget_item_realizations')
          ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
          ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
          ->whereIn('rkap_budget_items.account_code', $capexCoaCodes)
          ->selectRaw('rkap_budget_items.account_code as code, SUM(rkap_budget_item_realizations.amount) as total')
          ->groupBy('rkap_budget_items.account_code')
          ->pluck('total', 'code')
          ->toArray();

        $detailedCoas = [];
        foreach ($coaList as $code => $coa) {
          $coaBudget = (float) (isset($coaDataRaw[$code]) ? $coaDataRaw[$code]->budget : 0.0);
          $coaProjection = (float) (isset($coaDataRaw[$code]) ? $coaDataRaw[$code]->projection : 0.0);
          $coaRealization = (float) ($coaRealizationsRaw[$code] ?? 0.0);

          $detailedCoas[] = [
            'code' => $code,
            'title' => $coa->title,
            'group_name' => $coa->group_name,
            'group_id' => $coa->group_id,
            'budget' => $coaBudget,
            'realization' => $coaRealization,
            'projection' => $coaProjection,
            'variance' => $coaBudget - $coaProjection,
            'absorption_rate' => $coaBudget > 0 ? round(($coaRealization / $coaBudget) * 100, 1) : 0.0,
          ];
        }

        // Sort detailed COAs by code
        usort($detailedCoas, fn($a, $b) => strcmp($a['code'], $b['code']));

        return [
          'stats' => [
            'total_budget' => $total_budget,
            'total_realization' => $total_realization,
            'total_projection' => $total_projection,
            'absorption_rate' => $absorption_rate,
            'outlook_rate' => $outlook_rate,
            'variance' => $variance,
          ],
          'coaGroupSummary' => $coaGroupSummary,
          'detailedCoas' => $detailedCoas,
        ];
      });

      $stats = $cachedData['stats'];
      $coaGroupSummary = $cachedData['coaGroupSummary'];
      $detailedCoas = $cachedData['detailedCoas'];
    }

    return view('content.dashboard.analytics-capex', compact(
      'activePeriod',
      'finalizedPeriods',
      'stats',
      'coaGroupSummary',
      'detailedCoas'
    ));
  }

  public function coaDetail(Request $request)
  {
    $user = Auth::user();
    if (!$user) {
      abort(403);
    }

    $coaCode = $request->query('coa_code');
    $periodId = (int) $request->query('period_id');

    if (!$coaCode || !$periodId) {
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

    $period = RkapPeriod::find($periodId);
    $includeAllStatuses = ($period && $period->status !== 'finalized');

    $sanitizedCoa = (int) filter_var($coaCode, FILTER_SANITIZE_NUMBER_INT);
    $cacheKey = AnalyticsCacheService::detailKey('coa-code-detail', $periodId, $sanitizedCoa, $bureauIds);

    $rows = Cache::remember($cacheKey, AnalyticsCacheService::DETAIL_TTL, function () use ($periodId, $coaCode, $bureauIds, $includeAllStatuses) {
      return DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->join('departments', 'bureaus.department_id', '=', 'departments.id')
        ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
        ->leftJoin(DB::raw('(SELECT rkap_budget_item_id, SUM(amount) as realization_total FROM rkap_budget_item_realizations WHERE rkap_period_id = ' . $periodId . ' GROUP BY rkap_budget_item_id) as rl'), 'rl.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->where('coas.code', $coaCode)
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'))
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->whereNull('coas.deleted_at')
        ->selectRaw("
          coas.code as coa_code,
          coas.title as coa_title,
          rkap_work_plans.program_code,
          rkap_work_plans.program_name,
          directorates.code as directorate_code,
          departments.code as department_code,
          bureaus.code as bureau_code,
          SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN -rkap_budget_items.total_price ELSE rkap_budget_items.total_price END) as budget,
          SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN -COALESCE(rl.realization_total, 0) ELSE COALESCE(rl.realization_total, 0) END) as realization,
          SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN -rkap_budget_items.projection ELSE rkap_budget_items.projection END) as projection
        ")
        ->groupBy(
          'coas.code',
          'coas.title',
          'rkap_work_plans.program_code',
          'rkap_work_plans.program_name',
          'directorates.code',
          'departments.code',
          'bureaus.code'
        )
        ->orderBy('rkap_work_plans.program_code')
        ->get();
    });

    return response()->json(['data' => $rows]);
  }

  public function summaryDeptPlCapex(Request $request)
  {
    $user = Auth::user();
    if (!$user || (!$user->isAdmin() && !$user->isVerifikator())) {
      abort(403, __('Anda tidak memiliki akses untuk melihat laporan ini.'));
    }

    $finalizedPeriods = RkapPeriod::orderBy('year', 'desc')->orderBy('created_at', 'desc')->get();

    // Resolve separate period selections, default to latest active period if missing
    $currentYear = (int) date('Y');
    $defaultPeriod = RkapPeriod::where('year', $currentYear)->first() ?? RkapPeriod::latest()->first();

    $budgetPeriodId = $request->query('budget_period_id') ?: ($defaultPeriod?->id);
    $realizationPeriodId = $request->query('realization_period_id') ?: ($defaultPeriod?->id);
    $projectionPeriodId = $request->query('projection_period_id') ?: ($defaultPeriod?->id);

    $budgetPeriod = RkapPeriod::find($budgetPeriodId) ?? $defaultPeriod;
    $realizationPeriod = RkapPeriod::find($realizationPeriodId) ?? $defaultPeriod;
    $projectionPeriod = RkapPeriod::find($projectionPeriodId) ?? $defaultPeriod;

    $directorates = \App\Models\Directorate::orderBy('code')->get();
    $selectedDirectorateId = $request->query('directorate_id');

    $departmentsQuery = \App\Models\Department::with('directorate')->orderBy('code');
    if ($selectedDirectorateId) {
      $departmentsQuery->where('directorate_id', $selectedDirectorateId);
    }
    $departments = $departmentsQuery->get();

    // 1. Process P&L Data
    $plReportGroups = \App\Models\ReportGroup::where('type', 'PL')
      ->orderBy('code')
      ->get();

    $matrix = [];
    $deptTotals = [];
    $groupTotals = [];
    $grandTotal = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];

    foreach ($departments as $dept) {
      $deptTotals[$dept->id] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
    }
    foreach ($plReportGroups as $group) {
      $groupTotals[$group->id] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
      $matrix[$group->id] = [];
      foreach ($departments as $dept) {
        $matrix[$group->id][$dept->id] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
      }
    }

    if ($departments->isNotEmpty()) {
      $deptIds = $departments->pluck('id')->toArray();

      // 1.1 Query operational budgets (Budget Period)
      if ($budgetPeriod) {
        $includeAllStatusesB = ($budgetPeriod->status !== 'finalized');
        $budgetsRaw = DB::table('rkap_budget_items')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
          ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
          ->join('report_groups', 'coa_groups.report_group_id', '=', 'report_groups.id')
          ->where('rkap_submissions.rkap_period_id', $budgetPeriod->id)
          ->whereIn('bureaus.department_id', $deptIds)
          ->where('report_groups.type', 'PL')
          ->whereNull('coas.deleted_at')
          ->when(!$includeAllStatusesB, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->selectRaw("bureaus.department_id, report_groups.id as report_group_id, SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN rkap_budget_items.total_price WHEN rkap_budget_items.account_code = '7603000001' THEN -rkap_budget_items.total_price ELSE rkap_budget_items.total_price END) as budget")
          ->groupBy('bureaus.department_id', 'report_groups.id')
          ->get();

        foreach ($budgetsRaw as $b) {
          $matrix[$b->report_group_id][$b->department_id]['budget'] = (float) $b->budget;
          $deptTotals[$b->department_id]['budget'] += (float) $b->budget;
          $groupTotals[$b->report_group_id]['budget'] += (float) $b->budget;
          $grandTotal['budget'] += (float) $b->budget;
        }
      }

      // 1.2 Query operational realizations (Realization Period)
      if ($realizationPeriod) {
        $includeAllStatusesR = ($realizationPeriod->status !== 'finalized');
        $realizationsRaw = DB::table('rkap_budget_item_realizations')
          ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
          ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
          ->join('report_groups', 'coa_groups.report_group_id', '=', 'report_groups.id')
          ->where('rkap_budget_item_realizations.rkap_period_id', $realizationPeriod->id)
          ->whereIn('bureaus.department_id', $deptIds)
          ->where('report_groups.type', 'PL')
          ->whereNull('coas.deleted_at')
          ->when(!$includeAllStatusesR, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->selectRaw("bureaus.department_id, report_groups.id as report_group_id, SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN rkap_budget_item_realizations.amount WHEN rkap_budget_items.account_code = '7603000001' THEN -rkap_budget_item_realizations.amount ELSE rkap_budget_item_realizations.amount END) as realization")
          ->groupBy('bureaus.department_id', 'report_groups.id')
          ->get();

        foreach ($realizationsRaw as $r) {
          $matrix[$r->report_group_id][$r->department_id]['realization'] = (float) $r->realization;
          $deptTotals[$r->department_id]['realization'] += (float) $r->realization;
          $groupTotals[$r->report_group_id]['realization'] += (float) $r->realization;
          $grandTotal['realization'] += (float) $r->realization;
        }
      }

      // 1.3 Query operational projections (Projection Period)
      if ($projectionPeriod) {
        $includeAllStatusesP = ($projectionPeriod->status !== 'finalized');
        $projectionsRaw = DB::table('rkap_budget_items')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
          ->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
          ->join('report_groups', 'coa_groups.report_group_id', '=', 'report_groups.id')
          ->where('rkap_submissions.rkap_period_id', $projectionPeriod->id)
          ->whereIn('bureaus.department_id', $deptIds)
          ->where('report_groups.type', 'PL')
          ->whereNull('coas.deleted_at')
          ->when(!$includeAllStatusesP, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->selectRaw("bureaus.department_id, report_groups.id as report_group_id, SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN rkap_budget_items.projection WHEN rkap_budget_items.account_code = '7603000001' THEN -rkap_budget_items.projection ELSE rkap_budget_items.projection END) as projection")
          ->groupBy('bureaus.department_id', 'report_groups.id')
          ->get();

        foreach ($projectionsRaw as $p) {
          $matrix[$p->report_group_id][$p->department_id]['projection'] = (float) $p->projection;
          $deptTotals[$p->department_id]['projection'] += (float) $p->projection;
          $groupTotals[$p->report_group_id]['projection'] += (float) $p->projection;
          $grandTotal['projection'] += (float) $p->projection;
        }
      }
    }

    // 2. Process Capex Data
    $capexCoaCodes = self::getCapexCoaCodes();

    $capexMatrix = [];
    $totalStats = [
      'total_budget' => 0.0,
      'total_realization' => 0.0,
      'total_projection' => 0.0,
    ];

    foreach ($departments as $dept) {
      $capexMatrix[$dept->id] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
    }

    if ($departments->isNotEmpty()) {
      $deptIds = $departments->pluck('id')->toArray();

      // 2.1 Capex budget totals (Budget Period)
      if ($budgetPeriod) {
        $includeAllStatusesCB = ($budgetPeriod->status !== 'finalized');
        $capexBudgetsRaw = DB::table('rkap_budget_items')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->where('rkap_submissions.rkap_period_id', $budgetPeriod->id)
          ->whereIn('bureaus.department_id', $deptIds)
          ->whereIn('rkap_budget_items.account_code', $capexCoaCodes)
          ->when(!$includeAllStatusesCB, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->selectRaw('bureaus.department_id, SUM(rkap_budget_items.total_price) as budget')
          ->groupBy('bureaus.department_id')
          ->get()
          ->keyBy('department_id');
      }

      // 2.2 Capex realization totals (Realization Period)
      if ($realizationPeriod) {
        $includeAllStatusesCR = ($realizationPeriod->status !== 'finalized');
        $capexRealizationsRaw = DB::table('rkap_budget_item_realizations')
          ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->where('rkap_budget_item_realizations.rkap_period_id', $realizationPeriod->id)
          ->whereIn('bureaus.department_id', $deptIds)
          ->whereIn('rkap_budget_items.account_code', $capexCoaCodes)
          ->when(!$includeAllStatusesCR, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->selectRaw('bureaus.department_id, SUM(rkap_budget_item_realizations.amount) as realization')
          ->groupBy('bureaus.department_id')
          ->get()
          ->keyBy('department_id');
      }

      // 2.3 Capex projection totals (Projection Period)
      if ($projectionPeriod) {
        $includeAllStatusesCP = ($projectionPeriod->status !== 'finalized');
        $capexProjectionsRaw = DB::table('rkap_budget_items')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->where('rkap_submissions.rkap_period_id', $projectionPeriod->id)
          ->whereIn('bureaus.department_id', $deptIds)
          ->whereIn('rkap_budget_items.account_code', $capexCoaCodes)
          ->when(!$includeAllStatusesCP, fn($q) => $q->where('rkap_submissions.status', 'approved'))
          ->selectRaw('bureaus.department_id, SUM(rkap_budget_items.projection) as projection')
          ->groupBy('bureaus.department_id')
          ->get()
          ->keyBy('department_id');
      }

      foreach ($departments as $dept) {
        $budget = (float) (isset($capexBudgetsRaw[$dept->id]) ? $capexBudgetsRaw[$dept->id]->budget : 0.0);
        $realization = (float) (isset($capexRealizationsRaw[$dept->id]) ? $capexRealizationsRaw[$dept->id]->realization : 0.0);
        $projection = (float) (isset($capexProjectionsRaw[$dept->id]) ? $capexProjectionsRaw[$dept->id]->projection : 0.0);

        $capexMatrix[$dept->id] = [
          'budget' => $budget,
          'realization' => $realization,
          'projection' => $projection,
        ];

        $totalStats['total_budget'] += $budget;
        $totalStats['total_realization'] += $realization;
        $totalStats['total_projection'] += $projection;
      }
    }

    return view('content.dashboard.analytics-summary-dept-pl-capex', compact(
      'budgetPeriod',
      'realizationPeriod',
      'projectionPeriod',
      'finalizedPeriods',
      'directorates',
      'selectedDirectorateId',
      'departments',
      'plReportGroups',
      'matrix',
      'deptTotals',
      'groupTotals',
      'grandTotal',
      'capexMatrix',
      'totalStats'
    ));
  }

  public function summaryDeptDetail(Request $request)
  {
    $user = Auth::user();
    if (!$user) {
      abort(403);
    }

    $periodId = (int) $request->query('period_id');
    $deptId = (int) $request->query('department_id');
    $reportGroupId = $request->query('report_group_id'); // Can be numeric ID or 'capex'

    if (!$periodId || !$deptId) {
      return response()->json(['data' => []]);
    }

    // Determine whether to include all submission statuses based on the period's status
    $period = RkapPeriod::find($periodId);
    $includeAllStatuses = ($period && $period->status !== 'finalized');

    $isCapex = ($reportGroupId === 'capex');

    $query = DB::table('rkap_budget_items')
      ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
      ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
      ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
      ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
      ->join('departments', 'bureaus.department_id', '=', 'departments.id')
      ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
      ->leftJoin(DB::raw('(SELECT rkap_budget_item_id, SUM(amount) as realization_total FROM rkap_budget_item_realizations WHERE rkap_period_id = ' . $periodId . ' GROUP BY rkap_budget_item_id) as rl'), 'rl.rkap_budget_item_id', '=', 'rkap_budget_items.id')
      ->where('rkap_submissions.rkap_period_id', $periodId)
      ->where('bureaus.department_id', $deptId)
      ->whereNull('coas.deleted_at')
      ->when(!$includeAllStatuses, fn($q) => $q->where('rkap_submissions.status', 'approved'));

    if ($isCapex) {
      $capexCoaCodes = self::getCapexCoaCodes();
      $query->whereIn('rkap_budget_items.account_code', $capexCoaCodes);
    } else {
      $query->join('coa_groups', 'coas.coa_group_id', '=', 'coa_groups.id')
        ->join('report_groups', 'coa_groups.report_group_id', '=', 'report_groups.id')
        ->where('report_groups.type', 'PL')
        ->where('report_groups.id', (int) $reportGroupId);
    }

    $rows = $query->selectRaw("
        coas.code as coa_code,
        coas.title as coa_title,
        rkap_work_plans.program_code,
        rkap_work_plans.program_name,
        directorates.code as directorate_code,
        departments.code as department_code,
        bureaus.code as bureau_code,
        SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN -rkap_budget_items.total_price ELSE rkap_budget_items.total_price END) as budget,
        SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN -COALESCE(rl.realization_total, 0) ELSE COALESCE(rl.realization_total, 0) END) as realization,
        SUM(CASE WHEN rkap_budget_items.account_code = '7603000001' AND rkap_budget_items.is_gain = false THEN -rkap_budget_items.projection ELSE rkap_budget_items.projection END) as projection
      ")
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

  public function summaryDeptCashflow(Request $request)
  {
    $user = Auth::user();
    if (!$user || (!$user->isAdmin() && !$user->isVerifikator())) {
      abort(403, __('Anda tidak memiliki akses untuk melihat laporan ini.'));
    }

    $finalizedPeriods = RkapPeriod::whereIn('status', ['finalized', 'open'])->orderBy('year', 'desc')->get();
    $selectedPeriodId = $request->query('period_id');
    $activePeriod = null;

    if ($selectedPeriodId) {
      $activePeriod = RkapPeriod::whereIn('status', ['finalized', 'open'])->find($selectedPeriodId);
    }
    if (!$activePeriod) {
      $currentYear = (int) date('Y');
      $activePeriod = RkapPeriod::where('year', $currentYear)->whereIn('status', ['finalized', 'open'])->first()
        ?? RkapPeriod::whereIn('status', ['finalized', 'open'])->latest()->first();
    }

    $directorates = \App\Models\Directorate::orderBy('code')->get();
    $selectedDirectorateId = $request->query('directorate_id');

    $departmentsQuery = \App\Models\Department::with('directorate')->orderBy('code');
    if ($selectedDirectorateId) {
      $departmentsQuery->where('directorate_id', $selectedDirectorateId);
    }
    $departments = $departmentsQuery->get();

    // Load cashflow groups with their report group (category: Operasi / Investasi / Pendanaan)
    $cashflowGroups = \App\Models\CashflowGroup::with('reportGroup')->orderBy('code')->get();

    // FIX: cashflow_groups has no 'type' column.
    // Inflow/Outflow is determined by coas.cf_type = 'CASH IN' or 'CASH OUT'.
    $cfTypeMap = DB::table('coas')
      ->whereNotNull('cashflow_group_id')
      ->whereNull('deleted_at')
      ->selectRaw('cashflow_group_id, cf_type, COUNT(*) as cnt')
      ->groupBy('cashflow_group_id', 'cf_type')
      ->get()
      ->groupBy('cashflow_group_id');

    // Classify each cashflow group as inflow or outflow based on dominant COA cf_type
    $cgTypeMap = []; // cashflow_group_id => 'inflow' | 'outflow'
    foreach ($cfTypeMap as $cgId => $rows) {
      $cashIn = $rows->where('cf_type', 'CASH IN')->sum('cnt');
      $cashOut = $rows->where('cf_type', 'CASH OUT')->sum('cnt');
      $cgTypeMap[$cgId] = ($cashIn >= $cashOut) ? 'inflow' : 'outflow';
    }

    $inflowGroups = $cashflowGroups->filter(fn($g) => ($cgTypeMap[$g->id] ?? 'outflow') === 'inflow');
    $outflowGroups = $cashflowGroups->filter(fn($g) => ($cgTypeMap[$g->id] ?? 'outflow') === 'outflow');

    $matrix = [];
    $deptTotals = [];
    $groupTotals = [];

    foreach ($departments as $dept) {
      $deptTotals[$dept->id] = [
        'inflow_budget' => 0.0,
        'inflow_realization' => 0.0,
        'inflow_projection' => 0.0,
        'outflow_budget' => 0.0,
        'outflow_realization' => 0.0,
        'outflow_projection' => 0.0,
        'net_budget' => 0.0,
        'net_realization' => 0.0,
        'net_projection' => 0.0,
      ];
    }
    foreach ($cashflowGroups as $group) {
      $groupTotals[$group->id] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
    }

    if ($activePeriod && $departments->isNotEmpty()) {
      $periodId = $activePeriod->id;
      $deptIds = $departments->pluck('id')->toArray();

      $budgetsRaw = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('cashflow_groups', 'coas.cashflow_group_id', '=', 'cashflow_groups.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->whereIn('bureaus.department_id', $deptIds)
        ->whereNull('coas.deleted_at')
        ->whereIn('coas.cf_type', ['CASH IN', 'CASH OUT'])
        ->selectRaw('bureaus.department_id, cashflow_groups.id as cashflow_group_id, coas.cf_type, SUM(rkap_budget_items.total_price) as budget, SUM(rkap_budget_items.projection) as projection')
        ->groupBy('bureaus.department_id', 'cashflow_groups.id', 'coas.cf_type')
        ->get();

      $realizationsRaw = DB::table('rkap_budget_item_realizations')
        ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
        ->join('cashflow_groups', 'coas.cashflow_group_id', '=', 'cashflow_groups.id')
        ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
        ->whereIn('bureaus.department_id', $deptIds)
        ->whereNull('coas.deleted_at')
        ->whereIn('coas.cf_type', ['CASH IN', 'CASH OUT'])
        ->selectRaw('bureaus.department_id, cashflow_groups.id as cashflow_group_id, SUM(rkap_budget_item_realizations.amount) as realization')
        ->groupBy('bureaus.department_id', 'cashflow_groups.id')
        ->get();

      $realMap = [];
      foreach ($realizationsRaw as $r) {
        $realMap[$r->department_id . '_' . $r->cashflow_group_id] = (float) $r->realization;
      }

      foreach ($budgetsRaw as $b) {
        $deptId = $b->department_id;
        $cgId = $b->cashflow_group_id;
        $cfType = $b->cf_type; // 'CASH IN' or 'CASH OUT'
        $isInflow = $cfType === 'CASH IN';
        $budget = (float) $b->budget;
        $projection = (float) $b->projection;
        $realization = $realMap[$deptId . '_' . $cgId] ?? 0.0;

        // Matrix cell: accumulate per (group, dept)
        if (!isset($matrix[$cgId][$deptId])) {
          $matrix[$cgId][$deptId] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
        }
        $matrix[$cgId][$deptId]['budget'] += $budget;
        $matrix[$cgId][$deptId]['realization'] += $realization;
        $matrix[$cgId][$deptId]['projection'] += $projection;

        if (isset($groupTotals[$cgId])) {
          $groupTotals[$cgId]['budget'] += $budget;
          $groupTotals[$cgId]['realization'] += $realization;
          $groupTotals[$cgId]['projection'] += $projection;
        }

        if (isset($deptTotals[$deptId])) {
          if ($isInflow) {
            $deptTotals[$deptId]['inflow_budget'] += $budget;
            $deptTotals[$deptId]['inflow_realization'] += $realization;
            $deptTotals[$deptId]['inflow_projection'] += $projection;
          } else {
            $deptTotals[$deptId]['outflow_budget'] += $budget;
            $deptTotals[$deptId]['outflow_realization'] += $realization;
            $deptTotals[$deptId]['outflow_projection'] += $projection;
          }

          $deptTotals[$deptId]['net_budget'] = $deptTotals[$deptId]['inflow_budget'] - $deptTotals[$deptId]['outflow_budget'];
          $deptTotals[$deptId]['net_realization'] = $deptTotals[$deptId]['inflow_realization'] - $deptTotals[$deptId]['outflow_realization'];
          $deptTotals[$deptId]['net_projection'] = $deptTotals[$deptId]['inflow_projection'] - $deptTotals[$deptId]['outflow_projection'];
        }
      }
    }

    // -------------------------------------------------------------------
    // Build categorised Cashflow Report (Laporan Arus Kas tab)
    // Category = report_groups where type = 'CF'
    // -------------------------------------------------------------------
    $cfReportGroups = \App\Models\ReportGroup::where('type', 'CF')->orderBy('id')->get();

    $reportData = [];
    foreach ($cfReportGroups as $rg) {
      $reportData[$rg->id] = [
        'label' => $rg->name,
        'rows' => [],
        'subtotal' => ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0],
      ];
    }

    foreach ($cashflowGroups as $cg) {
      $rgId = $cg->report_group_id;
      if (!$rgId || !isset($reportData[$rgId]))
        continue;

      $gTot = $groupTotals[$cg->id] ?? ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
      $isInflow = ($cgTypeMap[$cg->id] ?? 'outflow') === 'inflow';
      $sign = $isInflow ? 1 : -1;

      $reportData[$rgId]['rows'][] = [
        'code' => $cg->code,
        'name' => $cg->name,
        'is_inflow' => $isInflow,
        'budget' => $gTot['budget'],
        'realization' => $gTot['realization'],
        'projection' => $gTot['projection'],
      ];

      // Subtotal: inflow adds, outflow subtracts
      $reportData[$rgId]['subtotal']['budget'] += $sign * $gTot['budget'];
      $reportData[$rgId]['subtotal']['realization'] += $sign * $gTot['realization'];
      $reportData[$rgId]['subtotal']['projection'] += $sign * $gTot['projection'];
    }

    // Grand total kenaikan/penurunan netto kas
    $grandTotal = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
    foreach ($reportData as $section) {
      $grandTotal['budget'] += $section['subtotal']['budget'];
      $grandTotal['realization'] += $section['subtotal']['realization'];
      $grandTotal['projection'] += $section['subtotal']['projection'];
    }

    return view('content.dashboard.analytics-summary-dept-cashflow', compact(
      'activePeriod',
      'finalizedPeriods',
      'directorates',
      'selectedDirectorateId',
      'departments',
      'inflowGroups',
      'outflowGroups',
      'matrix',
      'deptTotals',
      'groupTotals',
      'reportData',
      'grandTotal'
    ));
  }

  public function summaryDeptCapex(Request $request)
  {
    $user = Auth::user();
    if (!$user || (!$user->isAdmin() && !$user->isVerifikator())) {
      abort(403, __('Anda tidak memiliki akses untuk melihat laporan ini.'));
    }

    $finalizedPeriods = RkapPeriod::orderBy('year', 'desc')->orderBy('created_at', 'desc')->get();
    $selectedPeriodId = $request->query('period_id');
    $activePeriod = null;

    if ($selectedPeriodId) {
      $activePeriod = RkapPeriod::find($selectedPeriodId);
    }
    if (!$activePeriod) {
      $currentYear = (int) date('Y');
      $activePeriod = RkapPeriod::where('year', $currentYear)->first() ?? RkapPeriod::latest()->first();
    }

    $directorates = \App\Models\Directorate::orderBy('code')->get();
    $selectedDirectorateId = $request->query('directorate_id');

    $departmentsQuery = \App\Models\Department::with('directorate')->orderBy('code');
    if ($selectedDirectorateId) {
      $departmentsQuery->where('directorate_id', $selectedDirectorateId);
    }
    $departments = $departmentsQuery->get();

    $capexCoaCodes = self::getCapexCoaCodes();

    $deptCapexData = [];
    $totalStats = [
      'total_budget' => 0.0,
      'total_realization' => 0.0,
      'total_projection' => 0.0,
      'absorption_rate' => 0.0,
    ];

    if ($activePeriod && $departments->isNotEmpty()) {
      $periodId = $activePeriod->id;
      $deptIds = $departments->pluck('id')->toArray();

      $budgetsRaw = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->whereIn('bureaus.department_id', $deptIds)
        ->whereIn('rkap_budget_items.account_code', $capexCoaCodes)
        ->selectRaw('bureaus.department_id, SUM(rkap_budget_items.total_price) as budget, SUM(rkap_budget_items.projection) as projection')
        ->groupBy('bureaus.department_id')
        ->get()
        ->keyBy('department_id');

      $realizationsRaw = DB::table('rkap_budget_item_realizations')
        ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
        ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
        ->whereIn('bureaus.department_id', $deptIds)
        ->whereIn('rkap_budget_items.account_code', $capexCoaCodes)
        ->selectRaw('bureaus.department_id, SUM(rkap_budget_item_realizations.amount) as realization')
        ->groupBy('bureaus.department_id')
        ->get()
        ->keyBy('department_id');

      foreach ($departments as $dept) {
        $budget = (float) (isset($budgetsRaw[$dept->id]) ? $budgetsRaw[$dept->id]->budget : 0.0);
        $projection = (float) (isset($budgetsRaw[$dept->id]) ? $budgetsRaw[$dept->id]->projection : 0.0);
        $realization = (float) (isset($realizationsRaw[$dept->id]) ? $realizationsRaw[$dept->id]->realization : 0.0);
        $absorption = $budget > 0 ? round(($realization / $budget) * 100, 1) : 0.0;

        $deptCapexData[] = [
          'department' => $dept,
          'budget' => $budget,
          'realization' => $realization,
          'projection' => $projection,
          'variance' => $budget - $projection,
          'absorption_rate' => $absorption,
        ];

        $totalStats['total_budget'] += $budget;
        $totalStats['total_realization'] += $realization;
        $totalStats['total_projection'] += $projection;
      }

      $totalStats['absorption_rate'] = $totalStats['total_budget'] > 0
        ? round(($totalStats['total_realization'] / $totalStats['total_budget']) * 100, 1)
        : 0.0;
    }

    return view('content.dashboard.analytics-summary-dept-capex', compact(
      'activePeriod',
      'finalizedPeriods',
      'directorates',
      'selectedDirectorateId',
      'deptCapexData',
      'totalStats'
    ));
  }

  public static function getCapexCoaCodes(): array
  {
    return [
      '1105000001',
      '1201010001',
      '1201020001',
      '1201030001',
      '1201040001',
      '1201050001',
      '1201060001',
      '1201070001',
      '1201080001',
      '1201090001',
      '1201100001',
      '1201990001',
      '1201999999',
      '1203010001',
      '1203010101',
      '1203010201',
      '1203010202',
      '1203010203',
      '1203010204',
      '1203010205',
      '1203010206',
      '1203010207',
      '1203010299',
      '1203010301',
      '1203010302',
      '1203010303',
      '1203010304',
      '1203010399',
      '1203010401',
      '1203010402',
      '1203010403',
      '1203010501',
      '1203010601',
      '1203010701',
      '1203019901',
      '1203020001',
      '1203020101',
      '1203020201',
      '1203020202',
      '1203020299',
      '1203020301',
      '1203020302',
      '1203020303',
      '1203020399',
      '1203020401',
      '1203020402',
      '1203020403',
      '1203029901',
      '1203030101',
      '1203030201',
      '1203030301',
      '1203030399',
      '1203030401',
      '1203030402',
      '1203040301',
      '1203030403',
      '1203030501',
      '1203030502',
      '1203030503',
      '1203030504',
      '1203030505',
      '1203030506',
      '1203040101',
      '1203040102',
      '1203040103',
      '1203040201',
      '1203040302',
      '1203049901',
      '1204000001',
      '1204000002',
      '1204000003',
      '1204000004',
      '1206000001',
      '1299000001'
    ];
  }
}
