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
        <h5 class="mb-0">{{ __('Direktorat') }}</h5>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm w-auto" wire:model.live="perPage">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <div class="input-group input-group-sm w-auto">
                <span class="input-group-text"><i class="bx bx-search"></i></span>
                <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search directorates...') }}">
            </div>
            <button wire:click="create()" class="btn btn-primary btn-sm">
                <i class="bx bx-plus me-1"></i> {{ __('Tambah Direktorat') }}
            </button>
        </div>
    </div>

    <div class="table-responsive text-nowrap">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>{{ __('Nama Direktorat') }}</th>
                    <th>{{ __('Departemen') }}</th>
                    <th>Users</th>
                    <th>{{ __('Status') }}</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody class="table-border-bottom-0">
                @forelse($directorates as $directorate)
                <tr>
                    <td><span class="badge bg-label-secondary">{{ $directorate->code }}</span></td>
                    <td><strong>{{ $directorate->name }}</strong></td>
                    <td>{{ $directorate->departments_count }}</td>
                    <td>{{ $directorate->users_count }}</td>
                    <td>
                        @if($directorate->is_active)
                        <span class="badge bg-label-success">{{ __('Aktif') }}</span>
                        @else
                        <span class="badge bg-label-danger">{{ __('Non-Aktif') }}</span>
                        @endif
                    </td>
                    <td>
                        <button wire:click="edit({{ $directorate->id }})" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect" title="Edit">
                            <i class="bx bx-edit-alt"></i>
                        </button>
                        <button wire:click="toggleActive({{ $directorate->id }})" class="btn btn-sm btn-icon btn-text-warning rounded-pill waves-effect" title="Toggle Status">
                            <i class="bx bx-toggle-{{ $directorate->is_active ? 'right' : 'left' }}"></i>
                        </button>
                        <button wire:click="delete({{ $directorate->id }})" wire:confirm="Yakin ingin menghapus direktorat ini?" class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect" title="Hapus">
                            <i class="bx bx-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="bx bx-building bx-lg d-block mb-2"></i>
                        Belum ada data direktorat.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $directorates->links() }}
    </div>

    {{-- Modal --}}
    @if($isModalOpen)
    <div class="modal fade show rkap-modal-show" tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bx bx-building me-2"></i>
                        {{ $isEditMode ? __('Edit Direktorat') : __('Tambah Direktorat') }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeModal()"></button>
                </div>
                <form wire:submit.prevent="store">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="dir-code" class="form-label">Kode <span class="text-danger">*</span></label>
                                <input type="text" id="dir-code" class="form-control text-uppercase @error('code') is-invalid @enderror"
                                    wire:model="code" placeholder="DIR-XX" maxlength="20">
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-8 mb-3">
                                <label for="dir-name" class="form-label">Nama Direktorat <span class="text-danger">*</span></label>
                                <input type="text" id="dir-name" class="form-control @error('name') is-invalid @enderror"
                                    wire:model="name" placeholder="{{ __('Nama direktorat') }}" autofocus>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="dir-desc" class="form-label">{{ __('Deskripsi') }}</label>
                            <textarea id="dir-desc" class="form-control @error('description') is-invalid @enderror"
                                wire:model="description" rows="3" placeholder="{{ __('Deskripsi singkat direktorat...') }}"></textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="dir-active" wire:model="is_active">
                            <label class="form-check-label" for="dir-active">{{ __('Aktif') }}</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" wire:click="closeModal()">{{ __('Batal') }}</button>
                        <button type="submit" class="btn btn-primary">
                            <span wire:loading.remove><i class="bx bx-save me-1"></i> {{ __('Simpan') }}</span>
                            <span wire:loading><span class="spinner-border spinner-border-sm me-1"></span> {{ __('Menyimpan...') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>