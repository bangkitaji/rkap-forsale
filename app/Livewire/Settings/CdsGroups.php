<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\CdsGroup;
use App\Models\CoaGroup;
use App\Models\CashflowGroup;
use Illuminate\Support\Facades\Auth;

class CdsGroups extends Component
{
    use WithPagination;

    // Tabs
    public $activeTab = 'groups';

    // Search and filters
    public $search = '';
    public $searchMapping = '';

    // Model properties for CDS Group CRUD
    public $cdsGroupId = null;
    public $code = '';
    public $name = '';

    // Modal control
    public $isEditMode = false;
    public $isModalOpen = false;

    // Mapping Modal control
    public $isMappingModalOpen = false;
    public $mappingCdsGroupId = null;
    public $mappingCdsGroupName = '';
    public $selectedMappingCoaGroups = [];
    public $selectedMappingCashflowGroups = [];

    protected $queryString = [
        'activeTab' => ['except' => 'groups'],
        'search' => ['except' => ''],
        'searchMapping' => ['except' => ''],
    ];

    public function mount()
    {
        abort_if(Auth::user()->cannot('settings.cdsgroup.manage'), 403);
    }

    public function updatingSearch()
    {
        $this->resetPage('groupsPage');
    }

    public function updatingSearchMapping()
    {
        $this->resetPage('mappingPage');
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        // 1. CDS Groups CRUD Data
        $cdsGroups = CdsGroup::with('coaGroups')
            ->when($this->search, function ($query) {
                $query->where('code', 'like', '%' . $this->search . '%')
                      ->orWhere('name', 'like', '%' . $this->search . '%');
            })
            ->orderBy('code')
            ->paginate(10, ['*'], 'groupsPage');

        // 2. CDS Groups for Mapping Data
        $mappedCdsGroups = CdsGroup::with(['coaGroups', 'cashflowGroups'])
            ->when($this->searchMapping, function ($query) {
                $query->where('code', 'like', '%' . $this->searchMapping . '%')
                      ->orWhere('name', 'like', '%' . $this->searchMapping . '%');
            })
            ->orderBy('code')
            ->paginate(10, ['*'], 'mappingPage');

        $allCoaGroups = CoaGroup::orderBy('code')->get();
        $allCashflowGroups = CashflowGroup::orderBy('code')->get();

        return view('livewire.settings.cds-groups', [
            'cdsGroups' => $cdsGroups,
            'mappedCdsGroups' => $mappedCdsGroups,
            'allCoaGroups' => $allCoaGroups,
            'allCashflowGroups' => $allCashflowGroups,
        ])->layout('layouts.contentNavbarLayout');
    }

    public function openMappingModal($id)
    {
        $cdsGroup = CdsGroup::with(['coaGroups', 'cashflowGroups'])->findOrFail($id);
        $this->mappingCdsGroupId = $cdsGroup->id;
        $this->mappingCdsGroupName = $cdsGroup->code . ' - ' . $cdsGroup->name;
        
        $this->selectedMappingCoaGroups = $cdsGroup->coaGroups->pluck('id')->map(fn($id) => (string)$id)->toArray();
        $this->selectedMappingCashflowGroups = $cdsGroup->cashflowGroups->pluck('id')->map(fn($id) => (string)$id)->toArray();
        
        $this->isMappingModalOpen = true;
    }

    public function closeMappingModal()
    {
        $this->isMappingModalOpen = false;
        $this->mappingCdsGroupId = null;
        $this->mappingCdsGroupName = '';
        $this->selectedMappingCoaGroups = [];
        $this->selectedMappingCashflowGroups = [];
    }

    public function saveMapping()
    {
        abort_if(Auth::user()->cannot('settings.cdsgroup.manage'), 403);
        
        try {
            $cdsGroup = CdsGroup::findOrFail($this->mappingCdsGroupId);
            
            // Unmap existing COA Groups that are removed
            CoaGroup::where('cds_group_id', $cdsGroup->id)
                ->whereNotIn('id', $this->selectedMappingCoaGroups)
                ->update(['cds_group_id' => null]);
                
            // Map selected COA Groups
            if (!empty($this->selectedMappingCoaGroups)) {
                CoaGroup::whereIn('id', $this->selectedMappingCoaGroups)
                    ->update(['cds_group_id' => $cdsGroup->id]);
            }
            
            // Unmap existing Cashflow Groups that are removed
            CashflowGroup::where('cds_group_id', $cdsGroup->id)
                ->whereNotIn('id', $this->selectedMappingCashflowGroups)
                ->update(['cds_group_id' => null]);
                
            // Map selected Cashflow Groups
            if (!empty($this->selectedMappingCashflowGroups)) {
                CashflowGroup::whereIn('id', $this->selectedMappingCashflowGroups)
                    ->update(['cds_group_id' => $cdsGroup->id]);
            }
            
            session()->flash('mapping_message', "Pemetaan untuk {$cdsGroup->name} berhasil diperbarui.");
            $this->closeMappingModal();
        } catch (\Exception $e) {
            session()->flash('mapping_error', __('Gagal memperbarui pemetaan.'));
        }
    }

    public function openModal()
    {
        $this->isModalOpen = true;
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->cdsGroupId = null;
        $this->code = '';
        $this->name = '';
        $this->isEditMode = false;
        $this->resetErrorBag();
    }

    public function create()
    {
        abort_if(Auth::user()->cannot('settings.cdsgroup.manage'), 403);
        $this->resetForm();
        $this->openModal();
    }

    public function edit($id)
    {
        abort_if(Auth::user()->cannot('settings.cdsgroup.manage'), 403);
        $this->resetForm();
        $cdsGroup = CdsGroup::findOrFail($id);
        $this->cdsGroupId = $cdsGroup->id;
        $this->code = $cdsGroup->code;
        $this->name = $cdsGroup->name;
        $this->isEditMode = true;
        $this->openModal();
    }

    public function save()
    {
        abort_if(Auth::user()->cannot('settings.cdsgroup.manage'), 403);

        $rules = [
            'code' => 'required|string|max:255|unique:cds_groups,code' . ($this->isEditMode ? ',' . $this->cdsGroupId : ''),
            'name' => 'required|string|max:255',
        ];

        $this->validate($rules);

        CdsGroup::updateOrCreate(
            ['id' => $this->cdsGroupId],
            [
                'code' => $this->code,
                'name' => $this->name,
            ]
        );

        session()->flash('message', $this->isEditMode ? 'CDS Group berhasil diperbarui.' : 'CDS Group berhasil ditambahkan.');

        $this->closeModal();
    }

    public function delete($id)
    {
        abort_if(Auth::user()->cannot('settings.cdsgroup.manage'), 403);
        $cdsGroup = CdsGroup::findOrFail($id);
        $cdsGroup->delete();

        session()->flash('message', 'CDS Group berhasil dihapus.');
    }
}
