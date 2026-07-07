<div>
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Settings /</span> Satuan Reference
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
                <h5 class="mb-0">Reference Satuan</h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm w-auto" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <div class="input-group input-group-sm w-auto">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search Satuan...">
                    </div>
                    <button wire:click="create()" class="btn btn-primary btn-sm">
                        <i class="bx bx-plus me-1"></i> Add Satuan
                    </button>
                </div>
            </div>

            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($satuans as $satuan)
                        <tr>
                            <td><strong>{{ $satuan->name }}</strong></td>
                            <td class="text-wrap rkap-mw-400">
                                {{ $satuan->description ?? '-' }}
                            </td>
                            <td>
                                <button wire:click="edit({{ $satuan->id }})" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect">
                                    <i class="bx bx-edit-alt"></i>
                                </button>
                                <button wire:click="delete({{ $satuan->id }})" wire:confirm="Apakah Anda yakin ingin menghapus Satuan ini?" class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center">No Satuan records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $satuans->links() }}
            </div>

        </div>
    </div>

    {{-- Modal --}}
    @if($isModalOpen)
    <div class="modal fade show rkap-modal-show" tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isEditMode ? 'Edit Satuan' : 'Add New Satuan' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal()"></button>
                </div>
                <form wire:submit.prevent="store">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="satuan-name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" id="satuan-name" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder="e.g. Pcs, Set, Paket" autofocus>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="satuan-description" class="form-label">Description</label>
                            <textarea id="satuan-description" class="form-control @error('description') is-invalid @enderror" wire:model="description" rows="3" placeholder="Optional description"></textarea>
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