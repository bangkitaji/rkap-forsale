<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\CashflowGroup;
use App\Models\Coa;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class CashflowGroups extends Component
{
    use WithCustomPagination;

    // Tabs
    public $activeTab = 'groups';

    // Search and filters
    public $search = '';
    public $searchMapping = '';
    public $filterCashflowGroup = '';

    // Model properties for Cashflow Group CRUD
    public $cashflowGroupId = null;
    public $code = '';
    public $name = '';
    public $description = '';

    // Modal control
    public $isEditMode = false;
    public $isModalOpen = false;

    // Bulk selection mapping properties
    public $selectedCoas = [];
    public $bulkCashflowGroupId = '';

    protected $queryString = [
        'activeTab' => ['except' => 'groups'],
        'search' => ['except' => ''],
        'searchMapping' => ['except' => ''],
        'filterCashflowGroup' => ['except' => ''],
    ];

    public function mount()
    {
        abort_if(Auth::user()->cannot('settings.cashflowgroup.manage'), 403);
    }

    public function updatingSearch()
    {
        $this->resetPage('groupsPage');
    }

    public function updatingSearchMapping()
    {
        $this->resetPage('mappingPage');
    }

    public function updatingFilterCashflowGroup()
    {
        $this->resetPage('mappingPage');
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage('groupsPage');
        $this->resetPage('mappingPage');
    }

    protected function rules()
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('cashflow_groups', 'code')->ignore($this->cashflowGroupId)->whereNull('deleted_at'),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ];
    }

    public function create()
    {
        $this->resetInputFields();
        $this->isEditMode = false;
        $this->isModalOpen = true;
    }

    public function edit($id)
    {
        $this->resetInputFields();
        $this->isEditMode = true;

        try {
            $group = CashflowGroup::findOrFail($id);
            $this->cashflowGroupId = $group->id;
            $this->code = $group->code;
            $this->name = $group->name;
            $this->description = $group->description;
            $this->isModalOpen = true;
        } catch (\Exception $e) {
            session()->flash('error', 'Group Cashflow tidak ditemukan.');
        }
    }

    public function store()
    {
        $this->validate();

        try {
            CashflowGroup::updateOrCreate(
                ['id' => $this->cashflowGroupId],
                [
                    'code' => $this->code,
                    'name' => $this->name,
                    'description' => $this->description,
                ]
            );

            session()->flash('message', $this->cashflowGroupId ? 'Group Cashflow berhasil diperbarui.' : 'Group Cashflow berhasil ditambahkan.');
            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', 'Terjadi kesalahan saat menyimpan Group Cashflow.');
        }
    }

    public function delete($id)
    {
        try {
            $group = CashflowGroup::findOrFail($id);

            // Check if there are mapped COAs
            if ($group->coas()->exists()) {
                session()->flash('error', 'Gagal menghapus. Group Cashflow ini masih digunakan oleh beberapa COA.');
                return;
            }

            $group->delete();
            session()->flash('message', 'Group Cashflow berhasil dihapus.');
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menghapus Group Cashflow.');
        }
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetInputFields();
    }

    private function resetInputFields()
    {
        $this->cashflowGroupId = null;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->resetValidation();
    }

    // Mapping actions
    public function mapSingleCoa($coaId, $cashflowGroupId)
    {
        try {
            $coa = Coa::findOrFail($coaId);
            $coa->update([
                'cashflow_group_id' => $cashflowGroupId ?: null
            ]);
            session()->flash('mapping_message', "Pemetaan untuk COA {$coa->code} berhasil diperbarui.");
        } catch (\Exception $e) {
            session()->flash('mapping_error', 'Gagal memperbarui pemetaan.');
        }
    }

    public function applyBulkMapping()
    {
        if (empty($this->selectedCoas)) {
            session()->flash('mapping_error', 'Silakan pilih minimal satu COA.');
            return;
        }

        try {
            $cashflowGroupId = $this->bulkCashflowGroupId ?: null;
            Coa::whereIn('id', $this->selectedCoas)->update([
                'cashflow_group_id' => $cashflowGroupId
            ]);

            $count = count($this->selectedCoas);
            session()->flash('mapping_message', "Berhasil memperbarui pemetaan untuk {$count} COA.");
            $this->selectedCoas = [];
            $this->bulkCashflowGroupId = '';
        } catch (\Exception $e) {
            session()->flash('mapping_error', 'Gagal melakukan pemetaan massal.');
        }
    }

    public function toggleSelectAll($checked)
    {
        if ($checked) {
            $coasQuery = Coa::query();
            if ($this->searchMapping) {
                $coasQuery->where(function ($q) {
                    $q->where('code', 'like', '%' . $this->searchMapping . '%')
                      ->orWhere('title', 'like', '%' . $this->searchMapping . '%');
                });
            }
            if ($this->filterCashflowGroup === 'unmapped') {
                $coasQuery->whereNull('cashflow_group_id');
            } elseif ($this->filterCashflowGroup) {
                $coasQuery->where('cashflow_group_id', $this->filterCashflowGroup);
            }
            $this->selectedCoas = $coasQuery->pluck('id')
                ->map(fn($id) => (string)$id)
                ->toArray();
        } else {
            $this->selectedCoas = [];
        }
    }

    public function render()
    {
        // 1. Cashflow Groups CRUD Data
        $cashflowGroups = CashflowGroup::search('code|name', $this->search)
            ->orderBy('code')
            ->paginate($this->perPage, ['*'], 'groupsPage');

        // 2. COA Mapping Data
        $coasQuery = Coa::query()->with('cashflowGroup', 'coaGroup');

        if ($this->searchMapping) {
            $coasQuery->where(function ($q) {
                $q->where('code', 'like', '%' . $this->searchMapping . '%')
                  ->orWhere('title', 'like', '%' . $this->searchMapping . '%');
            });
        }

        if ($this->filterCashflowGroup === 'unmapped') {
            $coasQuery->whereNull('cashflow_group_id');
        } elseif ($this->filterCashflowGroup) {
            $coasQuery->where('cashflow_group_id', $this->filterCashflowGroup);
        }

        $coas = $coasQuery->orderBy('code')
            ->paginate($this->perPage, ['*'], 'mappingPage');

        // All cashflow groups for dropdowns
        $allCashflowGroups = CashflowGroup::orderBy('code')->get();

        return view('livewire.settings.cashflow-groups', [
            'cashflowGroups' => $cashflowGroups,
            'coas' => $coas,
            'allCashflowGroups' => $allCashflowGroups,
        ])->layout('layouts.contentNavbarLayout');
    }
}
