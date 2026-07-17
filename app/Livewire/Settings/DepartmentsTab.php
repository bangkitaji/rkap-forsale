<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\Department;
use App\Models\Directorate;
use Illuminate\Validation\Rule;

class DepartmentsTab extends Component
{
    use WithCustomPagination;

    public string $search = '';
    public ?int $departmentId = null;
    public ?int $directorate_id = null;
    public string $code = '';
    public string $name = '';
    public string $description = '';
    public bool $is_verifier = false;
    public bool $is_active = true;
    public bool $isEditMode = false;
    public bool $isModalOpen = false;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'directorate_id' => 'required|exists:directorates,id',
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($this->departmentId)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'is_verifier' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function create(): void
    {
        $this->resetInputFields();
        $this->isEditMode = false;
        $this->isModalOpen = true;
    }

    public function edit(int $id): void
    {
        $this->resetInputFields();
        $dept = Department::findOrFail($id);
        $this->departmentId = $dept->id;
        $this->directorate_id = $dept->directorate_id;
        $this->code = $dept->code;
        $this->name = $dept->name;
        $this->description = $dept->description ?? '';
        $this->is_verifier = $dept->is_verifier;
        $this->is_active = $dept->is_active;
        $this->isEditMode = true;
        $this->isModalOpen = true;
    }

    public function store(): void
    {
        $this->validate();

        Department::updateOrCreate(
            ['id' => $this->departmentId],
            [
                'directorate_id' => $this->directorate_id,
                'code' => strtoupper($this->code),
                'name' => $this->name,
                'description' => $this->description ?: null,
                'is_verifier' => $this->is_verifier,
                'is_active' => $this->is_active,
            ]
        );

        session()->flash('message', $this->isEditMode ? 'Departemen berhasil diperbarui.' : 'Departemen berhasil ditambahkan.');
        $this->closeModal();
    }

    public function delete(int $id): void
    {
        try {
            Department::findOrFail($id)->delete();
            session()->flash('message', __('Departemen berhasil dihapus.'));
        } catch (\Exception $e) {
            session()->flash('error', __('Gagal menghapus departemen. Pastikan tidak ada data terkait.'));
        }
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->resetInputFields();
    }

    private function resetInputFields(): void
    {
        $this->departmentId = null;
        $this->directorate_id = null;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->is_verifier = false;
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.settings.departments-tab', [
            'departments' => Department::with('directorate')
                ->withCount(['bureaus', 'users'])
                ->search('name|code', $this->search)
                ->orderBy('code')
                ->paginate($this->perPage),
            'directorates' => Directorate::active()->orderBy('name')->get(),
        ]);
    }
}
