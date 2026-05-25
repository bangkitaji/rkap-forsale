<?php

namespace App\Livewire\Settings;

use Livewire\Component;

class UserManagementRoles extends Component
{
    public function render()
    {
        return view('livewire.settings.user-management-roles')
            ->layout('layouts.contentNavbarLayout');
    }
}
