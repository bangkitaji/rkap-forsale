<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class MyProfile extends Component
{
    public string $activeTab = 'profile';

    // Profile fields
    public string $name = '';
    public string $email = '';
    public string $position = '';
    public string $bureau_name = '';
    public string $department_name = '';
    public string $directorate_name = '';
    public string $role_name = '';

    // Password fields
    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';

    public function mount(string $tab = 'profile')
    {
        $this->activeTab = in_array($tab, ['profile', 'security']) ? $tab : 'profile';
        
        $user = Auth::user();
        if ($user) {
            $this->name = $user->name;
            $this->email = $user->email;
            $this->position = $user->position ?? '-';
            $this->bureau_name = $user->bureau->name ?? '-';
            $this->department_name = $user->department->name ?? '-';
            $this->directorate_name = $user->directorate->name ?? '-';
            $this->role_name = $user->roles->pluck('name')->implode(', ') ?: 'User';
        }
    }

    public function setTab(string $tab)
    {
        $this->activeTab = in_array($tab, ['profile', 'security']) ? $tab : 'profile';
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function saveProfile()
    {
        $user = Auth::user();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        session()->flash('message_profile', 'Profil berhasil diperbarui.');
    }

    public function savePassword()
    {
        $user = Auth::user();

        $this->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        if (!Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Password saat ini tidak cocok.');
            return;
        }

        $user->update([
            'password' => Hash::make($this->new_password),
            'must_change_password' => false,
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        session()->flash('message_security', 'Password berhasil diperbarui.');
    }

    public function render()
    {
        return view('livewire.auth.my-profile')
            ->layout('layouts.contentNavbarLayout');
    }
}
