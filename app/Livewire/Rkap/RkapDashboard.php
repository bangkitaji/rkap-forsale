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
            'pending'    => (clone $statsQuery)->whereIn('status', ['submitted', 'dept_review', 'dir_review', 'final_review', 'verifikator_approved', 'pdir_review'])->count(),
            'revision'   => (clone $statsQuery)->whereIn('status', ['dept_revision', 'dir_revision', 'final_revision', 'pdir_revision'])->count(),
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
            if ($user->isDirekturFinance()) {
                $myActions = RkapSubmission::with(['bureau.department.directorate', 'period'])
                    ->where(function ($query) use ($user) {
                        $query->where(function ($q1) use ($user) {
                            $q1->where('status', 'dir_review')
                               ->whereHas('bureau.department', fn($d) => $d->where('directorate_id', $user->directorate_id));
                        })->orWhere(function ($q2) {
                            $q2->where('status', 'pdir_review')
                               ->whereDoesntHave('approvals', function ($q) {
                                   $q->whereColumn('version_number', 'rkap_submissions.current_version')
                                     ->where('role', 'direktur_keuangan')
                                     ->where('action', 'approved');
                               });
                        });
                    })
                    ->latest('updated_at')
                    ->limit(5)->get();
            } else {
                $myActions = RkapSubmission::with(['bureau.department', 'period'])
                    ->whereHas('bureau.department', fn($d) => $d->where('directorate_id', $user->directorate_id))
                    ->where('status', 'dir_review')
                    ->latest('updated_at')
                    ->limit(5)->get();
            }
        } elseif ($user->isVerifikator()) {
            $myActions = RkapSubmission::with(['bureau.department.directorate', 'period'])
                ->where('status', 'final_review')
                ->latest('updated_at')
                ->limit(5)->get();
        } elseif ($user->isPresidentDirector()) {
            $myActions = RkapSubmission::with(['bureau.department.directorate', 'period'])
                ->where('status', 'pdir_review')
                ->whereDoesntHave('approvals', function ($q) {
                    $q->whereColumn('version_number', 'rkap_submissions.current_version')
                      ->where('role', 'direktur_utama')
                      ->where('action', 'approved');
                })
                ->latest('updated_at')
                ->limit(5)->get();
        } elseif ($user->isKepalaBiro()) {
            $myActions = RkapSubmission::with(['bureau.department', 'period'])
                ->where('bureau_id', $user->bureau_id)
                ->whereIn('status', ['draft', 'dept_revision', 'dir_revision', 'final_revision', 'pdir_revision'])
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

        // Submissions by status tabs (for President Director, Verifikator, Admin)
        $submissionsByStatus = [
            'verified' => collect(),
            'review'   => collect(),
            'draft'    => collect(),
        ];
        $departmentsSubmissions = [];
        $verifiedDeptCount = 0;
        $totalDeptCount = 0;

        if ($user->isPresidentDirector() || $user->isDirekturFinance() || $user->isVerifikator() || $user->isAdmin()) {
            // Tabulated submissions
            $allSubmissions = RkapSubmission::with(['bureau.department.directorate', 'period', 'creator'])
                ->when($activePeriod, fn($q) => $q->where('rkap_period_id', $activePeriod->id))
                ->get();

            $submissionsByStatus['verified'] = $allSubmissions->filter(fn($s) => in_array($s->status, ['pdir_review', 'approved']));
            $submissionsByStatus['review']   = $allSubmissions->filter(fn($s) => in_array($s->status, ['submitted', 'dept_review', 'dept_approved', 'dir_review', 'dir_approved', 'final_review']));
            $submissionsByStatus['draft']    = $allSubmissions->filter(fn($s) => in_array($s->status, ['draft', 'dept_revision', 'dir_revision', 'final_revision', 'pdir_revision']));

            // Department compilation
            $departments = \App\Models\Department::active()->orderBy('code')->get();
            $totalDeptCount = $departments->count();

            foreach ($departments as $dept) {
                $submissions = RkapSubmission::whereHas('bureau', fn($b) => $b->where('department_id', $dept->id))
                    ->when($activePeriod, fn($q) => $q->where('rkap_period_id', $activePeriod->id))
                    ->with(['bureau', 'creator'])
                    ->get();

                $status = 'Belum Mengajukan';
                if ($submissions->isNotEmpty()) {
                    $statuses = $submissions->pluck('status')->unique();
                    
                    if ($statuses->contains(fn($s) => in_array($s, ['draft', 'dept_revision', 'dir_revision', 'final_revision', 'pdir_revision']))) {
                        $status = 'Draf / Revisi';
                    } elseif ($statuses->every(fn($s) => in_array($s, ['pdir_review', 'approved']))) {
                        $status = 'Terverifikasi';
                        $verifiedDeptCount++;
                    } else {
                        $status = 'Sedang Direview';
                    }
                }

                $departmentsSubmissions[] = [
                    'department' => $dept,
                    'submissions' => $submissions,
                    'total_budget' => $submissions->sum('total_budget'),
                    'status' => $status,
                ];
            }
        }

        // Recent activity
        $recentActivity = RkapSubmission::with(['bureau', 'period'])
            ->when($user->isKepalaBiro(), fn($q) => $q->where('bureau_id', $user->bureau_id))
            ->when($user->isKepalaDepartemen(), fn($q) => $q->whereHas('bureau', fn($b) => $b->where('department_id', $user->department_id)))
            ->when($user->isDireksi(), fn($q) => $q->whereHas('bureau.department', fn($d) => $d->where('directorate_id', $user->directorate_id)))
            ->latest('updated_at')->limit(8)->get();

        return view('livewire.rkap.rkap-dashboard', compact(
            'activePeriod', 'stats', 'myActions', 'budgetByDirectorate', 'recentActivity',
            'submissionsByStatus', 'departmentsSubmissions', 'verifiedDeptCount', 'totalDeptCount'
        ))->layout('layouts.contentNavbarLayout');
    }
}
