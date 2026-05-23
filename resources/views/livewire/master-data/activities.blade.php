<div>
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Master Data /</span> Activity
    </h4>

    <div class="card">
        <div class="card-body">
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Activities</h5>
        <div class="d-flex gap-2">
            <div class="input-group input-group-sm w-auto">
                <span class="input-group-text"><i class="bx bx-search"></i></span>
                <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search activities...">
            </div>
            <button wire:click="create()" class="btn btn-primary btn-sm">
                <i class="bx bx-plus me-1"></i> Add Activity
            </button>
        </div>
    </div>

    <div class="table-responsive text-nowrap">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Work Plan</th>
                    <th>Code</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody class="table-border-bottom-0">
                @forelse($activities as $activity)
                <tr>
                    <td>{{ $activity->workPlan ? $activity->workPlan->code . ' - ' . $activity->workPlan->title : '-' }}</td>
                    <td><strong>{{ $activity->code }}</strong></td>
                    <td>{{ $activity->title }}</td>
                    <td>{{ Str::limit($activity->description, 50) }}</td>
                    <td>
                        <button wire:click="edit({{ $activity->id }})" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect">
                            <i class="bx bx-edit-alt"></i>
                        </button>
                        <button wire:click="delete({{ $activity->id }})" wire:confirm="Are you sure you want to delete this activity?" class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect">
                            <i class="bx bx-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center">No activities found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $activities->links() }}
    </div>
        </div>
    </div>

    <!-- Modal -->
    @if($isModalOpen)
    <div class="modal fade show" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isEditMode ? 'Edit Activity' : 'Add New Activity' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal()"></button>
                </div>
                <form wire:submit.prevent="store">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="work_plan_id" class="form-label">Work Plan</label>
                            <select id="work_plan_id" class="form-select @error('work_plan_id') is-invalid @enderror" wire:model="work_plan_id">
                                <option value="">-- Select Work Plan --</option>
                                @foreach($workPlans as $wp)
                                    <option value="{{ $wp->id }}">{{ $wp->code }} - {{ $wp->title }}</option>
                                @endforeach
                            </select>
                            @error('work_plan_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="code" class="form-label">Code</label>
                            <input type="text" id="code" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder="e.g. ACT-01" autofocus>
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" id="title" class="form-control @error('title') is-invalid @enderror" wire:model="title" placeholder="Activity Title">
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea id="description" class="form-control @error('description') is-invalid @enderror" wire:model="description" rows="3" placeholder="Activity Description"></textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" wire:click="closeModal()">Close</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
