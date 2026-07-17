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
            session()->flash('info', __('Tidak ada versi sebelumnya untuk dibandingkan.'));
            return;
        }

        $this->compareVersionA = $previous->snapshot_data;
        $this->compareVersionB = $current->snapshot_data;
        $this->showDiff = true;
    }

    public function getDiffProperty(): array
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
                $aTotal = collect($aItem['budget_items'] ?? [])->sum(fn($bi) => (float)($bi['quantity'] ?? 0) * (float)($bi['unit_price'] ?? 0));
                $bTotal = collect($bItem['budget_items'] ?? [])->sum(fn($bi) => (float)($bi['quantity'] ?? 0) * (float)($bi['unit_price'] ?? 0));
                if ($aTotal !== $bTotal || json_encode($aItem) !== json_encode($bItem)) {
                    // Compute detailed budget items diff
                    $biDiff = [];
                    $aBis = $aItem['budget_items'] ?? [];
                    $bBis = $bItem['budget_items'] ?? [];

                    // Map by a key: try id, try account_code, fallback to description with prefix
                    $getBiKey = fn($bi) => !empty($bi['id']) ? 'id:' . $bi['id'] : (!empty($bi['account_code']) ? $bi['account_code'] : 'desc:' . ($bi['description'] ?? ''));

                    // Index items in A by key
                    $aUnmatched = [];
                    foreach ($aBis as $aBi) {
                        $key = $getBiKey($aBi);
                        $aUnmatched[$key][] = $aBi;
                    }

                    // Match B items against unmatched A items
                    foreach ($bBis as $bBi) {
                        $key = $getBiKey($bBi);
                        if (!empty($aUnmatched[$key])) {
                            // Match found: retrieve the first unmatched item with this key
                            $aBi = array_shift($aUnmatched[$key]);

                            $isBiModified = ($aBi['quantity'] != $bBi['quantity']) ||
                                            ($aBi['unit_price'] != $bBi['unit_price']) ||
                                            (($aBi['unit'] ?? '') != ($bBi['unit'] ?? '')) ||
                                            (($aBi['description'] ?? '') != ($bBi['description'] ?? '')) ||
                                            (($aBi['remarks'] ?? '') != ($bBi['remarks'] ?? ''));

                            if ($isBiModified) {
                                $biDiff[] = ['status' => 'modified', 'item' => $bBi, 'old' => $aBi];
                            } else {
                                $biDiff[] = ['status' => 'unchanged', 'item' => $bBi];
                            }
                        } else {
                            $biDiff[] = ['status' => 'added', 'item' => $bBi];
                        }
                    }

                    // Remaining unmatched items in A are removed
                    foreach ($aUnmatched as $key => $items) {
                        foreach ($items as $aBi) {
                            $biDiff[] = ['status' => 'removed', 'item' => $aBi];
                        }
                    }

                    $diff[] = [
                        'status' => 'modified',
                        'item' => $bItem,
                        'old' => $aItem,
                        'old_total' => $aTotal,
                        'new_total' => $bTotal,
                        'budget_items_diff' => $biDiff
                    ];
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
