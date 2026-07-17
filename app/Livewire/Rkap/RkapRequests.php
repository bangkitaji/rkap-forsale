<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Livewire\Traits\WithCustomPagination;
use App\Models\WorkPlan;
use App\Models\Activity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RkapRequests extends Component
{
    use WithCustomPagination;

    // View state
    public string $activeTab = 'work_plan'; // 'work_plan' or 'activity'
    public string $search = '';

    // Request Form Modal State
    public bool $isModalOpen = false;
    public string $requestType = 'work_plan'; // 'work_plan' or 'activity'

    // Form inputs for Work Plan
    public string $wpTitle = '';

    // Form inputs for Activity
    public ?int $actWorkPlanId = null;
    public string $actTitle = '';
    public string $actDescription = '';

    // Approval Modal State
    public bool $isApproveModalOpen = false;
    public string $approveType = 'work_plan'; // 'work_plan' or 'activity'
    public ?int $approveId = null;
    public string $approvalCode = '';

    // Rejection Modal State
    public bool $isRejectModalOpen = false;
    public string $rejectType = 'work_plan'; // 'work_plan' or 'activity'
    public ?int $rejectId = null;
    public string $rejectionNote = '';

    // Filter State
    public bool $onlyPending = false;

    protected $queryString = [
        'activeTab' => ['except' => 'work_plan'],
        'search' => ['except' => ''],
        'onlyPending' => ['except' => false],
    ];

    public function mount(): void
    {
        $this->resetPage();
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();
        $this->search = '';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingOnlyPending(): void
    {
        $this->resetPage();
    }

    public function openRequestModal(): void
    {
        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function closeRequestModal(): void
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->wpTitle = '';
        $this->actWorkPlanId = null;
        $this->actTitle = '';
        $this->actDescription = '';
        $this->resetValidation();
    }

    public function submitRequest(): void
    {
        $user = Auth::user();

        if ($this->requestType === 'work_plan') {
            $this->validate([
                'wpTitle' => 'required|string|max:255',
            ], [
                'wpTitle.required' => 'Nama Program Kerja wajib diisi.',
            ]);

            WorkPlan::create([
                'code' => 'REQ-WP-' . strtoupper(\Illuminate\Support\Str::random(8)),
                'title' => $this->wpTitle,
                'approval_status' => 'pending',
                'requested_by_bureau_id' => $user->bureau_id,
            ]);

            session()->flash('message', __('Usulan Program Kerja berhasil diajukan.'));
        } else {
            $this->validate([
                'actWorkPlanId' => 'required|integer|exists:work_plans,id',
                'actTitle' => 'required|string|max:255',
                'actDescription' => 'nullable|string',
            ], [
                'actWorkPlanId.required' => 'Program Kerja harus dipilih.',
                'actTitle.required' => 'Nama Kegiatan wajib diisi.',
            ]);

            Activity::create([
                'work_plan_id' => $this->actWorkPlanId,
                'code' => 'REQ-ACT-' . strtoupper(\Illuminate\Support\Str::random(8)),
                'title' => $this->actTitle,
                'description' => $this->actDescription,
                'approval_status' => 'pending',
                'requested_by_bureau_id' => $user->bureau_id,
            ]);

            session()->flash('message', __('Usulan Kegiatan berhasil diajukan.'));
        }

        $this->closeRequestModal();
    }

    public function openApproveModal(string $type, int $id): void
    {
        if (!Auth::user()->can('masterdata.request.approve')) {
            abort(403, __('Unauthorized action.'));
        }

        $this->approveType = $type;
        $this->approveId = $id;

        if ($type === 'work_plan') {
            $maxCode = WorkPlan::where('approval_status', 'approved')
                ->pluck('code')
                ->filter(fn($c) => is_numeric($c))
                ->map(fn($c) => (int)$c)
                ->max();
            $this->approvalCode = $maxCode ? (string)($maxCode + 1) : '1000000001';
        } else {
            $maxCode = Activity::where('approval_status', 'approved')
                ->pluck('code')
                ->filter(fn($c) => is_numeric($c))
                ->map(fn($c) => (int)$c)
                ->max();
            $this->approvalCode = $maxCode ? (string)($maxCode + 1) : '2000000001';
        }

        $this->isApproveModalOpen = true;
        $this->resetValidation();
    }

    public function closeApproveModal(): void
    {
        $this->isApproveModalOpen = false;
        $this->approvalCode = '';
    }

    public function confirmApprove(): void
    {
        if (!Auth::user()->can('masterdata.request.approve')) {
            abort(403, __('Unauthorized action.'));
        }

        if ($this->approveType === 'work_plan') {
            $this->validate([
                'approvalCode' => [
                    'required',
                    'string',
                    'max:255',
                    'unique:work_plans,code',
                ],
            ], [
                'approvalCode.required' => 'Kode Program Kerja wajib diisi.',
                'approvalCode.unique' => 'Kode Program Kerja sudah digunakan.',
            ]);

            $wp = WorkPlan::findOrFail($this->approveId);
            $wp->update([
                'code' => $this->approvalCode,
                'approval_status' => 'approved',
            ]);
            session()->flash('message', "Usulan Program Kerja '{$wp->code}' berhasil disetujui.");
        } else {
            $this->validate([
                'approvalCode' => [
                    'required',
                    'string',
                    'max:255',
                    'unique:activities,code',
                ],
            ], [
                'approvalCode.required' => 'Kode Kegiatan wajib diisi.',
                'approvalCode.unique' => 'Kode Kegiatan sudah digunakan.',
            ]);

            $act = Activity::findOrFail($this->approveId);
            $act->update([
                'code' => $this->approvalCode,
                'approval_status' => 'approved',
            ]);
            session()->flash('message', "Usulan Kegiatan '{$act->code}' berhasil disetujui.");
        }

        $this->closeApproveModal();
    }

    public function openRejectModal(string $type, int $id): void
    {
        if (!Auth::user()->can('masterdata.request.approve')) {
            abort(403, __('Unauthorized action.'));
        }

        $this->rejectType = $type;
        $this->rejectId = $id;
        $this->rejectionNote = '';
        $this->isRejectModalOpen = true;
        $this->resetValidation();
    }

    public function closeRejectModal(): void
    {
        $this->isRejectModalOpen = false;
        $this->rejectId = null;
        $this->rejectionNote = '';
    }

    public function confirmReject(): void
    {
        if (!Auth::user()->can('masterdata.request.approve')) {
            abort(403, __('Unauthorized action.'));
        }

        $this->validate([
            'rejectionNote' => 'required|string|min:5|max:1000',
        ], [
            'rejectionNote.required' => 'Catatan penolakan wajib diisi agar pengusul mengetahui alasannya.',
            'rejectionNote.min'      => 'Catatan penolakan minimal 5 karakter.',
        ]);

        if ($this->rejectType === 'work_plan') {
            $wp = WorkPlan::findOrFail($this->rejectId);
            $wp->update([
                'approval_status' => 'rejected',
                'rejection_note'  => $this->rejectionNote,
            ]);
            session()->flash('message', "Usulan Program Kerja '{$wp->title}' telah ditolak.");
        } else {
            $act = Activity::findOrFail($this->rejectId);
            $act->update([
                'approval_status' => 'rejected',
                'rejection_note'  => $this->rejectionNote,
            ]);
            session()->flash('message', "Usulan Kegiatan '{$act->title}' telah ditolak.");
        }

        $this->closeRejectModal();
    }

    public function render()
    {
        $user = Auth::user();
        $isApprover = $user->can('masterdata.request.approve');

        // Fetch Work Plans options for activity creation (must be approved workplans)
        $approvedWorkPlans = WorkPlan::where('approval_status', 'approved')->orderBy('code')->get();

        if ($this->activeTab === 'work_plan') {
            $query = WorkPlan::query()
                ->with('requestedBureau')
                // Non-approvers only see their bureau's requests
                ->when(!$isApprover, fn($q) => $q->where('requested_by_bureau_id', $user->bureau_id))
                // Only show those that were actually requested (i.e. has requested_by_bureau_id or status is not 'approved' initially, wait: we want to show all requests. A requested work plan has requested_by_bureau_id set!)
                ->whereNotNull('requested_by_bureau_id')
                ->when($this->onlyPending, fn($q) => $q->where('approval_status', 'pending'))
                ->when($this->search, function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('code', 'like', '%' . $this->search . '%')
                            ->orWhere('title', 'like', '%' . $this->search . '%');
                    });
                })
                ->orderBy('created_at', 'desc');

            $requests = $query->paginate($this->perPage);
        } else {
            $query = Activity::query()
                ->with(['workPlan', 'requestedBureau'])
                // Non-approvers only see their bureau's requests
                ->when(!$isApprover, fn($q) => $q->where('requested_by_bureau_id', $user->bureau_id))
                ->whereNotNull('requested_by_bureau_id')
                ->when($this->onlyPending, fn($q) => $q->where('approval_status', 'pending'))
                ->when($this->search, function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('code', 'like', '%' . $this->search . '%')
                            ->orWhere('title', 'like', '%' . $this->search . '%')
                            ->orWhereHas('workPlan', function ($wpQ) {
                                $wpQ->where('code', 'like', '%' . $this->search . '%')
                                    ->orWhere('title', 'like', '%' . $this->search . '%');
                            });
                    });
                })
                ->orderBy('created_at', 'desc');

            $requests = $query->paginate($this->perPage);
        }

        return view('livewire.rkap.rkap-requests', [
            'requests' => $requests,
            'approvedWorkPlans' => $approvedWorkPlans,
            'isApprover' => $isApprover,
        ])->layout('layouts.contentNavbarLayout');
    }
}
