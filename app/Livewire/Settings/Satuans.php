<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\Satuan;
use Illuminate\Validation\Rule;

class Satuans extends Component
{
    use WithCustomPagination;

    public $search = '';
    public $satuanId = null;
    public $name = '';
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('satuans', 'name')->ignore($this->satuanId)->whereNull('deleted_at'),
            ],
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
            $satuan = Satuan::findOrFail($id);
            $this->satuanId = $satuan->id;
            $this->name = $satuan->name;
            $this->description = $satuan->description;
            $this->isModalOpen = true;
        } catch (\Exception $e) {
            session()->flash('error', 'Satuan tidak ditemukan.');
        }
    }

    public function store()
    {
        $this->validate();

        try {
            Satuan::updateOrCreate(
                ['id' => $this->satuanId],
                [
                    'name' => $this->name,
                    'description' => $this->description,
                ]
            );

            session()->flash('message', $this->satuanId ? 'Satuan berhasil diperbarui.' : 'Satuan berhasil ditambahkan.');
            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', 'Terjadi kesalahan saat menyimpan Satuan.');
        }
    }

    public function delete($id)
    {
        try {
            Satuan::findOrFail($id)->delete();
            session()->flash('message', 'Satuan berhasil dihapus.');
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menghapus Satuan.');
        }
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetInputFields();
    }

    private function resetInputFields()
    {
        $this->satuanId = null;
        $this->name = '';
        $this->description = '';
        $this->resetValidation();
    }

    public function render()
    {
        $satuans = Satuan::search('name|description', $this->search)
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('livewire.settings.satuans', [
            'satuans' => $satuans,
        ])->layout('layouts.contentNavbarLayout');
    }
}
