<?php

namespace App\Livewire\Settings;

use Livewire\Component;

class OrganizationBureaus extends Component
{
    public function render()
    {
        return view('livewire.settings.organization-bureaus')
            ->layout('layouts.contentNavbarLayout');
    }
}
