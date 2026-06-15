<?php

namespace App\Livewire\MasterData;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\Coa;
use App\Models\CoaGroup;

class CoaProfitLossMapping extends Component
{
    use WithCustomPagination;

    public $search = '';
    public $filterCoaGroup = '';
    public $filterProfitLossGroup = '';

    public $sortBy = 'code';
    public $sortDir = 'asc';

    public $selectedCoas = [];
    public $selectAll = false;

    /** @var array<string, string> COA ID => profit_loss_group value */
    public array $mappings = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'filterCoaGroup' => ['except' => ''],
        'filterProfitLossGroup' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
        $this->selectedCoas = [];
        $this->selectAll = false;
    }

    public function updatingFilterCoaGroup()
    {
        $this->resetPage();
        $this->selectedCoas = [];
        $this->selectAll = false;
    }

    public function updatingFilterProfitLossGroup()
    {
        $this->resetPage();
        $this->selectedCoas = [];
        $this->selectAll = false;
    }

    public function updatingPage()
    {
        $this->selectedCoas = [];
        $this->selectAll = false;
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedCoas = $this->getCurrentPageCoaIds();
        } else {
            $this->selectedCoas = [];
        }
    }

    public function updatedSelectedCoas()
    {
        $this->selectAll = false;
    }

    /**
     * Livewire lifecycle hook: fires when any key in $mappings is changed via wire:model.
     * This is the core save mechanism — no JavaScript event value passing needed.
     */
    public function updatedMappings($value, $key)
    {
        try {
            $coa = Coa::findOrFail($key);
            $coa->update([
                'profit_loss_group' => $value !== '' ? $value : null,
            ]);
            $msg = "Pemetaan COA {$coa->code} berhasil diperbarui.";
            $this->dispatch('flash-message', message: $msg, type: 'success');
        } catch (\Exception $e) {
            $err = "Gagal memperbarui pemetaan: " . $e->getMessage();
            $this->dispatch('flash-message', message: $err, type: 'error');
        }
    }

    /**
     * Keep for programmatic / test usage.
     */
    public function updateMapping($coaId, $value)
    {
        try {
            $coa = Coa::findOrFail($coaId);
            $coa->update([
                'profit_loss_group' => $value ?: null
            ]);
            $msg = "Pemetaan COA {$coa->code} berhasil diperbarui.";
            $this->dispatch('flash-message', message: $msg, type: 'success');
        } catch (\Exception $e) {
            $err = "Gagal memperbarui pemetaan COA: " . $e->getMessage();
            $this->dispatch('flash-message', message: $err, type: 'error');
        }
    }

    private function getCurrentPageCoaIds()
    {
        return $this->buildQuery()
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage)
            ->pluck('id')
            ->map(fn($id) => (string)$id)
            ->toArray();
    }

    public function resetFilters()
    {
        $this->search = '';
        $this->filterCoaGroup = '';
        $this->filterProfitLossGroup = '';
        $this->selectedCoas = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy  = $column;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

    public function bulkMap($categoryId)
    {
        // Empty means "-- Pilih Kategori --" was selected — do nothing
        if (empty($categoryId)) {
            return;
        }

        if (empty($this->selectedCoas)) {
            $err = 'Silakan pilih setidaknya satu COA untuk dipetakan.';
            $this->dispatch('flash-message', message: $err, type: 'error');
            return;
        }

        // __reset__ sentinel means "remove mapping"
        $mappingValue = ($categoryId === '__reset__') ? null : $categoryId;

        try {
            $count = Coa::whereIn('id', $this->selectedCoas)->update([
                'profit_loss_group' => $mappingValue
            ]);
            $this->selectedCoas = [];
            $this->selectAll = false;
            $label = $mappingValue ? "kategori '{$mappingValue}'" : 'tanpa pemetaan (reset)';
            $msg = "Berhasil memetakan {$count} COA ke {$label}.";
            $this->dispatch('flash-message', message: $msg, type: 'success');
        } catch (\Exception $e) {
            $err = "Gagal melakukan pemetaan massal: " . $e->getMessage();
            $this->dispatch('flash-message', message: $err, type: 'error');
        }
    }

    public function getCategoriesProperty(): array
    {
        return self::CATEGORIES;
    }

    public const CATEGORIES = [
        'revenue_passenger' => [
            'label' => 'Pendapatan Tiket Penumpang',
            'group' => 'Revenue',
            'color' => 'success'
        ],
        'revenue_non_passenger' => [
            'label' => 'Pendapatan Non-Tiket / Komersial',
            'group' => 'Revenue',
            'color' => 'success'
        ],
        'direct_cost_traction' => [
            'label' => 'Beban Energi Listrik Traksi',
            'group' => 'Direct Cost',
            'color' => 'info'
        ],
        'direct_cost_maintenance' => [
            'label' => 'Beban Pemeliharaan Sarana & Prasarana',
            'group' => 'Direct Cost',
            'color' => 'info'
        ],
        'direct_cost_crew' => [
            'label' => 'Beban Awak KA & Staf Stasiun',
            'group' => 'Direct Cost',
            'color' => 'info'
        ],
        'direct_cost_passenger' => [
            'label' => 'Beban Pelayanan Penumpang',
            'group' => 'Direct Cost',
            'color' => 'info'
        ],
        'direct_cost_others' => [
            'label' => 'Beban Langsung Lainnya',
            'group' => 'Direct Cost',
            'color' => 'info'
        ],
        'indirect_cost_marketing' => [
            'label' => 'Beban Pemasaran & Penjualan',
            'group' => 'Indirect Cost',
            'color' => 'warning'
        ],
        'indirect_cost_admin' => [
            'label' => 'Beban Umum & Administrasi',
            'group' => 'Indirect Cost',
            'color' => 'warning'
        ],
        'depreciation_amortization' => [
            'label' => 'Beban Penyusutan & Amortisasi',
            'group' => 'Indirect Cost',
            'color' => 'warning'
        ],
        'non_operating_revenue' => [
            'label' => 'Pendapatan Non-Operasional',
            'group' => 'Non-Operating',
            'color' => 'secondary'
        ],
        'non_operating_expense' => [
            'label' => 'Beban Non-Operasional / Keuangan',
            'group' => 'Non-Operating',
            'color' => 'secondary'
        ],
    ];

    private function buildQuery()
    {
        return Coa::with('coaGroup')
            ->search('code|title|description', $this->search)
            ->when($this->filterCoaGroup, function ($query) {
                return $query->where('coa_group_id', $this->filterCoaGroup);
            })
            ->when($this->filterProfitLossGroup, function ($query) {
                if ($this->filterProfitLossGroup === 'unmapped') {
                    return $query->whereNull('profit_loss_group');
                }
                if ($this->filterProfitLossGroup === 'mapped') {
                    return $query->whereNotNull('profit_loss_group');
                }
                return $query->where('profit_loss_group', $this->filterProfitLossGroup);
            });
    }

    public function render()
    {
        $coas = $this->buildQuery()
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);

        $coaGroups = CoaGroup::orderBy('name')->get();

        $stats = [
            'total' => Coa::count(),
            'mapped' => Coa::whereNotNull('profit_loss_group')->count(),
            'unmapped' => Coa::whereNull('profit_loss_group')->count(),
        ];

        // Sync mappings property from current page's DB values.
        // This runs AFTER updatedMappings() saves, so DB values are fresh.
        $freshMappings = [];
        foreach ($coas as $coa) {
            $freshMappings[(string)$coa->id] = $coa->profit_loss_group ?? '';
        }
        $this->mappings = $freshMappings;

        return view('livewire.master-data.coa-profit-loss-mapping', [
            'coas' => $coas,
            'coaGroups' => $coaGroups,
            'categories' => self::CATEGORIES,
            'stats' => $stats,
        ])->layout('layouts.contentNavbarLayout');
    }
}
