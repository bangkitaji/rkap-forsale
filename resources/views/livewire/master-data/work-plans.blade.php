<div>
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Master Data /</span> Work Plan
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
                <h5 class="mb-0">Work Plans</h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm w-auto" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <div class="input-group input-group-sm w-auto">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search work plans...">
                    </div>
                    @can('masterdata.workplan.manage')
                    <button wire:click="create()" class="btn btn-primary btn-sm">
                        <i class="bx bx-plus me-1"></i> Add Work Plan
                    </button>
                    <button wire:click="openUploadModal()" class="btn btn-info btn-sm">
                        <i class="bx bx-upload me-1"></i> Import Excel
                    </button>
                    @endcan
                </div>
            </div>

            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th wire:click="sort('code')" class="rkap-cursor-pointer rkap-user-select-none text-nowrap">
                                Code
                                @if($sortBy === 'code')
                                <i class="bx bx-chevron-{{ $sortDir === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                @else
                                <i class="bx bx-sort ms-1 text-muted opacity-50"></i>
                                @endif
                            </th>
                            <th wire:click="sort('title')" class="rkap-cursor-pointer rkap-user-select-none text-nowrap">
                                Title
                                @if($sortBy === 'title')
                                <i class="bx bx-chevron-{{ $sortDir === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                @else
                                <i class="bx bx-sort ms-1 text-muted opacity-50"></i>
                                @endif
                            </th>
                            @can('masterdata.workplan.manage')
                            <th>Actions</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($workPlans as $workPlan)
                        <tr>
                            <td><strong>{{ $workPlan->code }}</strong></td>
                            <td>{{ $workPlan->title }}</td>
                            @can('masterdata.workplan.manage')
                            <td>
                                <button wire:click="edit({{ $workPlan->id }})" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect">
                                    <i class="bx bx-edit-alt"></i>
                                </button>
                                <button wire:click="delete({{ $workPlan->id }})" wire:confirm="Are you sure you want to delete this work plan?" class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </td>
                            @endcan
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->can('masterdata.workplan.manage') ? 3 : 2 }}" class="text-center">No work plans found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $workPlans->links() }}
            </div>
        </div>
    </div>

    <!-- Modal -->
    @if($isModalOpen)
    <div class="modal fade show rkap-modal-show" tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isEditMode ? 'Edit Work Plan' : 'Add New Work Plan' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal()"></button>
                </div>
                <form wire:submit.prevent="store">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="code" class="form-label">Code</label>
                            <input type="text" id="code" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder="e.g. WP-01" autofocus>
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" id="title" class="form-control @error('title') is-invalid @enderror" wire:model="title" placeholder="Work Plan Title">
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
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

    <!-- Upload Modal -->
    @if($isUploadModalOpen)
    <div class="modal fade show rkap-modal-show" tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Work Plans from Excel</h5>
                    <button type="button" class="btn-close" wire:click="closeUploadModal()"></button>
                </div>
                <form wire:submit.prevent="importExcel">
                    <div class="modal-body">
                        <p class="mb-3">Upload an Excel file to import work plans. <a href="{{ route('download-workplan-template') }}" class="btn btn-sm btn-link p-0">Download template</a></p>

                        <div class="mb-3">
                            <label for="uploadedFile" class="form-label">Excel File</label>
                            <input type="file" id="uploadedFile" class="form-control @error('uploadedFile') is-invalid @enderror" wire:model="uploadedFile" accept=".xlsx,.xls,.csv">
                            @error('uploadedFile') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        @if($importMessage)
                        <div class="alert alert-{{ $importStatus === 'success' ? 'success' : 'danger' }} alert-dismissible" role="alert">
                            <pre class="mb-0 rkap-font-0875 rkap-ws-pre-wrap">{{ $importMessage }}</pre>
                        </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" wire:click="closeUploadModal()">Close</button>
                        <button type="submit" class="btn btn-primary" @if($uploadedFile===null) disabled @endif>
                            <i class="bx bx-upload me-1"></i> Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>