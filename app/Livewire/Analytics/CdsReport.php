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

                $coaCodes = [];
                if (!empty($coaGroupIds) || !empty($cashflowGroupIds)) {
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
                }

                $budget = 0;
                $realization = 0;
                $projection = 0;

                $detailCoaGroups = [];

                if (!empty($coaCodes)) {
                    $budget = $this->calculateCashFlowBudget($coaCodes, $periodId, $monthFilter, $group->code);
                    $realization = $this->calculateRealization($coaCodes, $periodId, $monthFilter, $group->code);
                    $projection = $this->calculateProjection($coaCodes, $periodId, $monthFilter, $group->code);
                }

                // Details for breakdown view inside accordion
                foreach ($group->coaGroups as $cg) {
                    $cgCoaCodes = DB::table('coas')->where('coa_group_id', $cg->id)->whereNull('deleted_at')->pluck('code')->toArray();
                    $cgBudget = $this->calculateCashFlowBudget($cgCoaCodes, $periodId, $monthFilter, $group->code);

                    $detailCoaGroups[] = [
                        'type' => 'COA Group',
                        'code' => $cg->code,
                        'name' => $cg->name,
                        'budget' => $cgBudget
                    ];
                }

                foreach ($group->cashflowGroups as $cfg) {
                    $cfgCoaCodes = DB::table('coas')->where('cashflow_group_id', $cfg->id)->whereNull('deleted_at')->pluck('code')->toArray();
                    $cfgBudget = $this->calculateCashFlowBudget($cfgCoaCodes, $periodId, $monthFilter, $group->code);

                    $detailCoaGroups[] = [
                        'type' => 'Cash Flow Group',
                        'code' => $cfg->code,
                        'name' => $cfg->name,
                        'budget' => $cfgBudget
                    ];
                }

                $absorptionRate = $budget != 0 ? round(($realization / $budget) * 100, 1) : 0;
                $outlookRate = $budget != 0 ? round(($projection / $budget) * 100, 1) : 0;

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

        $grandAbsorption = $grandTotalBudget != 0 ? round(($grandTotalRealization / $grandTotalBudget) * 100, 1) : 0;

        return view('livewire.analytics.cds-report', [
            'periods' => $periods,
            'reportData' => $reportData,
            'grandTotalBudget' => $grandTotalBudget,
            'grandTotalRealization' => $grandTotalRealization,
            'grandTotalProjection' => $grandTotalProjection,
            'grandAbsorption' => $grandAbsorption,
        ])->layout('layouts.contentNavbarLayout');
    }

    public function formatRp($val): string
    {
        $amount = (float) $val;
        if ($amount < 0) {
            return '-Rp ' . number_format(abs($amount), 0, ',', '.');
        }
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    private function getDirectionMultiplierSql(?string $cdsGroupCode = null): string
    {
        if ($cdsGroupCode) {
            $code = strtoupper(trim($cdsGroupCode));
            if (in_array($code, ['CDS001', 'CDS002', 'CDS003'], true)) {
                return '1';
            }
            if (in_array($code, ['CDS004', 'CDS005', 'CDS006', 'CDS007', 'CDS008', 'CDS009'], true)) {
                return '-1';
            }
        }

        return "(CASE WHEN rkap_budget_items.flow_direction = 'IN' THEN 1 WHEN rkap_budget_items.flow_direction = 'OUT' THEN -1 WHEN coa_categories.group = 'Revenue' OR coa_categories.key = 'non_operating_revenue' OR coas.code LIKE '4%' OR coas.code LIKE '71%' THEN 1 ELSE -1 END)";
    }

    private function calculateCashFlowBudget(array $coaCodes, int $periodId, $monthFilter = null, ?string $cdsGroupCode = null): float
    {
        if (empty($coaCodes)) {
            return 0.0;
        }

        $multiplier = $this->getDirectionMultiplierSql($cdsGroupCode);

        $baseQuery = DB::table('rkap_budget_items')
            ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
            ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
            ->leftJoin('coas', function ($join) {
                $join->on('rkap_budget_items.account_code', '=', 'coas.code')
                     ->whereNull('coas.deleted_at');
            })
            ->leftJoin('coa_categories', 'coas.coa_category_id', '=', 'coa_categories.id')
            ->where('rkap_submissions.rkap_period_id', $periodId)
            ->whereIn('rkap_budget_items.account_code', $coaCodes);

        if ($monthFilter) {
            return (float) $baseQuery
                ->leftJoin('rkap_budget_item_cash_outs', function ($join) use ($monthFilter) {
                    $join->on('rkap_budget_items.id', '=', 'rkap_budget_item_cash_outs.rkap_budget_item_id')
                         ->where('rkap_budget_item_cash_outs.month', '=', (int) $monthFilter);
                })
                ->leftJoin('rkap_budget_item_monthlies', function ($join) use ($monthFilter) {
                    $join->on('rkap_budget_items.id', '=', 'rkap_budget_item_monthlies.rkap_budget_item_id')
                         ->where('rkap_budget_item_monthlies.month', '=', (int) $monthFilter);
                })
                ->selectRaw("SUM(COALESCE(rkap_budget_item_cash_outs.amount, rkap_budget_item_monthlies.amount, rkap_budget_items.total_price / 12.0) * {$multiplier}) as total")
                ->value('total');
        }

        return (float) $baseQuery
            ->leftJoin(
                DB::raw('(SELECT rkap_budget_item_id, SUM(amount) as cash_out_total FROM rkap_budget_item_cash_outs GROUP BY rkap_budget_item_id) as co'),
                'co.rkap_budget_item_id', '=', 'rkap_budget_items.id'
            )
            ->selectRaw("SUM(COALESCE(co.cash_out_total, rkap_budget_items.total_price) * {$multiplier}) as total")
            ->value('total');
    }

    private function calculateRealization(array $coaCodes, int $periodId, $monthFilter = null, ?string $cdsGroupCode = null): float
    {
        if (empty($coaCodes)) {
            return 0.0;
        }

        $multiplier = $this->getDirectionMultiplierSql($cdsGroupCode);

        $query = DB::table('rkap_budget_item_realizations')
            ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
            ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
            ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
            ->leftJoin('coas', function ($join) {
                $join->on('rkap_budget_items.account_code', '=', 'coas.code')
                     ->whereNull('coas.deleted_at');
            })
            ->leftJoin('coa_categories', 'coas.coa_category_id', '=', 'coa_categories.id')
            ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
            ->whereIn('rkap_budget_items.account_code', $coaCodes);

        if ($monthFilter) {
            $query->where('rkap_budget_item_realizations.month', (int) $monthFilter);
        }

        return (float) $query->selectRaw("SUM(rkap_budget_item_realizations.amount * {$multiplier}) as total")->value('total');
    }

    private function calculateProjection(array $coaCodes, int $periodId, $monthFilter = null, ?string $cdsGroupCode = null): float
    {
        if (empty($coaCodes)) {
            return 0.0;
        }

        $multiplier = $this->getDirectionMultiplierSql($cdsGroupCode);

        $baseQuery = DB::table('rkap_budget_items')
            ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
            ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
            ->leftJoin('coas', function ($join) {
                $join->on('rkap_budget_items.account_code', '=', 'coas.code')
                     ->whereNull('coas.deleted_at');
            })
            ->leftJoin('coa_categories', 'coas.coa_category_id', '=', 'coa_categories.id')
            ->where('rkap_submissions.rkap_period_id', $periodId)
            ->whereIn('rkap_budget_items.account_code', $coaCodes);

        if ($monthFilter) {
            return (float) $baseQuery
                ->leftJoin('rkap_budget_item_projections', function ($join) use ($monthFilter) {
                    $join->on('rkap_budget_items.id', '=', 'rkap_budget_item_projections.rkap_budget_item_id')
                         ->where('rkap_budget_item_projections.month', '=', (int) $monthFilter);
                })
                ->selectRaw("SUM(COALESCE(rkap_budget_item_projections.amount, rkap_budget_items.projection / 12.0) * {$multiplier}) as total")
                ->value('total');
        }

        return (float) $baseQuery->selectRaw("SUM(rkap_budget_items.projection * {$multiplier}) as total")->value('total');
    }
}
