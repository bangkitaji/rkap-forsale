<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapSubmission;
use App\Models\RkapPeriod;
use Illuminate\Support\Facades\Auth;

class RkapSubmissionList extends Component
{
    public string $search = '';
    public string $filterStatus = '';
    public ?int $filterPeriod = null;
    public bool $showPeriodSelector = false;

    public function openPeriodSelector(): void
    {
        $this->showPeriodSelector = true;
    }

    public function closePeriodSelector(): void
    {
        $this->showPeriodSelector = false;
    }

    public function selectPeriod(int $periodId): void
    {
        $this->redirectRoute('rkap-submissions-create', ['periodId' => $periodId]);
    }

    public function render()
    {
        $user = Auth::user();

        $query = RkapSubmission::with(['bureau.department.directorate', 'period', 'creator'])
            ->when($this->search, function ($q) {
                $q->whereHas('bureau', fn($b) => $b->where('name', 'like', "%{$this->search}%"))
                  ->orWhereHas('period', fn($p) => $p->where('title', 'like', "%{$this->search}%"));
            })
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPeriod, fn($q) => $q->where('rkap_period_id', $this->filterPeriod));

        // Role-based scoping
        if ($user->isKepalaBiro()) {
            $query->where('bureau_id', $user->bureau_id);
        } elseif ($user->isKepalaDepartemen()) {
            $query->whereHas('bureau', fn($b) => $b->where('department_id', $user->department_id));
        } elseif ($user->isDireksi()) {
            $query->whereHas('bureau.department', fn($d) => $d->where('directorate_id', $user->directorate_id));
        }
        // Verifikator and Admin see all

        $submissions = $query->orderByDesc('updated_at')->paginate(15);

        // Stats
        $statsQuery = RkapSubmission::query();
        if ($user->isKepalaBiro()) {
            $statsQuery->where('bureau_id', $user->bureau_id);
        } elseif ($user->isKepalaDepartemen()) {
            $statsQuery->whereHas('bureau', fn($b) => $b->where('department_id', $user->department_id));
        } elseif ($user->isDireksi()) {
            $statsQuery->whereHas('bureau.department', fn($d) => $d->where('directorate_id', $user->directorate_id));
        }

        $stats = [
            'total'       => (clone $statsQuery)->count(),
            'pending'     => (clone $statsQuery)->whereIn('status', ['submitted', 'dept_review', 'dir_review', 'final_review'])->count(),
            'revision'    => (clone $statsQuery)->whereIn('status', ['dept_revision', 'dir_revision', 'final_revision'])->count(),
            'approved'    => (clone $statsQuery)->where('status', 'approved')->count(),
        ];

        $periods = RkapPeriod::orderByDesc('year')->get();
        $activePeriods = RkapPeriod::active()->orderByDesc('year')->get();

        return view('livewire.rkap.rkap-submission-list', [
            'submissions'   => $submissions,
            'periods'       => $periods,
            'activePeriods' => $activePeriods,
            'stats'         => $stats,
        ])->layout('layouts.contentNavbarLayout');
    }
}
