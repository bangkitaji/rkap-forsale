<?php

namespace App\Livewire\MasterData;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\Activity;
use App\Models\WorkPlan;
use App\Models\Coa;
use App\Imports\ActivityImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

class Activities extends Component
{
    use WithCustomPagination, WithFileUploads;

    public $search = '';
    public bool $onlyUnmapped = false;

    public $activityId = null;
    public $work_plan_id = null;
    public $code = '';
    public $title = '';
    public $description = '';
    public $uploadedFile = null;

    public $sortBy  = 'code';
    public $sortDir = 'asc';

    public $isEditMode = false;
    public $isModalOpen = false;
    public $isUploadModalOpen = false;
    public $importMessage = '';
    public $importStatus = '';

    // ── COA Mapping Modal ──
    public bool $isCoaMappingOpen = false;
    public ?int $mappingActivityId = null;
    public string $coaSearch = '';
    public array $selectedCoaIds = [];

    private function ensureCanManage(): void
    {
        abort_unless(Gate::allows('masterdata.activity.manage'), 403);
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingOnlyUnmapped()
    {
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

    protected function rules()
    {
        return [
            'work_plan_id' => 'required|exists:work_plans,id',
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('activities', 'code')->ignore($this->activityId),
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ];
    }

    public function create()
    {
        $this->ensureCanManage();
        $this->resetInputFields();
        $this->code = $this->generateNextCode();
        $this->isEditMode = false;
        $this->isModalOpen = true;
    }

    /**
     * Generate the next activity code by finding the highest numeric code and adding 1.
     * Non-numeric codes (e.g. REQ-ACT-*) are ignored.
     */
    private function generateNextCode(): string
    {
        $lastCode = Activity::whereRaw('code ~ ?', ['^[0-9]+$'])
            ->orderByRaw('CAST(code AS BIGINT) DESC')
            ->value('code');

        if ($lastCode !== null && is_numeric($lastCode)) {
            return (string) ((int) $lastCode + 1);
        }

        // Fallback: start from 2000000001 if no numeric code exists yet
        return '2000000001';
    }

    public function edit($id)
    {
        $this->ensureCanManage();
        $this->resetInputFields();
        $this->isEditMode = true;

        try {
            $activity = Activity::findOrFail($id);
            $this->activityId = $activity->id;
            $this->work_plan_id = $activity->work_plan_id;
            $this->code = $activity->code;
            $this->title = $activity->title;
            $this->description = $activity->description;
            $this->isModalOpen = true;
        } catch (\Exception $e) {
            session()->flash('error', 'Activity not found.');
        }
    }

    public function store()
    {
        $this->ensureCanManage();
        $this->validate();

        try {
            Activity::updateOrCreate(
                ['id' => $this->activityId],
                [
                    'work_plan_id' => $this->work_plan_id,
                    'code' => $this->code,
                    'title' => $this->title,
                    'description' => $this->description,
                ]
            );

            session()->flash('message', $this->activityId ? 'Activity updated successfully.' : 'Activity created successfully.');
            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', 'An error occurred while saving the Activity.');
        }
    }

    public function delete($id)
    {
        $this->ensureCanManage();
        try {
            Activity::findOrFail($id)->delete();
            session()->flash('message', 'Activity deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Unable to delete Activity.');
        }
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetInputFields();
    }

    public function openUploadModal()
    {
        $this->ensureCanManage();
        $this->isUploadModalOpen = true;
        $this->uploadedFile = null;
        $this->importMessage = '';
        $this->importStatus = '';
    }

    public function closeUploadModal()
    {
        $this->isUploadModalOpen = false;
        $this->uploadedFile = null;
        $this->importMessage = '';
        $this->importStatus = '';
    }

    public function importExcel()
    {
        $this->ensureCanManage();
        $this->validate([
            'uploadedFile' => 'required|mimes:xlsx,xls,csv|max:5120',
        ]);

        try {
            $import = new ActivityImport();
            Excel::import($import, $this->uploadedFile);

            $results = $import->getResults();
            $this->importStatus = 'success';
            $this->importMessage = "Import completed! Success: {$results['success']}, Failed: {$results['failed']}";

            if (!empty($results['errors'])) {
                $this->importMessage .= "\n\nErrors:\n" . implode("\n", array_slice($results['errors'], 0, 10));
                if (count($results['errors']) > 10) {
                    $this->importMessage .= "\n... and " . (count($results['errors']) - 10) . " more errors";
                }
            }

            session()->flash('message', "Activities imported successfully! {$results['success']} records imported.");
            $this->closeUploadModal();
        } catch (\Exception $e) {
            $this->importStatus = 'error';
            $this->importMessage = 'Import failed: ' . $e->getMessage();
            session()->flash('error', $this->importMessage);
        }
    }

    public function downloadTemplate()
    {
        $this->ensureCanManage();
        return response()->download(
            public_path('templates/activity_template.xlsx'),
            'activity_template.xlsx'
        );
    }

    // ── COA Mapping Modal Methods ──

    public function openCoaMapping(int $activityId): void
    {
        $this->ensureCanManage();
        $this->mappingActivityId = $activityId;
        $this->coaSearch = '';
        $this->loadMappingCoas();
        $this->isCoaMappingOpen = true;
    }

    public function closeCoaMapping(): void
    {
        $this->isCoaMappingOpen = false;
        $this->mappingActivityId = null;
        $this->selectedCoaIds = [];
        $this->coaSearch = '';
    }

    private function loadMappingCoas(): void
    {
        $this->selectedCoaIds = [];
        if (!$this->mappingActivityId) {
            return;
        }
        $activity = Activity::with('coas')->find($this->mappingActivityId);
        if ($activity) {
            $this->selectedCoaIds = $activity->coas
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();
        }
    }

    public function updatingCoaSearch(): void
    {
        // no pagination reset needed; we use in-blade filtering
    }

    public function selectAllMappingCoas(): void
    {
        $this->ensureCanManage();
        $allIds = Coa::orderBy('code')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
        $this->selectedCoaIds = array_values(array_unique(
            array_merge(array_map('intval', $this->selectedCoaIds), $allIds)
        ));
    }

    public function deselectAllMappingCoas(): void
    {
        $this->ensureCanManage();
        $this->selectedCoaIds = [];
    }

    public function saveCoaMapping(): void
    {
        $this->ensureCanManage();

        if (!$this->mappingActivityId) {
            session()->flash('error', 'No activity selected for mapping.');
            return;
        }

        $activity = Activity::find($this->mappingActivityId);
        if (!$activity) {
            session()->flash('error', 'Activity not found.');
            return;
        }

        $coaIds = array_values(array_unique(array_map('intval', $this->selectedCoaIds)));
        $activity->coas()->sync($coaIds);

        session()->flash('message', 'COA mapping saved successfully.');
        $this->closeCoaMapping();
    }

    private function resetInputFields()
    {
        $this->activityId = null;
        $this->work_plan_id = null;
        $this->code = '';
        $this->title = '';
        $this->description = '';
        $this->resetValidation();
    }

    public function render()
    {
        // Map sortable column keys to actual DB columns
        $sortColumn = match ($this->sortBy) {
            'work_plan' => 'work_plans.code',
            'title'     => 'activities.title',
            default     => 'activities.code',
        };

        $activities = Activity::with(['workPlan', 'coas'])
            ->leftJoin('work_plans', 'activities.work_plan_id', '=', 'work_plans.id')
            ->select('activities.*')
            ->search('code|title|workPlan.title|workPlan.code|coas.code|coas.title', $this->search)
            ->when($this->onlyUnmapped, fn($q) => $q->doesntHave('coas'))
            ->orderBy($sortColumn, $this->sortDir)
            ->paginate($this->perPage);

        // Load COAs for mapping modal
        $allCoas = $this->isCoaMappingOpen
            ? Coa::orderBy('code')->get()
            : collect();

        return view('livewire.master-data.activities', [
            'activities' => $activities,
            'workPlans'  => WorkPlan::orderBy('code')->get(),
            'allCoas'    => $allCoas,
        ])->layout('layouts.contentNavbarLayout');
    }
}
