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

    $monthlyBudgetData = array_fill(1, 12, 0.0);
    $monthlyRealizationData = array_fill(1, 12, 0.0);
    $monthlyProjectionData = array_fill(1, 12, 0.0);

    $absorptionData = [];
    $coaData = [];

    if ($activePeriod) {
      $periodId = $activePeriod->id;

      // Define scoped bureau IDs based on user role
      $bureauIds = null;
      if ($user->isKepalaBiro()) {
        $bureauIds = [$user->bureau_id];
      } elseif ($user->isKepalaDepartemen()) {
        $bureauIds = DB::table('bureaus')->where('department_id', $user->department_id)->pluck('id')->toArray();
      } elseif ($user->isDireksi()) {
        $bureauIds = DB::table('bureaus')
          ->join('departments', 'bureaus.department_id', '=', 'departments.id')
          ->where('departments.directorate_id', $user->directorate_id)
          ->pluck('bureaus.id')
          ->toArray();
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

      // --- 4. Grouped Division Comparison for absorption Bar chart ---
      if ($user->isAdmin() || $user->isVerifikator()) {
        // Group by Directorate
        $budgets = DB::table('rkap_submissions')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->join('departments', 'bureaus.department_id', '=', 'departments.id')
          ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
          ->where('rkap_submissions.rkap_period_id', $periodId)
          ->where('rkap_submissions.status', 'approved')
          ->selectRaw('directorates.name as label, SUM(rkap_submissions.total_budget) as budget')
          ->groupBy('directorates.name')
          ->get()
          ->keyBy('label')
          ->toArray();

        $realizations = DB::table('rkap_budget_item_realizations')
          ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->join('departments', 'bureaus.department_id', '=', 'departments.id')
          ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
          ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
          ->where('rkap_submissions.status', 'approved')
          ->selectRaw('directorates.name as label, SUM(rkap_budget_item_realizations.amount) as amount')
          ->groupBy('directorates.name')
          ->get()
          ->keyBy('label')
          ->toArray();

        $projections = DB::table('rkap_budget_item_projections')
          ->join('rkap_budget_items', 'rkap_budget_item_projections.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->join('departments', 'bureaus.department_id', '=', 'departments.id')
          ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
          ->where('rkap_budget_item_projections.rkap_period_id', $periodId)
          ->where('rkap_submissions.status', 'approved')
          ->selectRaw('directorates.name as label, SUM(rkap_budget_item_projections.amount) as amount')
          ->groupBy('directorates.name')
          ->get()
          ->keyBy('label')
          ->toArray();
      } elseif ($user->isDireksi()) {
        // Group by Department
        $budgets = DB::table('rkap_submissions')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->join('departments', 'bureaus.department_id', '=', 'departments.id')
          ->where('rkap_submissions.rkap_period_id', $periodId)
          ->where('rkap_submissions.status', 'approved')
          ->where('departments.directorate_id', $user->directorate_id)
          ->selectRaw('departments.name as label, SUM(rkap_submissions.total_budget) as budget')
          ->groupBy('departments.name')
          ->get()
          ->keyBy('label')
          ->toArray();

        $realizations = DB::table('rkap_budget_item_realizations')
          ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->join('departments', 'bureaus.department_id', '=', 'departments.id')
          ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
          ->where('rkap_submissions.status', 'approved')
          ->where('departments.directorate_id', $user->directorate_id)
          ->selectRaw('departments.name as label, SUM(rkap_budget_item_realizations.amount) as amount')
          ->groupBy('departments.name')
          ->get()
          ->keyBy('label')
          ->toArray();

        $projections = DB::table('rkap_budget_item_projections')
          ->join('rkap_budget_items', 'rkap_budget_item_projections.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->join('departments', 'bureaus.department_id', '=', 'departments.id')
          ->where('rkap_budget_item_projections.rkap_period_id', $periodId)
          ->where('rkap_submissions.status', 'approved')
          ->where('departments.directorate_id', $user->directorate_id)
          ->selectRaw('departments.name as label, SUM(rkap_budget_item_projections.amount) as amount')
          ->groupBy('departments.name')
          ->get()
          ->keyBy('label')
          ->toArray();
      } else {
        // Group by Bureau
        $deptId = $user->isKepalaDepartemen() ? $user->department_id : $user->bureau?->department_id;

        $budgets = DB::table('rkap_submissions')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->where('rkap_submissions.rkap_period_id', $periodId)
          ->where('rkap_submissions.status', 'approved')
          ->when($deptId, fn($q) => $q->where('bureaus.department_id', $deptId))
          ->when($user->isKepalaBiro(), fn($q) => $q->where('bureaus.id', $user->bureau_id))
          ->selectRaw('bureaus.name as label, SUM(rkap_submissions.total_budget) as budget')
          ->groupBy('bureaus.name')
          ->get()
          ->keyBy('label')
          ->toArray();

        $realizations = DB::table('rkap_budget_item_realizations')
          ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
          ->where('rkap_submissions.status', 'approved')
          ->when($deptId, fn($q) => $q->where('bureaus.department_id', $deptId))
          ->when($user->isKepalaBiro(), fn($q) => $q->where('bureaus.id', $user->bureau_id))
          ->selectRaw('bureaus.name as label, SUM(rkap_budget_item_realizations.amount) as amount')
          ->groupBy('bureaus.name')
          ->get()
          ->keyBy('label')
          ->toArray();

        $projections = DB::table('rkap_budget_item_projections')
          ->join('rkap_budget_items', 'rkap_budget_item_projections.rkap_budget_item_id', '=', 'rkap_budget_items.id')
          ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
          ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
          ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
          ->where('rkap_budget_item_projections.rkap_period_id', $periodId)
          ->where('rkap_submissions.status', 'approved')
          ->when($deptId, fn($q) => $q->where('bureaus.department_id', $deptId))
          ->when($user->isKepalaBiro(), fn($q) => $q->where('bureaus.id', $user->bureau_id))
          ->selectRaw('bureaus.name as label, SUM(rkap_budget_item_projections.amount) as amount')
          ->groupBy('bureaus.name')
          ->get()
          ->keyBy('label')
          ->toArray();
      }

      $allLabels = array_unique(array_merge(
        array_keys($budgets),
        array_keys($realizations),
        array_keys($projections)
      ));

      foreach ($allLabels as $lbl) {
        $absorptionData[] = [
          'label'       => $lbl,
          'budget'      => isset($budgets[$lbl]) ? (float) $budgets[$lbl]->budget : 0.0,
          'realization' => isset($realizations[$lbl]) ? (float) $realizations[$lbl]->amount : 0.0,
          'projection'  => isset($projections[$lbl]) ? (float) $projections[$lbl]->amount : 0.0,
        ];
      }

      // --- 5. COA Category breakdown in PHP (database dialect safe) ---
      $items = DB::table('rkap_budget_items')
        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
        ->where('rkap_submissions.rkap_period_id', $periodId)
        ->where('rkap_submissions.status', 'approved')
        ->when($bureauIds, fn($q) => $q->whereIn('rkap_submissions.bureau_id', $bureauIds))
        ->selectRaw('rkap_budget_items.account_code, SUM(rkap_budget_items.quantity * rkap_budget_items.unit_price) as total')
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
      'absorptionData',
      'coaData'
    ));
  }
}

