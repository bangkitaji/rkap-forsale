<?php

namespace App\Livewire\Analytics;

use Livewire\Component;
use App\Models\CdsGroup;
use App\Models\RkapPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CdsReport extends Component
{
    public $selectedPeriodId = null;
    public $selectedMonth = null;

    public function mount()
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $activePeriod = RkapPeriod::where('status', 'finalized')->latest('year')->first()
            ?? RkapPeriod::latest()->first();

        if ($activePeriod) {
            $this->selectedPeriodId = $activePeriod->id;
        }
    }

    public function render()
    {
        $periods = RkapPeriod::orderBy('year', 'desc')->get();
        $cdsGroups = CdsGroup::with(['coaGroups', 'cashflowGroups'])->orderBy('code')->get();

        $reportData = [];
        $grandTotalBudget = 0;
        $grandTotalRealization = 0;
        $grandTotalProjection = 0;

        if ($this->selectedPeriodId) {
            $periodId = $this->selectedPeriodId;
            $monthFilter = $this->selectedMonth;

            foreach ($cdsGroups as $group) {
                // Collect COA IDs mapped to this CDS Group directly or via Cashflow Group
                $coaGroupIds = $group->coaGroups->pluck('id')->toArray();
                $cashflowGroupIds = $group->cashflowGroups->pluck('id')->toArray();

                $coaCodes = DB::table('coas')
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($coaGroupIds, $cashflowGroupIds) {
                        if (!empty($coaGroupIds)) {
                            $q->whereIn('coa_group_id', $coaGroupIds);
                        }
                        if (!empty($cashflowGroupIds)) {
                            $q->orWhereIn('cashflow_group_id', $cashflowGroupIds);
                        }
                    })
                    ->pluck('code')
                    ->toArray();

                $budget = 0;
                $realization = 0;
                $projection = 0;

                $detailCoaGroups = [];

                if (!empty($coaCodes)) {
                    // Calculate Budget
                    $budget = (float) DB::table('rkap_budget_items')
                        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
                        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
                        ->where('rkap_submissions.rkap_period_id', $periodId)
                        ->whereIn('rkap_budget_items.account_code', $coaCodes)
                        ->sum('rkap_budget_items.total_price');

                    // Calculate Realization
                    $realizationQuery = DB::table('rkap_budget_item_realizations')
                        ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
                        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
                        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
                        ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
                        ->whereIn('rkap_budget_items.account_code', $coaCodes);

                    if ($monthFilter) {
                        $realizationQuery->where('rkap_budget_item_realizations.month', $monthFilter);
                    }

                    $realization = (float) $realizationQuery->sum('rkap_budget_item_realizations.amount');

                    // Calculate Projection
                    $projection = (float) DB::table('rkap_budget_items')
                        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
                        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
                        ->where('rkap_submissions.rkap_period_id', $periodId)
                        ->whereIn('rkap_budget_items.account_code', $coaCodes)
                        ->sum('rkap_budget_items.projection');
                }

                // Details for breakdown view inside accordion
                foreach ($group->coaGroups as $cg) {
                    $cgCoaCodes = DB::table('coas')->where('coa_group_id', $cg->id)->whereNull('deleted_at')->pluck('code')->toArray();
                    $cgBudget = !empty($cgCoaCodes) ? (float) DB::table('rkap_budget_items')
                        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
                        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
                        ->where('rkap_submissions.rkap_period_id', $periodId)
                        ->whereIn('rkap_budget_items.account_code', $cgCoaCodes)
                        ->sum('rkap_budget_items.total_price') : 0;

                    $detailCoaGroups[] = [
                        'type' => 'COA Group',
                        'code' => $cg->code,
                        'name' => $cg->name,
                        'budget' => $cgBudget
                    ];
                }

                foreach ($group->cashflowGroups as $cfg) {
                    $cfgCoaCodes = DB::table('coas')->where('cashflow_group_id', $cfg->id)->whereNull('deleted_at')->pluck('code')->toArray();
                    $cfgBudget = !empty($cfgCoaCodes) ? (float) DB::table('rkap_budget_items')
                        ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
                        ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
                        ->where('rkap_submissions.rkap_period_id', $periodId)
                        ->whereIn('rkap_budget_items.account_code', $cfgCoaCodes)
                        ->sum('rkap_budget_items.total_price') : 0;

                    $detailCoaGroups[] = [
                        'type' => 'Cash Flow Group',
                        'code' => $cfg->code,
                        'name' => $cfg->name,
                        'budget' => $cfgBudget
                    ];
                }

                $absorptionRate = $budget > 0 ? round(($realization / $budget) * 100, 1) : 0;
                $outlookRate = $budget > 0 ? round(($projection / $budget) * 100, 1) : 0;

                $reportData[] = [
                    'group' => $group,
                    'budget' => $budget,
                    'realization' => $realization,
                    'projection' => $projection,
                    'absorption_rate' => $absorptionRate,
                    'outlook_rate' => $outlookRate,
                    'details' => $detailCoaGroups,
                ];

                $grandTotalBudget += $budget;
                $grandTotalRealization += $realization;
                $grandTotalProjection += $projection;
            }
        }

        $grandAbsorption = $grandTotalBudget > 0 ? round(($grandTotalRealization / $grandTotalBudget) * 100, 1) : 0;

        return view('livewire.analytics.cds-report', [
            'periods' => $periods,
            'reportData' => $reportData,
            'grandTotalBudget' => $grandTotalBudget,
            'grandTotalRealization' => $grandTotalRealization,
            'grandTotalProjection' => $grandTotalProjection,
            'grandAbsorption' => $grandAbsorption,
        ])->layout('layouts.contentNavbarLayout');
    }
}
