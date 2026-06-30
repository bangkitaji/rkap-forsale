<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\ReportGroup;
use App\Models\CoaGroup;
use App\Models\CashflowGroup;
use Illuminate\Validation\Rule;

class ReportGroups extends Component
{
    use WithCustomPagination;

    // Tabs
    public $activeTab = 'groups';

    // Search and filters
    public $search = '';
    public $searchMapping = '';
    public $filterReportGroup = '';

    // Cashflow mapping search and filter
    public $searchCashflow = '';
    public $filterCashflowReportGroup = '';
    public $selectedCashflowGroups = [];
    public $bulkCashflowReportGroupId = '';

    // Model properties for Report Group CRUD
    public $reportGroupId = null;
    public $code = '';
    public $type = 'PL';
    public $name = '';
    public $description = '';

    // Modal control
    public $isEditMode = false;
    public $isModalOpen = false;

    // Bulk selection mapping properties
    public $selectedCoaGroups = [];
    public $bulkReportGroupId = '';

    protected $queryString = [
        'activeTab' => ['except' => 'groups'],
        'search' => ['except' => ''],
        'searchMapping' => ['except' => ''],
        'filterReportGroup' => ['except' => ''],
        'searchCashflow' => ['except' => ''],
        'filterCashflowReportGroup' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage('groupsPage');
    }

    public function updatingSearchMapping()
    {
        $this->resetPage('mappingPage');
    }

    public function updatingFilterReportGroup()
    {
        $this->resetPage('mappingPage');
    }

    public function updatingSearchCashflow()
    {
        $this->resetPage('cashflowPage');
    }

    public function updatingFilterCashflowReportGroup()
    {
        $this->resetPage('cashflowPage');
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage('groupsPage');
        $this->resetPage('mappingPage');
        $this->resetPage('cashflowPage');
    }

