<?php

namespace App\Livewire\Traits;

use Livewire\WithPagination;

trait WithCustomPagination
{
    use WithPagination;

    public int $perPage = 10;

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    protected function paginationTheme(): string
    {
        return 'bootstrap';
    }

    public function paginationView(): string
    {
        return 'layouts.pagination';
    }
}
