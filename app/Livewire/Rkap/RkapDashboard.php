<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapSubmission;
use App\Models\RkapPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RkapDashboard extends Component
{
    public function render()
    {
        $user = Auth::user();

        // Active period
        $activePeriod = RkapPeriod::active()->with('submissions')->latest()->first();

        // Stats
        $statsQuery = RkapSubmission::query();
        if ($user->isKepalaBiro()) {
            $statsQuery->where('bureau_id', $user->bureau_id);
        } elseif ($user->isKepalaDepartemen()) {
            $statsQuery->whereHas('bureau', fn($b) => $b->where('department_id', $user->department_id));
        } elseif ($user->isDireksi()) {
            $statsQuery->whereHas('bureau.department', fn($d) => $d->where('directorate_id', $user->directorate_id));
        }

        if ($activePeriod) {
            $statsQuery->where('rkap_period_id', $activePeriod->id);
        }

        $stats = [
            'total'      => (clone $statsQuery)->count(),
            'draft'      => (clone $statsQuery)->where('status', 'draft')->count(),
            'pending'    => (clone $statsQuery)->whereIn('status', ['submitted', 'dept_review', 'dir_review', 'final_review'])->count(),
            'revision'   => (clone $statsQuery)->whereIn('status', ['dept_revision', 'dir_revision', 'final_revision'])->count(),
            'approved'   => (clone $statsQuery)->where('status', 'approved')->count(),
            'total_budget' => (clone $statsQuery)->sum('total_budget'),
        ];

        // My pending actions (what needs MY attention)
        $myActions = collect();
        if ($user->isKepalaDepartemen()) {
            $myActions = RkapSubmission::with(['bureau', 'period'])
                ->whereHas('bureau', fn($b) => $b->where('department_id', $user->department_id))
                ->where('status', 'submitted')
                ->latest('updated_at')
                ->limit(5)->get();
        } elseif ($user->isDireksi()) {
            $myActions = RkapSubmission::with(['bureau.department', 'period'])
                ->whereHas('bureau.department', fn($d) => $d->where('directorate_id', $user->directorate_id))
                ->where('status', 'dept_approved')
                ->latest('updated_at')
                ->limit(5)->get();
        } elseif ($user->isVerifikator()) {
            $myActions = RkapSubmission::with(['bureau.department.directorate', 'period'])
                ->where('status', 'dir_approved')
                ->latest('updated_at')
                ->limit(5)->get();
        } elseif ($user->isKepalaBiro()) {
            $myActions = RkapSubmission::with(['period'])
                ->where('bureau_id', $user->bureau_id)
                ->whereIn('status', ['dept_revision', 'dir_revision', 'final_revision'])
                ->latest('updated_at')
                ->limit(5)->get();
        }

        // Budget by directorate (for admin/verifikator)
        $budgetByDirectorate = collect();
        if ($user->isAdmin() || $user->isVerifikator()) {
            $budgetByDirectorate = DB::table('rkap_submissions')
                ->join('bureaus', 'rkap_submissions.bureau_id', '=', 'bureaus.id')
                ->join('departments', 'bureaus.department_id', '=', 'departments.id')
                ->join('directorates', 'departments.directorate_id', '=', 'directorates.id')
                ->when($activePeriod, fn($q) => $q->where('rkap_submissions.rkap_period_id', $activePeriod->id))
                ->selectRaw('directorates.name as directorate, SUM(rkap_submissions.total_budget) as total')
                ->groupBy('directorates.id', 'directorates.name')
                ->get();
        }

        // Recent activity
        $recentActivity = RkapSubmission::with(['bureau', 'period'])
            ->when($user->isKepalaBiro(), fn($q) => $q->where('bureau_id', $user->bureau_id))
            ->when($user->isKepalaDepartemen(), fn($q) => $q->whereHas('bureau', fn($b) => $b->where('department_id', $user->department_id)))
            ->when($user->isDireksi(), fn($q) => $q->whereHas('bureau.department', fn($d) => $d->where('directorate_id', $user->directorate_id)))
            ->latest('updated_at')->limit(8)->get();

        return view('livewire.rkap.rkap-dashboard', compact(
            'activePeriod', 'stats', 'myActions', 'budgetByDirectorate', 'recentActivity'
        ))->layout('layouts.contentNavbarLayout');
    }
}
