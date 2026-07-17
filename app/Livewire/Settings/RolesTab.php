<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RolesTab extends Component
{
    use WithCustomPagination;

    public $search = '';
    public $name;
    public $roleId;
    public $isEditMode = false;
    public $isModalOpen = false;
    public $rolePermissions = [];

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
                Rule::unique('roles', 'name')->ignore($this->roleId),
            ],
            'rolePermissions' => 'nullable|array',
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
            $role = Role::findOrFail($id);
            $this->roleId = $role->id;
            $this->name = $role->name;
            $this->rolePermissions = $role->permissions->pluck('name')->toArray();
            $this->isModalOpen = true;
        } catch (\Exception $e) {
            session()->flash('error', __('Role not found.'));
        }
    }

    public function store()
    {
        $this->validate();

        try {
            if ($this->roleId) {
                $role = Role::findOrFail($this->roleId);
                $role->update(['name' => $this->name]);
            } else {
                $role = Role::create(['name' => $this->name, 'guard_name' => 'web']);
            }

            $role->syncPermissions($this->rolePermissions);

            // Clear Spatie permission cache
            Artisan::call('permission:cache-reset');

            session()->flash('message', $this->roleId ? 'Role updated successfully.' : 'Role created successfully.');
        } catch (\Exception $e) {
            session()->flash('error', __('An error occurred while saving the role.'));
        }

        $this->closeModal();
    }

    public function delete($id)
    {
        try {
            Role::findOrFail($id)->delete();
            Artisan::call('permission:cache-reset');
            session()->flash('message', __('Role deleted successfully.'));
        } catch (\Exception $e) {
            session()->flash('error', __('Unable to delete role.'));
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
        $this->roleId = null;
        $this->rolePermissions = [];
        $this->resetValidation();
    }

    public function render()
    {
        $roles = Role::with('permissions')
            ->when($this->search, function ($query) {
                $query->where(DB::raw('LOWER(name)'), 'like', '%' . strtolower($this->search) . '%');
            })
            ->paginate($this->perPage);

        return view('livewire.settings.roles-tab', [
            'roles' => $roles,
            'permissions' => Permission::orderBy('name')->get()
        ]);
    }
}
