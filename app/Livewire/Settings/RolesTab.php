<?php
namespace App\Livewire\Settings;

use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

class RolesTab extends Component
{
    public $name;
    public $roleId;
    public $isEditMode = false;
    public $isModalOpen = false;
    public $rolePermissions = [];

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
            session()->flash('error', 'Role not found.');
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
            session()->flash('error', 'An error occurred while saving the role.');
        }

        $this->closeModal();
    }

    public function delete($id)
    {
        try {
            Role::findOrFail($id)->delete();
            Artisan::call('permission:cache-reset');
            session()->flash('message', 'Role deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Unable to delete role.');
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
        return view('livewire.settings.roles-tab', [
            'roles' => Role::with('permissions')->get(),
            'permissions' => Permission::all()
        ]);
    }
}