    protected function rules()
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('report_groups', 'code')->ignore($this->reportGroupId)->whereNull('deleted_at'),
            ],
            'type' => 'required|in:PL,BS,CF',
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
            $group = ReportGroup::findOrFail($id);
            $this->reportGroupId = $group->id;
            $this->code = $group->code;
            $this->type = $group->type;
            $this->name = $group->name;
            $this->description = $group->description;
            $this->isModalOpen = true;
        } catch (\Exception $e) {
            session()->flash('error', 'Report Group tidak ditemukan.');
        }
    }

    public function store()
    {
        $this->validate();

        try {
            ReportGroup::updateOrCreate(
                ['id' => $this->reportGroupId],
                [
                    'code' => $this->code,
                    'type' => $this->type,
                    'name' => $this->name,
                    'description' => $this->description,
                ]
            );

            session()->flash('message', $this->reportGroupId ? 'Report Group berhasil diperbarui.' : 'Report Group berhasil ditambahkan.');
            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', 'Terjadi kesalahan saat menyimpan Report Group.');
        }
    }

    public function delete($id)
    {
        try {
            $group = ReportGroup::findOrFail($id);

            // Check if there are mapped COA Groups
            if ($group->coaGroups()->exists()) {
                session()->flash('error', 'Gagal menghapus. Report Group ini masih digunakan oleh beberapa COA Group.');
                return;
            }

            $group->delete();
            session()->flash('message', 'Report Group berhasil dihapus.');
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menghapus Report Group.');
        }
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetInputFields();
    }

    private function resetInputFields()
    {
        $this->reportGroupId = null;
        $this->code = '';
        $this->type = 'PL';
        $this->name = '';
        $this->description = '';
        $this->resetValidation();
    }

    // Mapping actions
    public function mapSingleGroup($coaGroupId, $reportGroupId)
    {
        try {
            $coaGroup = CoaGroup::findOrFail($coaGroupId);
            $coaGroup->update([
                'report_group_id' => $reportGroupId ?: null
            ]);
            session()->flash('mapping_message', "Pemetaan untuk COA Group {$coaGroup->name} berhasil diperbarui.");
        } catch (\Exception $e) {
            session()->flash('mapping_error', 'Gagal memperbarui pemetaan.');
        }
    }

    public function applyBulkMapping()
    {
        if (empty($this->selectedCoaGroups)) {
            session()->flash('mapping_error', 'Silakan pilih minimal satu COA Group.');
            return;
        }

        try {
            $reportGroupId = $this->bulkReportGroupId ?: null;
            CoaGroup::whereIn('id', $this->selectedCoaGroups)->update([
                'report_group_id' => $reportGroupId
            ]);

            $count = count($this->selectedCoaGroups);
            session()->flash('mapping_message', "Berhasil memperbarui pemetaan untuk {$count} COA Group.");
            $this->selectedCoaGroups = [];
            $this->bulkReportGroupId = '';
        } catch (\Exception $e) {
            session()->flash('mapping_error', 'Gagal melakukan pemetaan massal.');
        }
    }

    public function toggleSelectAll($checked)
    {
        if ($checked) {
            $coaGroupsQuery = CoaGroup::query();
            if ($this->searchMapping) {
                $coaGroupsQuery->where(function ($q) {
                    $q->where('code', 'like', '%' . $this->searchMapping . '%')
                        ->orWhere('name', 'like', '%' . $this->searchMapping . '%');
                });
            }
            if ($this->filterReportGroup === 'unmapped') {
                $coaGroupsQuery->whereNull('report_group_id');
            } elseif ($this->filterReportGroup) {
                $coaGroupsQuery->where('report_group_id', $this->filterReportGroup);
            }
            $this->selectedCoaGroups = $coaGroupsQuery->pluck('id')
                ->map(fn($id) => (string)$id)
                ->toArray();
        } else {
            $this->selectedCoaGroups = [];
        }
    }

    public function mapSingleCashflowGroup($cashflowGroupId, $reportGroupId)
    {
        try {
            $cashflowGroup = CashflowGroup::findOrFail($cashflowGroupId);
            $cashflowGroup->update([
                'report_group_id' => $reportGroupId ?: null
            ]);
            session()->flash('mapping_message', "Pemetaan untuk Cashflow Group {$cashflowGroup->name} berhasil diperbarui.");
        } catch (\Exception $e) {
            session()->flash('mapping_error', 'Gagal memperbarui pemetaan Cashflow.');
        }
    }

    public function applyBulkCashflowMapping()
    {
        if (empty($this->selectedCashflowGroups)) {
            session()->flash('mapping_error', 'Silakan pilih minimal satu Cashflow Group.');
            return;
        }

        try {
            $reportGroupId = $this->bulkCashflowReportGroupId ?: null;
            CashflowGroup::whereIn('id', $this->selectedCashflowGroups)->update([
                'report_group_id' => $reportGroupId
            ]);

            $count = count($this->selectedCashflowGroups);
            session()->flash('mapping_message', "Berhasil memperbarui pemetaan untuk {$count} Cashflow Group.");
            $this->selectedCashflowGroups = [];
            $this->bulkCashflowReportGroupId = '';
        } catch (\Exception $e) {
            session()->flash('mapping_error', 'Gagal melakukan pemetaan massal Cashflow.');
        }
    }

    public function toggleSelectAllCashflow($checked)
    {
        if ($checked) {
            $cashflowQuery = CashflowGroup::query();
            if ($this->searchCashflow) {
                $cashflowQuery->where(function ($q) {
                    $q->where('code', 'like', '%' . $this->searchCashflow . '%')
                        ->orWhere('name', 'like', '%' . $this->searchCashflow . '%');
                });
            }
            if ($this->filterCashflowReportGroup === 'unmapped') {
                $cashflowQuery->whereNull('report_group_id');
            } elseif ($this->filterCashflowReportGroup) {
                $cashflowQuery->where('report_group_id', $this->filterCashflowReportGroup);
            }
            $this->selectedCashflowGroups = $cashflowQuery->pluck('id')
                ->map(fn($id) => (string)$id)
                ->toArray();
        } else {
            $this->selectedCashflowGroups = [];
        }
    }

    public function render()
    {
        // 1. Report Groups CRUD Data
        $reportGroups = ReportGroup::search('code|name|type', $this->search)
            ->orderBy('type')
            ->orderBy('code')
            ->paginate($this->perPage, ['*'], 'groupsPage');

        // 2. COA Groups Mapping Data
        $coaGroupsQuery = CoaGroup::query()->with('reportGroup');

        if ($this->searchMapping) {
            $coaGroupsQuery->where(function ($q) {
                $q->where('code', 'like', '%' . $this->searchMapping . '%')
                    ->orWhere('name', 'like', '%' . $this->searchMapping . '%');
            });
        }

        if ($this->filterReportGroup === 'unmapped') {
            $coaGroupsQuery->whereNull('report_group_id');
        } elseif ($this->filterReportGroup) {
            $coaGroupsQuery->where('report_group_id', $this->filterReportGroup);
        }

        $coaGroups = $coaGroupsQuery->orderBy('code')
            ->paginate($this->perPage, ['*'], 'mappingPage');

        // 3. Cashflow Groups Mapping Data
        $cashflowGroupsQuery = CashflowGroup::query()->with('reportGroup');

        if ($this->searchCashflow) {
            $cashflowGroupsQuery->where(function ($q) {
                $q->where('code', 'like', '%' . $this->searchCashflow . '%')
                    ->orWhere('name', 'like', '%' . $this->searchCashflow . '%');
            });
        }

        if ($this->filterCashflowReportGroup === 'unmapped') {
            $cashflowGroupsQuery->whereNull('report_group_id');
        } elseif ($this->filterCashflowReportGroup) {
            $cashflowGroupsQuery->where('report_group_id', $this->filterCashflowReportGroup);
        }

        $cashflowGroups = $cashflowGroupsQuery->orderBy('code')
            ->paginate($this->perPage, ['*'], 'cashflowPage');

        // All report groups for dropdowns
        $allReportGroups = ReportGroup::orderBy('type')->orderBy('name')->get();

        return view('livewire.settings.report-groups', [
            'reportGroups' => $reportGroups,
            'coaGroups' => $coaGroups,
            'allReportGroups' => $allReportGroups,
            'cashflowGroups' => $cashflowGroups,
        ])->layout('layouts.contentNavbarLayout');
    }
}
