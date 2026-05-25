<?php

namespace App\Livewire\Settings;

use Livewire\Component;

class OrganizationDepartments extends Component
{
    public function render()
    {
        return view('livewire.settings.organization-departments')
            ->layout('layouts.contentNavbarLayout');
    }
}
