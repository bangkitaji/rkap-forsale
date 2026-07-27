<?php

namespace App\Livewire\Rkap;

use App\Models\Setting;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class RkapPeriodClosingManagement extends Component
{
    public int $closingDay;
    public bool $allowProjectionExceedBudget;
    public string $projectionStatus = 'open';
    public string $submissionStatus = 'open';

    public function mount(): void
    {
        if (! auth()->user()?->can('rkap.closing.manage')) {
            abort(403, __('Anda tidak memiliki akses untuk halaman ini.'));
        }

        $this->closingDay = (int) Setting::get('rkap_closing_day', 10);
        $this->allowProjectionExceedBudget = Setting::get('rkap_allow_projection_exceed_budget', '0') === '1';
        $this->projectionStatus = Setting::get('rkap_projection_status', 'open');
        $this->submissionStatus = Setting::get('rkap_submission_status', 'open');
    }

    public function save(): void
    {
        $this->validate([
            'closingDay' => 'required|integer|between:1,31',
            'projectionStatus' => 'required|in:open,closed',
            'submissionStatus' => 'required|in:open,closed',
        ], [
            'closingDay.required' => 'Tanggal closing wajib diisi.',
            'closingDay.integer' => 'Tanggal closing harus berupa angka.',
            'closingDay.between' => 'Tanggal closing harus antara 1 sampai 31.',
            'projectionStatus.required' => 'Status penginputan proyeksi wajib dipilih.',
            'projectionStatus.in' => 'Status penginputan proyeksi tidak valid.',
            'submissionStatus.required' => 'Status pengisian usulan RKAP wajib dipilih.',
            'submissionStatus.in' => 'Status pengisian usulan RKAP tidak valid.',
        ]);

        Setting::set('rkap_closing_day', $this->closingDay);
        Setting::set('rkap_allow_projection_exceed_budget', $this->allowProjectionExceedBudget ? '1' : '0');
        Setting::set('rkap_projection_status', $this->projectionStatus);
        Setting::set('rkap_submission_status', $this->submissionStatus);

        session()->flash('message', __('Setting closing periode berhasil disimpan.'));
    }

    private function getMonthName(int $month): string
    {
        $names = [
            1  => 'Januari',  2  => 'Februari', 3  => 'Maret',
            4  => 'April',    5  => 'Mei',       6  => 'Juni',
            7  => 'Juli',     8  => 'Agustus',   9  => 'September',
            10 => 'Oktober',  11 => 'November',  12 => 'Desember',
        ];
        return $names[$month] ?? '';
    }

    public function render(): View
    {
        $currentYear = (int) date('Y');
        
        $monthData = [];
        for ($m = 1; $m <= 12; $m++) {
            $nextMonth = \Carbon\Carbon::create($currentYear, $m, 1)->addMonth();
            $dayToUse = min($this->closingDay, $nextMonth->daysInMonth);
            $closingDate = $nextMonth->day($dayToUse)->endOfDay();

            if (now()->greaterThan($closingDate)) {
                $statusLabel = 'Closed';
                $statusClass = 'bg-label-danger';
            } else {
                $statusLabel = 'Open';
                $statusClass = 'bg-label-success';
            }

            $monthData[] = [
                'month' => $m,
                'name' => $this->getMonthName($m),
                'closing_date' => $closingDate->format('d M Y'),
                'statusLabel' => $statusLabel,
                'statusClass' => $statusClass,
            ];
        }

        return view('livewire.rkap.rkap-period-closing-management', [
            'monthData' => $monthData,
            'currentYear' => $currentYear,
        ])->layout('layouts.contentNavbarLayout');
    }
}
