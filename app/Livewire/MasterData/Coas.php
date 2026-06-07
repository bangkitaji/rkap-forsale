<?php

namespace App\Livewire\MasterData;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\Coa;
use App\Imports\CoaImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\Rule;

class Coas extends Component
{
    use WithCustomPagination, WithFileUploads;

    public $search = '';
    public $coaId = null;
    public $code = '';
    public $title = '';
    public $description = '';
    public $uploadedFile = null;

    public $isEditMode = false;
    public $isModalOpen = false;
    public $isUploadModalOpen = false;
    public $importMessage = '';
    public $importStatus = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    protected function rules()
    {
        return [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('coas', 'code')->ignore($this->coaId)->whereNull('deleted_at'),
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
            $coa = Coa::findOrFail($id);
            $this->coaId = $coa->id;
            $this->code = $coa->code;
            $this->title = $coa->title;
            $this->description = $coa->description;
            $this->isModalOpen = true;
        } catch (\Exception $e) {
            session()->flash('error', 'COA not found.');
        }
    }

    public function store()
    {
        $this->validate();

        try {
            Coa::updateOrCreate(
                ['id' => $this->coaId],
                [
                    'code' => $this->code,
                    'title' => $this->title,
                    'description' => $this->description,
                ]
            );

            session()->flash('message', $this->coaId ? 'COA updated successfully.' : 'COA created successfully.');
            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', 'An error occurred while saving the COA.');
        }
    }

    public function delete($id)
    {
        try {
            Coa::findOrFail($id)->delete();
            session()->flash('message', 'COA deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Unable to delete COA.');
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
            $import = new CoaImport();
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

            session()->flash('message', "COAs imported successfully! {$results['success']} records imported.");
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
            public_path('templates/coa_template.xlsx'),
            'coa_template.xlsx'
        );
    }

    private function resetInputFields()
    {
        $this->coaId = null;
        $this->code = '';
        $this->title = '';
        $this->description = '';
        $this->resetValidation();
    }

    public function render()
    {
        $coas = Coa::search('code|title|description', $this->search)
            ->orderBy('code')
            ->paginate($this->perPage);

        return view('livewire.master-data.coas', [
            'coas' => $coas,
        ])->layout('layouts.contentNavbarLayout');
    }
}
