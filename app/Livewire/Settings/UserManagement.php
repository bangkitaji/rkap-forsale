<?php
namespace App\Livewire\Settings;

use Livewire\Component;

class UserManagement extends Component
{
    public $activeTab = 'users';

    public function render()
    {
        return view('livewire.settings.user-management')->layout('layouts.contentNavbarLayout');
    }
}
