<?php

namespace App\Livewire\MasterData;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\Coa;
use Illuminate\Validation\Rule;

class Coas extends Component
{
    use WithCustomPagination;

    public $search = '';
    public $coaId = null;
    public $code = '';
    public $title = '';
    public $description = '';

    public $isEditMode = false;
    public $isModalOpen = false;

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
