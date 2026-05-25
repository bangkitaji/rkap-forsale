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
        <h5 class="mb-0">Departemen</h5>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm w-auto" wire:model.live="perPage">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <div class="input-group input-group-sm w-auto">
                <span class="input-group-text"><i class="bx bx-search"></i></span>
                <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search departments...">
            </div>
            <button wire:click="create()" class="btn btn-primary btn-sm">
                <i class="bx bx-plus me-1"></i> Tambah Departemen
            </button>
        </div>
    </div>

    <div class="table-responsive text-nowrap">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Departemen</th>
                    <th>Direktorat</th>
                    <th>Biro</th>
                    <th>Verifikator</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody class="table-border-bottom-0">
                @forelse($departments as $dept)
                <tr>
                    <td><span class="badge bg-label-secondary">{{ $dept->code }}</span></td>
                    <td><strong>{{ $dept->name }}</strong></td>
                    <td>
                        <small class="text-muted">{{ $dept->directorate->name ?? '-' }}</small>
                    </td>
                    <td>{{ $dept->bureaus_count }}</td>
                    <td>
                        @if($dept->is_verifier)
                            <span class="badge bg-label-primary"><i class="bx bx-check-shield me-1"></i>Ya</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if($dept->is_active)
                            <span class="badge bg-label-success">Aktif</span>
                        @else
                            <span class="badge bg-label-danger">Non-Aktif</span>
                        @endif
                    </td>
                    <td>
                        <button wire:click="edit({{ $dept->id }})" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect" title="Edit">
                            <i class="bx bx-edit-alt"></i>
                        </button>
                        <button wire:click="delete({{ $dept->id }})" wire:confirm="Yakin ingin menghapus departemen ini?" class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect" title="Hapus">
                            <i class="bx bx-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="bx bx-sitemap bx-lg d-block mb-2"></i>
                        Belum ada data departemen.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $departments->links() }}
    </div>

    {{-- Modal --}}
    @if($isModalOpen)
    <div class="modal fade show" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bx bx-sitemap me-2"></i>
                        {{ $isEditMode ? 'Edit Departemen' : 'Tambah Departemen' }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeModal()"></button>
                </div>
                <form wire:submit.prevent="store">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="dept-directorate" class="form-label">Direktorat <span class="text-danger">*</span></label>
                            <select id="dept-directorate" class="form-select @error('directorate_id') is-invalid @enderror" wire:model="directorate_id">
                                <option value="">-- Pilih Direktorat --</option>
                                @foreach($directorates as $dir)
                                    <option value="{{ $dir->id }}">{{ $dir->name }}</option>
                                @endforeach
                            </select>
                            @error('directorate_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="dept-code" class="form-label">Kode <span class="text-danger">*</span></label>
                                <input type="text" id="dept-code" class="form-control text-uppercase @error('code') is-invalid @enderror"
                                    wire:model="code" placeholder="DEPT-XX" maxlength="20">
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-8 mb-3">
                                <label for="dept-name" class="form-label">Nama Departemen <span class="text-danger">*</span></label>
                                <input type="text" id="dept-name" class="form-control @error('name') is-invalid @enderror"
                                    wire:model="name" placeholder="Nama departemen" autofocus>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="dept-desc" class="form-label">Deskripsi</label>
                            <textarea id="dept-desc" class="form-control" wire:model="description" rows="2" placeholder="Deskripsi singkat..."></textarea>
                        </div>
                        <div class="d-flex gap-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="dept-verifier" wire:model="is_verifier">
                                <label class="form-check-label" for="dept-verifier">Departemen Verifikator RKAP</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="dept-active" wire:model="is_active">
                                <label class="form-check-label" for="dept-active">Aktif</label>
                            </div>
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
