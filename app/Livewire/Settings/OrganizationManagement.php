<?php

namespace App\Livewire\Settings;

use Livewire\Component;

class OrganizationManagement extends Component
{
    public string $activeTab = 'directorates';

    public function render()
    {
        return view('livewire.settings.organization-management')
            ->layout('layouts.contentNavbarLayout');
    }
}
