<?php

namespace App\Livewire\MasterData;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\Activity;
use App\Models\Coa;
use App\Imports\ActivityCoaMappingImport;
use Maatwebsite\Excel\Facades\Excel;

class ActivityCoaMapping extends Component
{
    use WithCustomPagination, WithFileUploads;

    public $activityId = null;
    public $uploadedFile = null;
    public $isUploadModalOpen = false;
    public $importMessage = '';
    public $importStatus = '';

    public string $activitySearch = '';
    public string $coaSearch = '';

    public array $selectedCoaIds = [];

    public function mount(): void
    {
        $this->selectedCoaIds = [];
    }

    public function updatedActivityId(): void
    {
        $this->loadSelectedCoas();
    }

    public function selectActivity(int $id): void
    {
        $this->activityId = $id;
        $this->loadSelectedCoas();
        $this->activitySearch = ''; // Clear search query to close autocomplete dropdown
    }

    public function clearActivity(): void
    {
        $this->activityId = null;
        $this->activitySearch = '';
        $this->selectedCoaIds = [];
    }

    private function loadSelectedCoas(): void
    {
        $this->selectedCoaIds = [];

        if (empty($this->activityId)) {
            return;
        }

        $activity = Activity::with('coas')->find($this->activityId);
        if (!$activity) {
            return;
        }

        $this->selectedCoaIds = $activity
            ->coas
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    public function updatingCoaSearch(): void
    {
        $this->resetPage();
    }

    public function save(): void
    {
        if (empty($this->activityId)) {
            session()->flash('error', 'Please select an Activity.');
            return;
        }

        $activity = Activity::find($this->activityId);
        if (!$activity) {
            session()->flash('error', 'Selected Activity not found.');
            return;
        }

        $coaIds = array_values(array_unique(array_map('intval', $this->selectedCoaIds)));

        // If nothing selected, sync([]) will detach all.
        $activity->coas()->sync($coaIds);

        session()->flash('message', 'Activity ↔ COA mapping saved successfully.');
    }

    public function selectAll(): void
    {
        // Add all COAs on the current page to the selection
        $pageIds = Coa::search('code|title|description', $this->coaSearch)
            ->orderBy('code')
            ->paginate($this->perPage)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        $this->selectedCoaIds = array_values(array_unique(
            array_merge(array_map('intval', $this->selectedCoaIds), $pageIds)
        ));
    }

    public function deselectAll(): void
    {
        // Remove all COAs on the current page from the selection
        $pageIds = Coa::search('code|title|description', $this->coaSearch)
            ->orderBy('code')
            ->paginate($this->perPage)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        $this->selectedCoaIds = array_values(array_filter(
            array_map('intval', $this->selectedCoaIds),
            fn($id) => !in_array($id, $pageIds, true)
        ));
    }

    public function openUploadModal(): void
    {
        $this->isUploadModalOpen = true;
        $this->uploadedFile = null;
        $this->importMessage = '';
        $this->importStatus = '';
    }

    public function closeUploadModal(): void
    {
        $this->isUploadModalOpen = false;
        $this->uploadedFile = null;
        $this->importMessage = '';
        $this->importStatus = '';
    }

    public function importExcel(): void
    {
        $this->validate([
            'uploadedFile' => 'required|mimes:xlsx,xls,csv|max:5120',
        ]);

        try {
            $import = new ActivityCoaMappingImport();
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

            session()->flash('message', "Activity-COA mappings imported successfully! {$results['success']} records imported.");
            $this->closeUploadModal();
        } catch (\Exception $e) {
            $this->importStatus = 'error';
            $this->importMessage = 'Import failed: ' . $e->getMessage();
            session()->flash('error', $this->importMessage);
        }
    }

    public function downloadTemplate(): void
    {
        response()->download(
            public_path('templates/activity_coa_mapping_template.xlsx'),
            'activity_coa_mapping_template.xlsx'
        )->send();
    }

    public function render()
    {
        $activities = Activity::query()
            ->with('workPlan')
            ->when(
                !empty($this->activitySearch),
                fn($q) => $q->search('code|title|workPlan.title|workPlan.code', $this->activitySearch)
            )
            ->orderBy('code')
            ->limit(10)
            ->get();

        $coas = Coa::search('code|title|description', $this->coaSearch)
            ->orderBy('code')
            ->paginate($this->perPage);

        $selected = array_flip(array_map('intval', $this->selectedCoaIds));
        $selectedActivity = $this->activityId ? Activity::with('workPlan')->find($this->activityId) : null;

        return view('livewire.master-data.activity-coa-mapping', [
            'activities' => $activities,
            'coas' => $coas,
            'selected' => $selected,
            'selectedActivity' => $selectedActivity,
        ])->layout('layouts.contentNavbarLayout');
    }
}
