<?php

namespace App\Livewire\MasterData;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\WorkPlan;
use App\Imports\WorkPlanImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\Rule;

class WorkPlans extends Component
{
    use WithCustomPagination, WithFileUploads;

    public $search = '';
    public $workPlanId = null;
    public $code = '';
    public $title = '';
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
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('work_plans', 'code')->ignore($this->workPlanId),
            ],
            'title' => 'required|string|max:255',
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
            $workPlan = WorkPlan::findOrFail($id);
            $this->workPlanId = $workPlan->id;
            $this->code = $workPlan->code;
            $this->title = $workPlan->title;
            $this->isModalOpen = true;
        } catch (\Exception $e) {
            session()->flash('error', 'Work Plan not found.');
        }
    }

    public function store()
    {
        $this->validate();

        try {
            WorkPlan::updateOrCreate(
                ['id' => $this->workPlanId],
                [
                    'code' => $this->code,
                    'title' => $this->title,
                ]
            );

            session()->flash('message', $this->workPlanId ? 'Work Plan updated successfully.' : 'Work Plan created successfully.');
            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', 'An error occurred while saving the Work Plan.');
        }
    }

    public function delete($id)
    {
        try {
            WorkPlan::findOrFail($id)->delete();
            session()->flash('message', 'Work Plan deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Unable to delete Work Plan.');
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
            $import = new WorkPlanImport();
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

            session()->flash('message', "Work Plans imported successfully! {$results['success']} records imported.");
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
            public_path('templates/workplan_template.xlsx'),
            'workplan_template.xlsx'
        );
    }

    private function resetInputFields()
    {
        $this->workPlanId = null;
        $this->code = '';
        $this->title = '';
        $this->resetValidation();
    }

    public function render()
    {
        $workPlans = WorkPlan::search('code|title', $this->search)
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);

        return view('livewire.master-data.work-plans', [
            'workPlans' => $workPlans
        ])->layout('layouts.contentNavbarLayout');
    }
}
