<?php

namespace App\Livewire\Analytics;

use Livewire\Component;
use App\Models\RkapPeriod;
use App\Models\Coa;
use App\Models\CoaGroup;
use App\Models\ReportGroup;
use App\Models\BalanceSheetOpeningBalance;
use Illuminate\Support\Facades\Auth;

class OpeningBalanceForm extends Component
{
    public $periodId;
    public $openingBalances = []; // Form state: coa_id => amount
    public $bsReportGroups = [];
    public $availablePeriods = [];

    protected $rules = [
        'periodId' => 'required|exists:rkap_periods,id',
        'openingBalances.*' => 'nullable|numeric'
    ];

    public function mount()
    {
        $this->availablePeriods = RkapPeriod::orderBy('year', 'desc')->get();
        
        if (count($this->availablePeriods) > 0) {
            $this->periodId = $this->availablePeriods->first()->id;
        }

        $this->loadReportStructure();
        $this->loadOpeningBalances();
    }

    public function updatedPeriodId()
    {
        $this->loadOpeningBalances();
    }

    private function loadReportStructure()
    {
        // Get all BS report groups with their coaGroups and COAs
        $this->bsReportGroups = ReportGroup::where('type', 'BS')
            ->orderBy('code')
            ->with(['coaGroups' => function($q) {
                $q->orderBy('code')->with(['coas' => function($q2) {
                    $q2->orderBy('code');
                }]);
            }])->get();
    }

    private function loadOpeningBalances()
    {
        $this->openingBalances = [];

        if (!$this->periodId) return;

        $balances = BalanceSheetOpeningBalance::where('rkap_period_id', $this->periodId)->get();

        foreach ($balances as $b) {
            $this->openingBalances[$b->coa_id] = $b->amount;
        }
    }

    public function save()
    {
        $this->validate();

        $user = Auth::user();

        foreach ($this->openingBalances as $coaId => $amount) {
            // Clean up formatting or nulls, save numeric value
            $val = (float) $amount;

            if ($val !== 0.0) {
                $existing = BalanceSheetOpeningBalance::where('rkap_period_id', $this->periodId)
                    ->where('coa_id', $coaId)->first();

                if ($existing) {
                    $existing->update([
                        'amount' => $val,
                        'updated_by' => $user->id
                    ]);
                } else {
                    BalanceSheetOpeningBalance::create([
                        'rkap_period_id' => $this->periodId,
                        'coa_id' => $coaId,
                        'amount' => $val,
                        'created_by' => $user->id,
                        'updated_by' => $user->id
                    ]);
                }
            } else {
                // If 0, we can delete it to keep table clean, or just save 0. Let's delete it.
                BalanceSheetOpeningBalance::where('rkap_period_id', $this->periodId)
                    ->where('coa_id', $coaId)
                    ->delete();
            }
        }

        session()->flash('message', 'Opening balances saved successfully.');
    }

    public function render()
    {
        return view('livewire.analytics.opening-balance-form')->layout('layouts.contentNavbarLayout');
    }
}
