<?php

namespace App\Livewire\MasterData;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\CoaGroup;
use App\Models\Coa;
use Illuminate\Validation\Rule;

class CoaGroups extends Component
{
    use WithCustomPagination;

    // Tabs control
    public string $activeTab = 'groups'; // 'groups' or 'mapping'

    // Group CRUD variables
    public $searchGroup = '';
    public $groupId = null;
    public $groupCode = '';
    public $groupName = '';
    public $groupDescription = '';
    public $sortByGroup = 'code';
    public $sortDirGroup = 'asc';
    public bool $isModalOpen = false;
    public bool $isEditMode = false;

    // Mapping variables
    public $searchCoa = '';
    public $filterGroupId = 'all'; // 'all', 'unmapped', or numeric group ID
    public array $selectedCoas = []; // array of COA IDs checked in mapping tab
    public $targetGroupId = ''; // COA Group ID selected for bulk mapping

    // Reset pagination when searching or filtering
    public function updatingSearchGroup(): void
    {
        $this->resetPage();
    }

    public function updatingSearchCoa(): void
    {
        $this->resetPage();
    }

    public function updatingFilterGroupId(): void
    {
        $this->resetPage();
        $this->selectedCoas = [];
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
        $this->selectedCoas = [];
        $this->resetValidation();
    }

    // Group Sorting
    public function sortGroup(string $column): void
    {
        if ($this->sortByGroup === $column) {
            $this->sortDirGroup = $this->sortDirGroup === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortByGroup = $column;
            $this->sortDirGroup = 'asc';
        }
        $this->resetPage();
    }

    // Group Modal CRUD Methods
    public function createGroup(): void
    {
        $this->resetGroupFields();
        $this->isEditMode = false;
        $this->isModalOpen = true;
    }

    public function editGroup($id): void
    {
        $this->resetGroupFields();
        $this->isEditMode = true;

        try {
            $group = CoaGroup::findOrFail($id);
            $this->groupId = $group->id;
            $this->groupCode = $group->code;
            $this->groupName = $group->name;
            $this->groupDescription = $group->description;
            $this->isModalOpen = true;
        } catch (\Exception $e) {
            session()->flash('error', 'COA Group not found.');
        }
    }

    public function storeGroup(): void
    {
        $this->validate([
            'groupCode' => [
                'required',
                'string',
                'max:255',
                Rule::unique('coa_groups', 'code')->ignore($this->groupId)->whereNull('deleted_at'),
            ],
            'groupName' => 'required|string|max:255',
            'groupDescription' => 'nullable|string',
        ]);

        try {
            CoaGroup::updateOrCreate(
                ['id' => $this->groupId],
                [
                    'code' => $this->groupCode,
                    'name' => $this->groupName,
                    'description' => $this->groupDescription,
                ]
            );

            session()->flash('message', $this->groupId ? 'COA Group updated successfully.' : 'COA Group created successfully.');
            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', 'An error occurred while saving the COA Group.');
        }
    }

    public function deleteGroup($id): void
    {
        try {
            CoaGroup::findOrFail($id)->delete();
            session()->flash('message', 'COA Group deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Unable to delete COA Group.');
        }
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->resetGroupFields();
    }

    private function resetGroupFields(): void
    {
        $this->groupId = null;
        $this->groupCode = '';
        $this->groupName = '';
        $this->groupDescription = '';
        $this->resetValidation();
    }

    // Mapping Actions
    public function selectAllCoas(array $pageCoaIds): void
    {
        // Toggle: if all are already selected, clear them. Otherwise, merge them.
        $allSelected = true;
        foreach ($pageCoaIds as $id) {
            if (!in_array((int)$id, $this->selectedCoas, true)) {
                $allSelected = false;
                break;
            }
        }

        if ($allSelected) {
            $this->selectedCoas = array_values(array_diff($this->selectedCoas, array_map('intval', $pageCoaIds)));
        } else {
            $this->selectedCoas = array_values(array_unique(array_merge($this->selectedCoas, array_map('intval', $pageCoaIds))));
        }
    }

    public function mapSelected(): void
    {
        if (empty($this->selectedCoas)) {
            session()->flash('error', 'Please select at least one COA.');
            return;
        }

        if (empty($this->targetGroupId)) {
            session()->flash('error', 'Please select a target COA Group.');
            return;
        }

        try {
            $group = CoaGroup::findOrFail($this->targetGroupId);
            Coa::whereIn('id', $this->selectedCoas)->update(['coa_group_id' => $group->id]);

            session()->flash('message', 'Selected COAs mapped to group "' . $group->name . '" successfully.');
            $this->selectedCoas = [];
            $this->targetGroupId = '';
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to map selected COAs.');
        }
    }

    public function unmapSelected(): void
    {
        if (empty($this->selectedCoas)) {
            session()->flash('error', 'Please select at least one COA.');
            return;
        }

        try {
            Coa::whereIn('id', $this->selectedCoas)->update(['coa_group_id' => null]);

            session()->flash('message', 'Selected COA mappings removed successfully.');
            $this->selectedCoas = [];
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to remove COA mappings.');
        }
    }

    public function render()
    {
        // Load groups list
        $groupsQuery = CoaGroup::query();
        if (!empty($this->searchGroup)) {
            $groupsQuery->search('code|name|description', $this->searchGroup);
        }
        $groups = $groupsQuery->orderBy($this->sortByGroup, $this->sortDirGroup)
            ->paginate($this->perPage, ['*'], 'groupsPage');

        // All groups options for dropdowns
        $allGroups = CoaGroup::orderBy('name')->get();

        // Load COAs for mapping
        $coasQuery = Coa::with('coaGroup');
        
        if (!empty($this->searchCoa)) {
            $coasQuery->search('code|title|description', $this->searchCoa);
        }

        if ($this->filterGroupId === 'unmapped') {
            $coasQuery->whereNull('coa_group_id');
        } elseif ($this->filterGroupId !== 'all' && !empty($this->filterGroupId)) {
            $coasQuery->where('coa_group_id', $this->filterGroupId);
        }

        $coas = $coasQuery->orderBy('code')
            ->paginate($this->perPage, ['*'], 'coasPage');

        return view('livewire.master-data.coa-groups', [
            'groups' => $groups,
            'allGroups' => $allGroups,
            'coas' => $coas,
        ])->layout('layouts.contentNavbarLayout');
    }
}
