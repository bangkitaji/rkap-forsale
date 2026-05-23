<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Directorate;
use Illuminate\Validation\Rule;

class DirectoratesTab extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $search = '';
    public ?int $directorateId = null;
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
            'code' => ['required', 'string', 'max:20', Rule::unique('directorates', 'code')->ignore($this->directorateId)],
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
        $directorate = Directorate::findOrFail($id);
        $this->directorateId = $directorate->id;
        $this->code = $directorate->code;
        $this->name = $directorate->name;
        $this->description = $directorate->description ?? '';
        $this->is_active = $directorate->is_active;
        $this->isEditMode = true;
        $this->isModalOpen = true;
    }

    public function store(): void
    {
        $this->validate();

        Directorate::updateOrCreate(
            ['id' => $this->directorateId],
            [
                'code' => strtoupper($this->code),
                'name' => $this->name,
                'description' => $this->description ?: null,
                'is_active' => $this->is_active,
            ]
        );

        session()->flash('message', $this->isEditMode ? 'Direktorat berhasil diperbarui.' : 'Direktorat berhasil ditambahkan.');
        $this->closeModal();
    }

    public function toggleActive(int $id): void
    {
        $directorate = Directorate::findOrFail($id);
        $directorate->update(['is_active' => !$directorate->is_active]);
        session()->flash('message', 'Status direktorat diperbarui.');
    }

    public function delete(int $id): void
    {
        try {
            Directorate::findOrFail($id)->delete();
            session()->flash('message', 'Direktorat berhasil dihapus.');
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menghapus direktorat. Pastikan tidak ada data terkait.');
        }
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->resetInputFields();
    }

    private function resetInputFields(): void
    {
        $this->directorateId = null;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        $directorates = Directorate::withCount(['departments', 'users'])
            ->when($this->search, function($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%');
            })
            ->orderBy('code')
            ->paginate(10);

        return view('livewire.settings.directorates-tab', [
            'directorates' => $directorates,
        ]);
    }
}
