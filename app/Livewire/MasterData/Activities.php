<?php

namespace App\Livewire\MasterData;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\Activity;
use App\Models\WorkPlan;
use App\Imports\ActivityImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\Rule;

class Activities extends Component
{
    use WithCustomPagination, WithFileUploads;

    public $search = '';
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

    public function updatingSearch()
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
        $this->resetInputFields();
        $this->isEditMode = false;
        $this->isModalOpen = true;
    }

    public function edit($id)
    {
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
        return response()->download(
            public_path('templates/activity_template.xlsx'),
            'activity_template.xlsx'
        );
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

        $activities = Activity::with('workPlan')
            ->leftJoin('work_plans', 'activities.work_plan_id', '=', 'work_plans.id')
            ->select('activities.*')
            ->search('code|title|workPlan.title|workPlan.code', $this->search)
            ->orderBy($sortColumn, $this->sortDir)
            ->paginate($this->perPage);

        return view('livewire.master-data.activities', [
            'activities' => $activities,
            'workPlans'  => WorkPlan::orderBy('code')->get()
        ])->layout('layouts.contentNavbarLayout');
    }
}
