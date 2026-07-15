<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PermissionsTab extends Component
{
    use WithCustomPagination;

    public $search = '';
    public $name;
    public $permissionId;
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
                Rule::unique('permissions', 'name')->ignore($this->permissionId),
            ],
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
            $permission = Permission::findOrFail($id);
            $this->permissionId = $permission->id;
            $this->name = $permission->name;
            $this->isModalOpen = true;
        } catch (\Exception $e) {
            session()->flash('error', __('Permission not found.'));
        }
    }

    public function store()
    {
        $this->validate();

        try {
            if ($this->permissionId) {
                $permission = Permission::findOrFail($this->permissionId);
                $permission->update(['name' => $this->name]);
            } else {
                $permission = Permission::create(['name' => $this->name, 'guard_name' => 'web']);
            }

            // Clear Spatie permission cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            session()->flash('message', $this->permissionId ? 'Permission updated successfully.' : 'Permission created successfully.');

            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', __('An error occurred while saving the permission.'));
        }
    }

    public function delete($id)
    {
        try {
            Permission::findOrFail($id)->delete();

            // Clear Spatie permission cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            session()->flash('message', __('Permission deleted successfully.'));
        } catch (\Exception $e) {
            session()->flash('error', __('Unable to delete permission.'));
        }
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetInputFields();
    }

    private function resetInputFields()
    {
        $this->name = '';
        $this->permissionId = null;
        $this->resetValidation();
    }

    public function render()
    {
        $permissions = Permission::when($this->search, function ($query) {
            $query->where(DB::raw('LOWER(name)'), 'like', '%' . strtolower($this->search) . '%');
        })
            ->paginate($this->perPage);

        return view('livewire.settings.permissions-tab', [
            'permissions' => $permissions
        ]);
    }
}
