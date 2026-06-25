<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\DifferenceGroup;
use App\Models\Coa;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class DifferenceGroups extends Component
{
    use WithCustomPagination;

    // Tabs
    public $activeTab = 'groups';

    // Search and filters
    public $search = '';
    public $searchMapping = '';
    public $filterDifferenceGroup = '';

    // Model properties for Difference Group CRUD
    public $differenceGroupId = null;
    public $code = '';
    public $name = '';
    public $description = '';

    // Modal control
    public $isEditMode = false;
    public $isModalOpen = false;

    // Bulk selection mapping properties
    public $selectedCoas = [];
    public $bulkDifferenceGroupId = '';

    protected $queryString = [
        'activeTab' => ['except' => 'groups'],
        'search' => ['except' => ''],
        'searchMapping' => ['except' => ''],
        'filterDifferenceGroup' => ['except' => ''],
    ];

    public function mount()
    {
        abort_if(Auth::user()->cannot('settings.differencegroup.manage'), 403);
    }

    public function updatingSearch()
    {
        $this->resetPage('groupsPage');
    }

    public function updatingSearchMapping()
    {
        $this->resetPage('mappingPage');
    }

    public function updatingFilterDifferenceGroup()
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
                Rule::unique('difference_groups', 'code')->ignore($this->differenceGroupId)->whereNull('deleted_at'),
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
            $group = DifferenceGroup::findOrFail($id);
            $this->differenceGroupId = $group->id;
            $this->code = $group->code;
            $this->name = $group->name;
            $this->description = $group->description;
            $this->isModalOpen = true;
        } catch (\Exception $e) {
            session()->flash('error', 'Group Difference tidak ditemukan.');
        }
    }

    public function store()
    {
        $this->validate();

        try {
            DifferenceGroup::updateOrCreate(
                ['id' => $this->differenceGroupId],
                [
                    'code' => $this->code,
                    'name' => $this->name,
                    'description' => $this->description,
                ]
            );

            session()->flash('message', $this->differenceGroupId ? 'Group Difference berhasil diperbarui.' : 'Group Difference berhasil ditambahkan.');
            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', 'Terjadi kesalahan saat menyimpan Group Difference.');
        }
    }

    public function delete($id)
    {
        try {
            $group = DifferenceGroup::findOrFail($id);

            // Check if there are mapped COAs
            if ($group->coas()->exists()) {
                session()->flash('error', 'Gagal menghapus. Group Difference ini masih digunakan oleh beberapa COA.');
                return;
            }

            $group->delete();
            session()->flash('message', 'Group Difference berhasil dihapus.');
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menghapus Group Difference.');
        }
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetInputFields();
    }

    private function resetInputFields()
    {
        $this->differenceGroupId = null;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->resetValidation();
    }

    // Mapping actions
    public function mapSingleCoa($coaId, $differenceGroupId)
    {
        try {
            $coa = Coa::findOrFail($coaId);
            $coa->update([
                'difference_group_id' => $differenceGroupId ?: null
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
            $differenceGroupId = $this->bulkDifferenceGroupId ?: null;
            Coa::whereIn('id', $this->selectedCoas)->update([
                'difference_group_id' => $differenceGroupId
            ]);

            $count = count($this->selectedCoas);
            session()->flash('mapping_message', "Berhasil memperbarui pemetaan untuk {$count} COA.");
            $this->selectedCoas = [];
            $this->bulkDifferenceGroupId = '';
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
            if ($this->filterDifferenceGroup === 'unmapped') {
                $coasQuery->whereNull('difference_group_id');
            } elseif ($this->filterDifferenceGroup) {
                $coasQuery->where('difference_group_id', $this->filterDifferenceGroup);
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
        // 1. Difference Groups CRUD Data
        $differenceGroups = DifferenceGroup::search('code|name', $this->search)
            ->orderBy('code')
            ->paginate($this->perPage, ['*'], 'groupsPage');

        // 2. COA Mapping Data
        $coasQuery = Coa::query()->with('differenceGroup', 'coaGroup');

        if ($this->searchMapping) {
            $coasQuery->where(function ($q) {
                $q->where('code', 'like', '%' . $this->searchMapping . '%')
                  ->orWhere('title', 'like', '%' . $this->searchMapping . '%');
            });
        }

        if ($this->filterDifferenceGroup === 'unmapped') {
            $coasQuery->whereNull('difference_group_id');
        } elseif ($this->filterDifferenceGroup) {
            $coasQuery->where('difference_group_id', $this->filterDifferenceGroup);
        }

        $coas = $coasQuery->orderBy('code')
            ->paginate($this->perPage, ['*'], 'mappingPage');

        // All difference groups for dropdowns
        $allDifferenceGroups = DifferenceGroup::orderBy('code')->get();

        return view('livewire.settings.difference-groups', [
            'differenceGroups' => $differenceGroups,
            'coas' => $coas,
            'allDifferenceGroups' => $allDifferenceGroups,
        ])->layout('layouts.contentNavbarLayout');
    }
}
