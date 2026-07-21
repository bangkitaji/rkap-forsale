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
            session()->flash('error', __('Group Difference tidak ditemukan.'));
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
            session()->flash('error', __('Terjadi kesalahan saat menyimpan Group Difference.'));
        }
    }

    public function delete($id)
    {
        try {
            $group = DifferenceGroup::findOrFail($id);

            // Check if there are mapped COAs
            if ($group->coas()->exists()) {
                session()->flash('error', __('Gagal menghapus. Group Difference ini masih digunakan oleh beberapa COA.'));
                return;
            }

            $group->delete();
            session()->flash('message', __('Group Difference berhasil dihapus.'));
        } catch (\Exception $e) {
            session()->flash('error', __('Gagal menghapus Group Difference.'));
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

    // Single COA mapping modal control
    public $isMappingModalOpen = false;
    public $selectedCoaForMapping = null;
    public array $tempMappedGroups = [];

    // Mapping actions
    public function openMappingModal($coaId)
    {
        $coa = Coa::with('differenceGroups')->findOrFail($coaId);
        $this->selectedCoaForMapping = $coa;
        $this->tempMappedGroups = $coa->differenceGroups->pluck('id')->map(fn($id) => (string)$id)->toArray();
        $this->isMappingModalOpen = true;
    }

    public function closeMappingModal()
    {
        $this->isMappingModalOpen = false;
        $this->selectedCoaForMapping = null;
        $this->tempMappedGroups = [];
    }

    public function saveSingleCoaMapping()
    {
        if (!$this->selectedCoaForMapping) {
            return;
        }

        try {
            $coa = Coa::findOrFail($this->selectedCoaForMapping->id);
            $coa->differenceGroups()->sync($this->tempMappedGroups);
            session()->flash('mapping_message', "Pemetaan untuk COA {$coa->code} berhasil diperbarui.");
            $this->closeMappingModal();
        } catch (\Exception $e) {
            session()->flash('mapping_error', __('Gagal memperbarui pemetaan.'));
        }
    }

    public function mapSingleCoa($coaId, $differenceGroupId)
    {
        try {
            $coa = Coa::findOrFail($coaId);
            if ($differenceGroupId) {
                $coa->differenceGroups()->sync([$differenceGroupId]);
            } else {
                $coa->differenceGroups()->detach();
            }
            session()->flash('mapping_message', "Pemetaan untuk COA {$coa->code} berhasil diperbarui.");
        } catch (\Exception $e) {
            session()->flash('mapping_error', __('Gagal memperbarui pemetaan.'));
        }
    }

    public function applyBulkMapping()
    {
        if (empty($this->selectedCoas)) {
            session()->flash('mapping_error', __('Silakan pilih minimal satu COA.'));
            return;
        }

        try {
            $differenceGroupId = $this->bulkDifferenceGroupId ?: null;
            
            foreach ($this->selectedCoas as $coaId) {
                $coa = Coa::find($coaId);
                if ($coa) {
                    if ($differenceGroupId) {
                        $coa->differenceGroups()->syncWithoutDetaching([$differenceGroupId]);
                    } else {
                        $coa->differenceGroups()->detach();
                    }
                }
            }

            $count = count($this->selectedCoas);
            session()->flash('mapping_message', "Berhasil memperbarui pemetaan untuk {$count} COA.");
            $this->selectedCoas = [];
            $this->bulkDifferenceGroupId = '';
        } catch (\Exception $e) {
            session()->flash('mapping_error', __('Gagal melakukan pemetaan massal.'));
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
                $coasQuery->whereDoesntHave('differenceGroups');
            } elseif ($this->filterDifferenceGroup) {
                $coasQuery->whereHas('differenceGroups', fn($q) => $q->where('difference_groups.id', $this->filterDifferenceGroup));
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
        $differenceGroups = DifferenceGroup::with('coas')
            ->search('code|name', $this->search)
            ->orderBy('code')
            ->paginate($this->perPage, ['*'], 'groupsPage');

        // 2. COA Mapping Data
        $coasQuery = Coa::query()->with('differenceGroups', 'coaGroup');

        if ($this->searchMapping) {
            $coasQuery->where(function ($q) {
                $q->where('code', 'like', '%' . $this->searchMapping . '%')
                  ->orWhere('title', 'like', '%' . $this->searchMapping . '%');
            });
        }

        if ($this->filterDifferenceGroup === 'unmapped') {
            $coasQuery->whereDoesntHave('differenceGroups');
        } elseif ($this->filterDifferenceGroup) {
            $coasQuery->whereHas('differenceGroups', fn($q) => $q->where('difference_groups.id', $this->filterDifferenceGroup));
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
