<?php

namespace App\Livewire\Settings;

use Livewire\Component;

class UserManagementPermissions extends Component
{
    public function render()
    {
        return view('livewire.settings.user-management-permissions')
            ->layout('layouts.contentNavbarLayout');
    }
}
