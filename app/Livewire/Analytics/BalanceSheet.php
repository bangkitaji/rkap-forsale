<?php

namespace App\Livewire\Analytics;

use Livewire\Component;
use App\Models\RkapPeriod;
use App\Models\ReportGroup;
use App\Models\BalanceSheetOpeningBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class BalanceSheet extends Component
{
    public $periodId;
    public $availablePeriods = [];

    // Data structure to hold computed BS rows
    public $bsData = [];
    public $totals = [];

    public function mount()
    {
        $this->availablePeriods = RkapPeriod::orderBy('year', 'desc')->get();
        
        if (count($this->availablePeriods) > 0) {
            $this->periodId = $this->availablePeriods->first()->id;
        }

        $this->loadBalanceSheet();
    }

    public function updatedPeriodId()
    {
        $this->loadBalanceSheet();
    }

    private function loadBalanceSheet()
    {
        $this->bsData = [];
        $this->totals = [
            'aset' => ['opening' => 0, 'budget' => 0, 'realization' => 0, 'projection' => 0],
            'liabilitas_ekuitas' => ['opening' => 0, 'budget' => 0, 'realization' => 0, 'projection' => 0]
        ];

        if (!$this->periodId) return;

        // 1. Get BS Report Groups
        $bsReportGroups = ReportGroup::where('type', 'BS')
            ->orderBy('code')
            ->with(['coaGroups' => function($q) {
                $q->orderBy('code')->with(['coas' => function($q2) {
                    $q2->orderBy('code');
                }]);
            }])->get();

        // 2. Fetch Opening Balances
        $openingBalances = BalanceSheetOpeningBalance::where('rkap_period_id', $this->periodId)
            ->pluck('amount', 'coa_id')->toArray();

        // 3. Fetch Budgets, Realizations, Projections for this period
        // For RKAP, mutations on BS accounts might be recorded as budget items (e.g., CAPEX).
        $budgets = DB::table('rkap_budget_items')
            ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
            ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
            ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
            ->where('rkap_submissions.rkap_period_id', $this->periodId)
            ->select('coas.id as coa_id', DB::raw('SUM(rkap_budget_items.total_price) as total'))
            ->groupBy('coas.id')
            ->pluck('total', 'coa_id')->toArray();

        $realizations = DB::table('rkap_budget_item_realizations')
            ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
            ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
            ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
            ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
            ->where('rkap_budget_item_realizations.rkap_period_id', $this->periodId)
            ->select('coas.id as coa_id', DB::raw('SUM(rkap_budget_item_realizations.amount) as total'))
            ->groupBy('coas.id')
            ->pluck('total', 'coa_id')->toArray();

        // Projections can come from actual projection inputs or budget (if no projection input)
        // For simplicity in this example, we take existing projections
        $projections = DB::table('rkap_budget_item_projections')
            ->join('rkap_budget_items', 'rkap_budget_item_projections.rkap_budget_item_id', '=', 'rkap_budget_items.id')
            ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
            ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
            ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
            ->where('rkap_submissions.rkap_period_id', $this->periodId)
            ->select('coas.id as coa_id', DB::raw('SUM(rkap_budget_item_projections.amount) as total'))
            ->groupBy('coas.id')
            ->pluck('total', 'coa_id')->toArray();

        // Calculate PL (Net Profit) to inject into Equity
        $netProfit = $this->calculateNetProfit($this->periodId);

        // 4. Build Hierarchical Data
        foreach ($bsReportGroups as $rg) {
            $rgType = (in_array($rg->code, ['BS0001', 'BS0002'])) ? 'aset' : 'liabilitas_ekuitas';
            
            $rgData = [
                'id' => $rg->id,
                'code' => $rg->code,
                'name' => $rg->name,
                'type' => $rgType,
                'opening' => 0,
                'budget' => 0,
                'realization' => 0,
                'projection' => 0,
                'groups' => []
            ];

            foreach ($rg->coaGroups as $cg) {
                $cgData = [
                    'id' => $cg->id,
                    'code' => $cg->code,
                    'name' => $cg->name,
                    'opening' => 0,
                    'budget' => 0,
                    'realization' => 0,
                    'projection' => 0,
                    'coas' => []
                ];

                foreach ($cg->coas as $coa) {
                    $opening = (float) ($openingBalances[$coa->id] ?? 0);
                    $budgetMut = (float) ($budgets[$coa->id] ?? 0);
                    $realMut = (float) ($realizations[$coa->id] ?? 0);
                    $projMut = (float) ($projections[$coa->id] ?? 0);
                    
                    // Base ending balance
                    $budget = $opening + $budgetMut;
                    $realization = $opening + $realMut;
                    $projection = $opening + $projMut;

                    $coaData = [
                        'id' => $coa->id,
                        'code' => $coa->code,
                        'name' => $coa->title,
                        'opening' => $opening,
                        'budget' => $budget,
                        'realization' => $realization,
                        'projection' => $projection,
                    ];

                    $cgData['coas'][] = $coaData;
                    
                    $cgData['opening'] += $opening;
                    $cgData['budget'] += $budget;
                    $cgData['realization'] += $realization;
                    $cgData['projection'] += $projection;
                }

                $rgData['groups'][] = $cgData;
                
                $rgData['opening'] += $cgData['opening'];
                $rgData['budget'] += $cgData['budget'];
                $rgData['realization'] += $cgData['realization'];
                $rgData['projection'] += $cgData['projection'];
            }

            // Inject Current Year Earnings into Equity (BS0005)
            if ($rg->code === 'BS0005') {
                $rgData['groups'][] = [
                    'id' => 'cye',
                    'code' => '',
                    'name' => 'Laba / (Rugi) Tahun Berjalan',
                    'opening' => 0, // Current year earnings starts at 0
                    'budget' => $netProfit['budget'],
                    'realization' => $netProfit['realization'],
                    'projection' => $netProfit['projection'],
                    'coas' => []
                ];

                $rgData['budget'] += $netProfit['budget'];
                $rgData['realization'] += $netProfit['realization'];
                $rgData['projection'] += $netProfit['projection'];
            }

            $this->bsData[] = $rgData;

            // Add to grand totals
            $this->totals[$rgType]['opening'] += $rgData['opening'];
            $this->totals[$rgType]['budget'] += $rgData['budget'];
            $this->totals[$rgType]['realization'] += $rgData['realization'];
            $this->totals[$rgType]['projection'] += $rgData['projection'];
        }
    }

    private function calculateNetProfit($periodId)
    {
        // Simple PL calculation (Revenue - Expenses)
        // PL0001 (Rev), PL0002 (DC), PL0003 (IC), PL0004 (Other Inc), PL0005 (Other Exp)
        
        $plGroups = ReportGroup::where('type', 'PL')->with('coaGroups.coas')->get();
        $plCoaIds = [];
        $revCoaIds = [];
        $expCoaIds = [];

        foreach ($plGroups as $rg) {
            foreach ($rg->coaGroups as $cg) {
                foreach ($cg->coas as $coa) {
                    $plCoaIds[] = $coa->id;
                    if (in_array($rg->code, ['PL0001', 'PL0004'])) {
                        $revCoaIds[] = $coa->id;
                    } else {
                        $expCoaIds[] = $coa->id;
                    }
                }
            }
        }

        // Fetch totals for these COAs
        $budgets = DB::table('rkap_budget_items')
            ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
            ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
            ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
            ->where('rkap_submissions.rkap_period_id', $periodId)
            ->whereIn('coas.id', $plCoaIds)
            ->select('coas.id as coa_id', DB::raw('SUM(rkap_budget_items.total_price) as total'))
            ->groupBy('coas.id')
            ->pluck('total', 'coa_id')->toArray();

        $realizations = DB::table('rkap_budget_item_realizations')
            ->join('rkap_budget_items', 'rkap_budget_item_realizations.rkap_budget_item_id', '=', 'rkap_budget_items.id')
            ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
            ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
            ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
            ->where('rkap_budget_item_realizations.rkap_period_id', $periodId)
            ->whereIn('coas.id', $plCoaIds)
            ->select('coas.id as coa_id', DB::raw('SUM(rkap_budget_item_realizations.amount) as total'))
            ->groupBy('coas.id')
            ->pluck('total', 'coa_id')->toArray();

        $projections = DB::table('rkap_budget_item_projections')
            ->join('rkap_budget_items', 'rkap_budget_item_projections.rkap_budget_item_id', '=', 'rkap_budget_items.id')
            ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
            ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
            ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
            ->where('rkap_submissions.rkap_period_id', $periodId)
            ->whereIn('coas.id', $plCoaIds)
            ->select('coas.id as coa_id', DB::raw('SUM(rkap_budget_item_projections.amount) as total'))
            ->groupBy('coas.id')
            ->pluck('total', 'coa_id')->toArray();

        $np = ['budget' => 0, 'realization' => 0, 'projection' => 0];

        foreach ($revCoaIds as $id) {
            $np['budget'] += (float) ($budgets[$id] ?? 0);
            $np['realization'] += (float) ($realizations[$id] ?? 0);
            $np['projection'] += (float) ($projections[$id] ?? 0);
        }

        foreach ($expCoaIds as $id) {
            $np['budget'] -= (float) ($budgets[$id] ?? 0);
            $np['realization'] -= (float) ($realizations[$id] ?? 0);
            $np['projection'] -= (float) ($projections[$id] ?? 0);
        }

        return $np;
    }

    public function render()
    {
        return view('livewire.analytics.balance-sheet')->layout('layouts.contentNavbarLayout');
    }
}
