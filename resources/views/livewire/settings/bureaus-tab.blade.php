<div>
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
        <h5 class="mb-0">Biro</h5>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm w-auto" wire:model.live="perPage">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <div class="input-group input-group-sm w-auto">
                <span class="input-group-text"><i class="bx bx-search"></i></span>
                <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search bureaus...">
            </div>
            <button wire:click="create()" class="btn btn-primary btn-sm">
                <i class="bx bx-plus me-1"></i> Tambah Biro
            </button>
        </div>
    </div>

    <div class="table-responsive text-nowrap">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Biro</th>
                    <th>Departemen</th>
                    <th>Direktorat</th>
                    <th>Users</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody class="table-border-bottom-0">
                @forelse($bureaus as $bureau)
                <tr>
                    <td><span class="badge bg-label-secondary">{{ $bureau->code }}</span></td>
                    <td><strong>{{ $bureau->name }}</strong></td>
                    <td><small>{{ $bureau->department->name ?? '-' }}</small></td>
                    <td><small class="text-muted">{{ $bureau->department->directorate->name ?? '-' }}</small></td>
                    <td>{{ $bureau->users_count }}</td>
                    <td>
                        @if($bureau->is_active)
                            <span class="badge bg-label-success">Aktif</span>
                        @else
                            <span class="badge bg-label-danger">Non-Aktif</span>
                        @endif
                    </td>
                    <td>
                        <button wire:click="edit({{ $bureau->id }})" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect" title="Edit">
                            <i class="bx bx-edit-alt"></i>
                        </button>
                        <button wire:click="delete({{ $bureau->id }})" wire:confirm="Yakin ingin menghapus biro ini?" class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect" title="Hapus">
                            <i class="bx bx-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="bx bx-folder bx-lg d-block mb-2"></i>
                        Belum ada data biro.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $bureaus->links() }}
    </div>

    {{-- Modal --}}
    @if($isModalOpen)
    <div class="modal fade show" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bx bx-folder me-2"></i>
                        {{ $isEditMode ? 'Edit Biro' : 'Tambah Biro' }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeModal()"></button>
                </div>
                <form wire:submit.prevent="store">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="bro-department" class="form-label">Departemen <span class="text-danger">*</span></label>
                            <select id="bro-department" class="form-select @error('department_id') is-invalid @enderror" wire:model="department_id">
                                <option value="">-- Pilih Departemen --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }} ({{ $dept->directorate->name ?? '' }})</option>
                                @endforeach
                            </select>
                            @error('department_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="bro-code" class="form-label">Kode <span class="text-danger">*</span></label>
                                <input type="text" id="bro-code" class="form-control text-uppercase @error('code') is-invalid @enderror"
                                    wire:model="code" placeholder="BRO-XX" maxlength="20">
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-8 mb-3">
                                <label for="bro-name" class="form-label">Nama Biro <span class="text-danger">*</span></label>
                                <input type="text" id="bro-name" class="form-control @error('name') is-invalid @enderror"
                                    wire:model="name" placeholder="Nama biro" autofocus>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="bro-desc" class="form-label">Deskripsi</label>
                            <textarea id="bro-desc" class="form-control" wire:model="description" rows="2" placeholder="Deskripsi singkat..."></textarea>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="bro-active" wire:model="is_active">
                            <label class="form-check-label" for="bro-active">Aktif</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" wire:click="closeModal()">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <span wire:loading.remove><i class="bx bx-save me-1"></i> Simpan</span>
                            <span wire:loading><span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
