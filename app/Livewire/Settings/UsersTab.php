<?php
namespace App\Livewire\Settings;

use Livewire\Component;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsersTab extends Component
{
    public $name;
    public $email;
    public $password;
    public $userId;
    public $isEditMode = false;
    public $isModalOpen = false;
    public $userRoles = [];

    protected function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->userId)
            ],
            'userRoles' => 'nullable|array',
        ];

        if (!$this->isEditMode) {
            $rules['password'] = 'required|min:6';
        } else {
            $rules['password'] = 'nullable|min:6';
        }

        return $rules;
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
            $user = User::findOrFail($id);
            $this->userId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->userRoles = $user->roles->pluck('name')->toArray();
            $this->isModalOpen = true;
        } catch (\Exception $e) {
            session()->flash('error', 'User not found.');
        }
    }

    public function store()
    {
        $this->validate();

        try {
            $data = [
                'name' => $this->name,
                'email' => $this->email,
            ];

            if ($this->password) {
                $data['password'] = Hash::make($this->password);
            }

            $user = User::updateOrCreate(
                ['id' => $this->userId],
                $data
            );

            $user->syncRoles($this->userRoles);

            session()->flash('message', $this->userId ? 'User updated successfully.' : 'User created successfully.');

            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', 'An error occurred while saving the user.');
        }
    }

    public function delete($id)
    {
        try {
            User::findOrFail($id)->delete();
            session()->flash('message', 'User deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Unable to delete user.');
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
        $this->email = '';
        $this->password = '';
        $this->userId = null;
        $this->userRoles = [];
        $this->resetValidation();
    }

    public function render()
    {
        $users = User::with('roles')->get();
        $roles = Role::all();
        
        return view('livewire.settings.users-tab', [
            'users' => $users,
            'roles' => $roles
        ]);
    }
}
