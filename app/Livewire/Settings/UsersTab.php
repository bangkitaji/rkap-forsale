<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\User;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Log;

class UsersTab extends Component
{
    use WithCustomPagination;

    public $search = '';
    public $name;
    public $email;
    public $password;
    public $userId;
    public $isEditMode = false;
    public $isModalOpen = false;
    public $userRoles = [];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    // Organization fields
    public $organization_type = ''; // 'bureau' | 'department' | 'directorate'
    public $bureau_id;
    public $department_id;
    public $directorate_id;
    public $position;

    protected function rules()
    {
        $rules = [
            'name'              => 'required|string|max:255',
            'email'             => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->userId)
            ],
            'userRoles'         => 'nullable|array',
            'organization_type' => 'nullable|in:bureau,department,directorate',
            'bureau_id'         => 'nullable|exists:bureaus,id',
            'department_id'     => 'nullable|exists:departments,id',
            'directorate_id'    => 'nullable|exists:directorates,id',
            'position'          => 'nullable|string|max:255',
        ];

        if (!$this->isEditMode) {
            $rules['password'] = ['required', Password::min(8)->mixedCase()->numbers()];
        } else {
            $rules['password'] = ['nullable', Password::min(8)->mixedCase()->numbers()];
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
            $this->userId        = $user->id;
            $this->name          = $user->name;
            $this->email         = $user->email;
            $this->position      = $user->position;
            $this->bureau_id     = $user->bureau_id;
            $this->department_id = $user->department_id;
            $this->directorate_id = $user->directorate_id;
            $this->userRoles     = $user->roles->pluck('name')->toArray();

            if ($user->bureau_id) {
                $this->organization_type = 'bureau';
            } elseif ($user->department_id) {
                $this->organization_type = 'department';
            } elseif ($user->directorate_id) {
                $this->organization_type = 'directorate';
            }

            $this->isModalOpen = true;
        } catch (\Exception $e) {
            session()->flash('error', __('User not found.'));
        }
    }

    public function store()
    {
        $this->validate();

        try {
            $data = [
                'name'     => $this->name,
                'email'    => $this->email,
                'position' => $this->position,
                // Reset all org ids first, then set the selected one
                'bureau_id'      => null,
                'department_id'  => null,
                'directorate_id' => null,
            ];

            if ($this->organization_type === 'bureau' && $this->bureau_id) {
                $data['bureau_id'] = $this->bureau_id;
            } elseif ($this->organization_type === 'department' && $this->department_id) {
                $data['department_id'] = $this->department_id;
            } elseif ($this->organization_type === 'directorate' && $this->directorate_id) {
                $data['directorate_id'] = $this->directorate_id;
            }

            if ($this->password) {
                $data['password'] = Hash::make($this->password);
                $data['must_change_password'] = true;
            }

            $user = User::updateOrCreate(
                ['id' => $this->userId],
                $data
            );

            $user->syncRoles($this->userRoles);

            Log::info($this->userId ? 'User updated in settings' : 'User created in settings', [
                'user_id' => $user->id,
                'email' => $user->email,
                'roles' => $this->userRoles,
                'performed_by' => auth()->id(),
            ]);

            session()->flash('message', $this->userId ? 'User updated successfully.' : 'User created successfully.');

            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', __('An error occurred while saving the user.'));
        }
    }

    public function delete($id)
    {
        try {
            $user = User::findOrFail($id);
            $userEmail = $user->email;
            $user->delete();

            Log::info('User deleted in settings', [
                'user_id' => $id,
                'email' => $userEmail,
                'performed_by' => auth()->id(),
            ]);

            session()->flash('message', __('User deleted successfully.'));
        } catch (\Exception $e) {
            session()->flash('error', __('Unable to delete user.'));
        }
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetInputFields();
    }

    private function resetInputFields()
    {
        $this->name              = '';
        $this->email             = '';
        $this->password          = '';
        $this->userId            = null;
        $this->userRoles         = [];
        $this->organization_type = '';
        $this->bureau_id         = null;
        $this->department_id     = null;
        $this->directorate_id    = null;
        $this->position          = '';
        $this->resetValidation();
    }

    public function render()
    {
        $users = User::with(['roles', 'bureau', 'department', 'directorate'])
            ->search('name|email', $this->search)
            ->paginate($this->perPage);

        $roles       = Role::all();
        $bureaus     = Bureau::active()->orderBy('name')->get();
        $departments = Department::active()->orderBy('name')->get();
        $directorates = Directorate::active()->orderBy('name')->get();

        return view('livewire.settings.users-tab', [
            'users'        => $users,
            'roles'        => $roles,
            'bureaus'      => $bureaus,
            'departments'  => $departments,
            'directorates' => $directorates,
        ]);
    }
}
