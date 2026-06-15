<?php

namespace App\Livewire\MasterData;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\Coa;
use App\Models\CoaGroup;
use App\Models\CoaCategory;

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

    /** @var array<string, string> COA ID => coa_category_id value */
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
     */
    public function updatedMappings($value, $key)
    {
        try {
            $coa = Coa::findOrFail($key);
            $coa->update([
                'coa_category_id' => $value !== '' ? (int)$value : null,
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
                'coa_category_id' => $value ? (int)$value : null
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
        $mappingValue = ($categoryId === '__reset__') ? null : (int)$categoryId;

        try {
            $count = Coa::whereIn('id', $this->selectedCoas)->update([
                'coa_category_id' => $mappingValue
            ]);
            $this->selectedCoas = [];
            $this->selectAll = false;
            
            $categoryName = 'tanpa pemetaan (reset)';
            if ($mappingValue) {
                $categoryName = CoaCategory::find($mappingValue)?->label ?? 'kategori';
            }
            $msg = "Berhasil memetakan {$count} COA ke {$categoryName}.";
            $this->dispatch('flash-message', message: $msg, type: 'success');
        } catch (\Exception $e) {
            $err = "Gagal melakukan pemetaan massal: " . $e->getMessage();
            $this->dispatch('flash-message', message: $err, type: 'error');
        }
    }

    private function buildQuery()
    {
        return Coa::with(['coaGroup', 'coaCategory'])
            ->search('code|title|description', $this->search)
            ->when($this->filterCoaGroup, function ($query) {
                return $query->where('coa_group_id', $this->filterCoaGroup);
            })
            ->when($this->filterProfitLossGroup, function ($query) {
                if ($this->filterProfitLossGroup === 'unmapped') {
                    return $query->whereNull('coa_category_id');
                }
                if ($this->filterProfitLossGroup === 'mapped') {
                    return $query->whereNotNull('coa_category_id');
                }
                return $query->where('coa_category_id', $this->filterProfitLossGroup);
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
            'mapped' => Coa::whereNotNull('coa_category_id')->count(),
            'unmapped' => Coa::whereNull('coa_category_id')->count(),
        ];

        // Sync mappings for current page COAs only when they differ
        // (avoids wholesale property replacement which disrupts DOM morphing)
        $currentPageIds = $coas->pluck('id')->map(fn($id) => (string)$id)->toArray();

        // Remove mappings for COAs no longer on current page
        $this->mappings = array_intersect_key(
            $this->mappings,
            array_flip($currentPageIds)
        );

        // Add/update mappings for current page COAs
        foreach ($coas as $coa) {
            $key = (string)$coa->id;
            $dbValue = $coa->coa_category_id ? (string)$coa->coa_category_id : '';
            // Only set if not already present or if DB value changed externally (e.g. bulk map)
            if (!array_key_exists($key, $this->mappings) || $this->mappings[$key] !== $dbValue) {
                $this->mappings[$key] = $dbValue;
            }
        }

        $coaCategories = CoaCategory::orderBy('sort_order')->get();

        return view('livewire.master-data.coa-profit-loss-mapping', [
            'coas' => $coas,
            'coaGroups' => $coaGroups,
            'coaCategories' => $coaCategories,
            'stats' => $stats,
        ])->layout('layouts.contentNavbarLayout');
    }
}
