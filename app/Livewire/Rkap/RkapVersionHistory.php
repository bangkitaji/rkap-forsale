<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapSubmission;
use App\Models\RkapVersion;

class RkapVersionHistory extends Component
{
    public RkapSubmission $submission;
    public ?int $selectedVersionNumber = null;
    public ?array $compareVersionA = null;
    public ?array $compareVersionB = null;
    public bool $showDiff = false;

    public function mount(int $id): void
    {
        $this->submission = RkapSubmission::with([
            'bureau.department.directorate',
            'period',
            'versions.creator',
            'approvals.user',
        ])->findOrFail($id);

        // Default to current version
        $this->selectedVersionNumber = $this->submission->current_version;
    }

    public function selectVersion(int $versionNumber): void
    {
        $this->selectedVersionNumber = $versionNumber;
        $this->showDiff = false;
    }

    public function getSelectedVersionProperty(): ?RkapVersion
    {
        if (!$this->selectedVersionNumber) return null;
        return $this->submission->versions->firstWhere('version_number', $this->selectedVersionNumber);
    }

    public function compareToPrevious(): void
    {
        $current = $this->selectedVersion;
        if (!$current) return;

        $previous = $this->submission->versions->firstWhere('version_number', $this->selectedVersionNumber - 1);
        if (!$previous) {
            session()->flash('info', 'Tidak ada versi sebelumnya untuk dibandingkan.');
            return;
        }

        $this->compareVersionA = $previous->snapshot_data;
        $this->compareVersionB = $current->snapshot_data;
        $this->showDiff = true;
    }

    public function getDiffAttribute(): array
    {
        if (!$this->showDiff || !$this->compareVersionA || !$this->compareVersionB) {
            return [];
        }

        $diff = [];
        $aByName = collect($this->compareVersionA)->keyBy('program_name');
        $bByName = collect($this->compareVersionB)->keyBy('program_name');

        // Items in B (new/modified)
        foreach ($this->compareVersionB as $bItem) {
            $aItem = $aByName->get($bItem['program_name']);
            if (!$aItem) {
                $diff[] = ['status' => 'added', 'item' => $bItem];
            } else {
                $aTotal = collect($aItem['budget_items'] ?? [])->sum(fn($bi) => $bi['quantity'] * $bi['unit_price']);
                $bTotal = collect($bItem['budget_items'] ?? [])->sum(fn($bi) => $bi['quantity'] * $bi['unit_price']);
                if ($aTotal !== $bTotal || json_encode($aItem) !== json_encode($bItem)) {
                    $diff[] = ['status' => 'modified', 'item' => $bItem, 'old' => $aItem, 'old_total' => $aTotal, 'new_total' => $bTotal];
                } else {
                    $diff[] = ['status' => 'unchanged', 'item' => $bItem];
                }
            }
        }

        // Items removed (in A but not in B)
        foreach ($this->compareVersionA as $aItem) {
            if (!$bByName->has($aItem['program_name'])) {
                $diff[] = ['status' => 'removed', 'item' => $aItem];
            }
        }

        return $diff;
    }

    public function render()
    {
        return view('livewire.rkap.rkap-version-history')
            ->layout('layouts.contentNavbarLayout');
    }
}
