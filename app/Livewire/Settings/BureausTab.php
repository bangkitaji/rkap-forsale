<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use Illuminate\Validation\Rule;

class BureausTab extends Component
{
    use WithCustomPagination;

    public $search = '';
    public ?int $bureauId = null;
    public ?int $department_id = null;
    public string $code = '';
    public string $name = '';
    public string $description = '';
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
            'department_id' => 'required|exists:departments,id',
            'code' => ['required', 'string', 'max:20', Rule::unique('bureaus', 'code')->ignore($this->bureauId)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
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
        $bureau = Bureau::findOrFail($id);
        $this->bureauId = $bureau->id;
        $this->department_id = $bureau->department_id;
        $this->code = $bureau->code;
        $this->name = $bureau->name;
        $this->description = $bureau->description ?? '';
        $this->is_active = $bureau->is_active;
        $this->isEditMode = true;
        $this->isModalOpen = true;
    }

    public function store(): void
    {
        $this->validate();

        Bureau::updateOrCreate(
            ['id' => $this->bureauId],
            [
                'department_id' => $this->department_id,
                'code' => strtoupper($this->code),
                'name' => $this->name,
                'description' => $this->description ?: null,
                'is_active' => $this->is_active,
            ]
        );

        session()->flash('message', $this->isEditMode ? 'Biro berhasil diperbarui.' : 'Biro berhasil ditambahkan.');
        $this->closeModal();
    }

    public function delete(int $id): void
    {
        try {
            Bureau::findOrFail($id)->delete();
            session()->flash('message', 'Biro berhasil dihapus.');
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menghapus biro. Pastikan tidak ada data terkait.');
        }
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->resetInputFields();
    }

    private function resetInputFields(): void
    {
        $this->bureauId = null;
        $this->department_id = null;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.settings.bureaus-tab', [
            'bureaus' => Bureau::with(['department.directorate'])
                ->withCount('users')
                ->search('name|code', $this->search)
                ->orderBy('code')
                ->paginate($this->perPage),
            'departments' => Department::with('directorate')->active()->orderBy('name')->get(),
        ]);
    }
}
