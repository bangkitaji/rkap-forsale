<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapPeriod;

class RkapPeriodManagement extends Component
{
    public ?int $periodId = null;
    public int $year;
    public string $title = '';
    public string $description = '';
    public string $status = 'draft';
    public ?string $submission_start = null;
    public ?string $submission_end = null;
    public bool $isEditMode = false;
    public bool $isModalOpen = false;

    public function mount(): void
    {
        $this->year = (int) date('Y') + 1;
    }

    protected function rules(): array
    {
        return [
            'year'             => 'required|integer|min:2000|max:2100',
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'status'           => 'required|in:draft,open,closed,finalized',
            'submission_start' => 'nullable|date',
            'submission_end'   => 'nullable|date|after_or_equal:submission_start',
        ];
    }

    public function create(): void
    {
        $this->resetInputFields();
        $this->isEditMode = false;
        $this->isModalOpen = true;
    }

    public function edit(int $id): void
    {
        $this->resetInputFields();
        $period = RkapPeriod::findOrFail($id);
        $this->periodId = $period->id;
        $this->year = $period->year;
        $this->title = $period->title;
        $this->description = $period->description ?? '';
        $this->status = $period->status;
        $this->submission_start = $period->submission_start?->format('Y-m-d');
        $this->submission_end = $period->submission_end?->format('Y-m-d');
        $this->isEditMode = true;
        $this->isModalOpen = true;
    }

    public function store(): void
    {
        $this->validate();

        RkapPeriod::updateOrCreate(
            ['id' => $this->periodId],
            [
                'year'             => $this->year,
                'title'            => $this->title,
                'description'      => $this->description ?: null,
                'status'           => $this->status,
                'submission_start' => $this->submission_start ?: null,
                'submission_end'   => $this->submission_end ?: null,
            ]
        );

        session()->flash('message', $this->isEditMode ? 'Periode berhasil diperbarui.' : 'Periode RKAP berhasil dibuat.');
        $this->closeModal();
    }

    public function openPeriod(int $id): void
    {
        RkapPeriod::findOrFail($id)->update(['status' => 'open']);
        session()->flash('message', 'Periode RKAP dibuka. Biro dapat mulai mengajukan RKAP.');
    }

    public function closePeriod(int $id): void
    {
        RkapPeriod::findOrFail($id)->update(['status' => 'closed']);
        session()->flash('message', 'Periode RKAP ditutup.');
    }

    public function finalizePeriod(int $id): void
    {
        RkapPeriod::findOrFail($id)->update(['status' => 'finalized']);
        session()->flash('message', 'Periode RKAP difinalisasi.');
    }

    public function delete(int $id): void
    {
        try {
            RkapPeriod::findOrFail($id)->delete();
            session()->flash('message', 'Periode RKAP dihapus.');
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menghapus. Periode ini sudah memiliki pengajuan.');
        }
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->resetInputFields();
    }

    private function resetInputFields(): void
    {
        $this->periodId = null;
        $this->year = (int) date('Y') + 1;
        $this->title = '';
        $this->description = '';
        $this->status = 'draft';
        $this->submission_start = null;
        $this->submission_end = null;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.rkap.rkap-period-management', [
            'periods' => RkapPeriod::withCount('submissions')->orderByDesc('year')->get(),
        ])->layout('layouts.contentNavbarLayout');
    }
}
