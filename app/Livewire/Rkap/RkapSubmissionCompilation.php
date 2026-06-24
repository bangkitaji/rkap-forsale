<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapSubmission;
use App\Models\RkapPeriod;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RkapCompilationExport;

class RkapSubmissionCompilation extends Component
{
    public ?int $filterPeriod = null;

    public function mount(): void
    {
        // Default to the latest active period if available
        $latestActive = RkapPeriod::active()->orderByDesc('year')->first();
        if ($latestActive) {
            $this->filterPeriod = $latestActive->id;
        } else {
            $latest = RkapPeriod::orderByDesc('year')->first();
            if ($latest) {
                $this->filterPeriod = $latest->id;
            }
        }
    }

    /**
     * Build an Eloquent query scoped by the current user's role / permissions.
     * All queries are pre-filtered to status = approved.
     */
    private function buildQuery()
    {
        $user  = Auth::user();
        $query = RkapSubmission::with(['bureau.department.directorate', 'period'])
            ->where('status', 'approved')
            ->when($this->filterPeriod, fn($q) => $q->where('rkap_period_id', $this->filterPeriod));

        if ($user->can('rkap.compilation.all')) {
            // Verifikator / Admin — no restriction
        } elseif ($user->can('rkap.compilation.dir')) {
            // Direksi — own directorate only
            $query->whereHas(
                'bureau.department',
                fn($d) => $d->where('directorate_id', $user->directorate_id)
            );
        } elseif ($user->can('rkap.compilation.dept')) {
            // Kepala Departemen / Kepala Biro — own department only
            $deptId = $user->department_id;

            // For kepala_biro, department comes from their bureau
            if (!$deptId && $user->bureau_id) {
                $deptId = $user->bureau?->department_id;
            }

            $query->whereHas(
                'bureau',
                fn($b) => $b->where('department_id', $deptId)
            );
        } else {
            // No compilation permission — return empty
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function exportExcel()
    {
        $user = Auth::user();

        if (
            !$user->can('rkap.compilation.dept') &&
            !$user->can('rkap.compilation.dir') &&
            !$user->can('rkap.compilation.all')
        ) {
            session()->flash('error', 'Anda tidak memiliki akses untuk mengekspor kompilasi.');
            return null;
        }

        $submissions = $this->buildQuery()
            ->orderBy('bureau_id')
            ->get();

        if ($submissions->isEmpty()) {
            session()->flash('error', 'Tidak ada data kompilasi untuk diekspor.');
            return null;
        }

        $period      = $this->filterPeriod ? RkapPeriod::find($this->filterPeriod) : null;
        $periodTitle = $period ? $period->title : 'all-periods';
        $filename    = 'kompilasi-rkap-' . str($periodTitle)->slug() . '-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new RkapCompilationExport($submissions, $periodTitle), $filename);
    }

    public function render()
    {
        $user       = Auth::user();
        $periods    = RkapPeriod::orderByDesc('year')->get();
        $submissions = $this->buildQuery()
            ->orderBy('bureau_id')
            ->get();

        // Build map of previous approved submission totals and realizations for all levels
        $prevDataMap = [
            'submissions' => [],
            'departments' => [],
            'directorates' => [],
            'grand_total' => [
                'budget' => 0.0,
                'realization' => 0.0,
                'projection' => 0.0,
                'period_title' => null,
            ],
        ];

        foreach ($submissions as $sub) {
            $bureauId = $sub->bureau_id;
            $year = $sub->period?->year ?? 0;

            $prevSubmission = RkapSubmission::with([
                    'workPlans.budgetItems.realizations',
                    'workPlans.budgetItems.projections',
                    'period',
                ])
                ->where('bureau_id', $bureauId)
                ->where('id', '!=', $sub->id)
                ->where('status', 'approved')
                ->whereHas('period', fn($q) => $q->where('year', '<', $year))
                ->orderByDesc(\Illuminate\Support\Facades\DB::raw('(SELECT year FROM rkap_periods WHERE rkap_periods.id = rkap_submissions.rkap_period_id)'))
                ->first();

            if ($prevSubmission) {
                $totalRealization = 0;
                $totalProjection = 0;
                foreach ($prevSubmission->workPlans as $wp) {
                    foreach ($wp->budgetItems as $bi) {
                        $totalRealization += (float) $bi->realizations->sum('amount');
                        $totalProjection += (float) $bi->projection;
                    }
                }

                $prevDataMap['submissions'][$sub->id] = [
                    'period_title' => $prevSubmission->period->title,
                    'budget' => (float) $prevSubmission->total_budget,
                    'realization' => $totalRealization,
                    'projection' => $totalProjection,
                ];

                // Add to department total
                $deptKey = $sub->bureau?->department?->name ?? 'Lainnya';
                if (!isset($prevDataMap['departments'][$deptKey])) {
                    $prevDataMap['departments'][$deptKey] = [
                        'budget' => 0.0,
                        'realization' => 0.0,
                        'projection' => 0.0,
                        'period_title' => $prevSubmission->period->title,
                    ];
                }
                $prevDataMap['departments'][$deptKey]['budget'] += (float) $prevSubmission->total_budget;
                $prevDataMap['departments'][$deptKey]['realization'] += $totalRealization;
                $prevDataMap['departments'][$deptKey]['projection'] += $totalProjection;

                // Add to directorate total
                $dirKey = $sub->bureau?->department?->directorate?->name ?? 'Lainnya';
                if (!isset($prevDataMap['directorates'][$dirKey])) {
                    $prevDataMap['directorates'][$dirKey] = [
                        'budget' => 0.0,
                        'realization' => 0.0,
                        'projection' => 0.0,
                        'period_title' => $prevSubmission->period->title,
                    ];
                }
                $prevDataMap['directorates'][$dirKey]['budget'] += (float) $prevSubmission->total_budget;
                $prevDataMap['directorates'][$dirKey]['realization'] += $totalRealization;
                $prevDataMap['directorates'][$dirKey]['projection'] += $totalProjection;
            }
        }

        foreach ($prevDataMap['submissions'] as $subData) {
            $prevDataMap['grand_total']['budget'] += $subData['budget'];
            $prevDataMap['grand_total']['realization'] += $subData['realization'];
            $prevDataMap['grand_total']['projection'] += $subData['projection'];
            if (!$prevDataMap['grand_total']['period_title']) {
                $prevDataMap['grand_total']['period_title'] = $subData['period_title'];
            }
        }

        // Stats
        $grandTotal     = $submissions->sum('total_budget');
        $bureauCount    = $submissions->pluck('bureau_id')->unique()->count();
        $deptCount      = $submissions->pluck('bureau.department_id')->unique()->count();
        $dirCount       = $submissions->pluck('bureau.department.directorate_id')->unique()->count();

        // Group for display: directorate → department → bureau submissions
        $grouped = $submissions
            ->groupBy(fn($s) => $s->bureau?->department?->directorate?->name ?? 'Lainnya')
            ->map(fn($dirSubmissions) =>
                $dirSubmissions->groupBy(fn($s) => $s->bureau?->department?->name ?? 'Lainnya')
            );

        $selectedPeriod = $this->filterPeriod ? RkapPeriod::find($this->filterPeriod) : null;

        $canSeeAll = $user->can('rkap.compilation.all');
        $canSeeDir = $user->can('rkap.compilation.dir');

        return view('livewire.rkap.rkap-submission-compilation', [
            'periods'        => $periods,
            'submissions'    => $submissions,
            'grouped'        => $grouped,
            'grandTotal'     => $grandTotal,
            'bureauCount'    => $bureauCount,
            'deptCount'      => $deptCount,
            'dirCount'       => $dirCount,
            'selectedPeriod' => $selectedPeriod,
            'canSeeAll'      => $canSeeAll,
            'canSeeDir'      => $canSeeDir,
            'prevDataMap'    => $prevDataMap,
        ])->layout('layouts.contentNavbarLayout');
    }
}
