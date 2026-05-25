<?php

namespace App\Livewire\Settings;

use Livewire\Component;

class OrganizationDirectorates extends Component
{
    public function render()
    {
        return view('livewire.settings.organization-directorates')
            ->layout('layouts.contentNavbarLayout');
    }
}
