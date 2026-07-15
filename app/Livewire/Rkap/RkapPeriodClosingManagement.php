<?php

namespace App\Livewire\Rkap;

use App\Models\Setting;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class RkapPeriodClosingManagement extends Component
{
    public int $closingDay;

    public function mount(): void
    {
        if (! auth()->user()?->can('rkap.closing.manage')) {
            abort(403, __('Anda tidak memiliki akses untuk halaman ini.'));
        }

        $this->closingDay = (int) Setting::get('rkap_closing_day', 10);
    }

    public function save(): void
    {
        $this->validate([
            'closingDay' => 'required|integer|between:1,31',
        ], [
            'closingDay.required' => 'Tanggal closing wajib diisi.',
            'closingDay.integer' => 'Tanggal closing harus berupa angka.',
            'closingDay.between' => 'Tanggal closing harus antara 1 sampai 31.',
        ]);

        Setting::set('rkap_closing_day', $this->closingDay);

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
