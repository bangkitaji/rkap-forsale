<?php

namespace App\Livewire\Settings;

use Livewire\Component;

class UserManagementUsers extends Component
{
    public function render()
    {
        return view('livewire.settings.user-management-users')
            ->layout('layouts.contentNavbarLayout');
    }
}
