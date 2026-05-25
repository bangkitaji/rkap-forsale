<div>
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Master Data /</span> COA
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
                <h5 class="mb-0">Chart of Accounts (COA)</h5>
                <div class="d-flex gap-2">
                    <div class="input-group input-group-sm w-auto">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search COA...">
                    </div>
                    <button wire:click="create()" class="btn btn-primary btn-sm">
                        <i class="bx bx-plus me-1"></i> Add COA
                    </button>
                </div>
            </div>

            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($coas as $coa)
                        <tr>
                            <td><strong>{{ $coa->code }}</strong></td>
                            <td>{{ $coa->title }}</td>
                            <td class="text-wrap" style="max-width: 300px;">
                                {{ $coa->description ?? '-' }}
                            </td>
                            <td>
                                <button wire:click="edit({{ $coa->id }})" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect">
                                    <i class="bx bx-edit-alt"></i>
                                </button>
                                <button wire:click="delete({{ $coa->id }})" wire:confirm="Are you sure you want to delete this COA?" class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center">No COA records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $coas->links() }}
            </div>

        </div>
    </div>

    {{-- Modal --}}
    @if($isModalOpen)
    <div class="modal fade show" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isEditMode ? 'Edit COA' : 'Add New COA' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal()"></button>
                </div>
                <form wire:submit.prevent="store">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="coa-code" class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" id="coa-code" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder="e.g. 1-1001" autofocus>
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="coa-title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" id="coa-title" class="form-control @error('title') is-invalid @enderror" wire:model="title" placeholder="Account Title">
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="coa-description" class="form-label">Description</label>
                            <textarea id="coa-description" class="form-control @error('description') is-invalid @enderror" wire:model="description" rows="3" placeholder="Optional description"></textarea>
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
