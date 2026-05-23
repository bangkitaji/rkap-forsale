<?php
namespace App\Livewire\Settings;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsersTab extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

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
            session()->flash('error', 'User not found.');
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
            ->when($this->search, function($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
            })
            ->paginate(10);

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
