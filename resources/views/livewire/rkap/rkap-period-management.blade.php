<div>
    <div class="d-flex justify-content-between align-items-center py-3 mb-4">
        <h4 class="mb-0"><span class="text-muted fw-light">RKAP /</span> Periode Anggaran</h4>
        <button wire:click="create()" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> Buat Periode
        </button>
    </div>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        @forelse($periods as $period)
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="badge bg-label-secondary fs-6 mb-1">{{ $period->year }}</span>
                            <h5 class="card-title mb-0">{{ $period->title }}</h5>
                        </div>
                        <span class="badge bg-{{
                            match($period->status) {
                                'draft' => 'secondary',
                                'open' => 'success',
                                'closed' => 'warning',
                                'finalized' => 'primary',
                                default => 'secondary'
                            }
                        }}">
                            {{ ucfirst($period->status) }}
                        </span>
                    </div>

                    @if($period->description)
                        <p class="text-muted small mb-3">{{ $period->description }}</p>
                    @endif

                    <div class="d-flex gap-3 mb-3 small text-muted">
                        <span><i class="bx bx-calendar me-1"></i>
                            {{ $period->submission_start?->format('d M Y') ?? '-' }} —
                            {{ $period->submission_end?->format('d M Y') ?? '-' }}
                        </span>
                    </div>

                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="bx bx-file text-primary"></i>
                        <span class="fw-semibold">{{ $period->submissions_count }}</span>
                        <span class="text-muted small">pengajuan</span>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button wire:click="edit({{ $period->id }})" class="btn btn-sm btn-label-secondary">
                            <i class="bx bx-edit me-1"></i> Edit
                        </button>
                        @if($period->status === 'draft')
                            <button wire:click="openPeriod({{ $period->id }})" wire:confirm="Buka periode ini untuk pengajuan?" class="btn btn-sm btn-success">
                                <i class="bx bx-lock-open me-1"></i> Buka
                            </button>
                        @elseif($period->status === 'open')
                            <button wire:click="closePeriod({{ $period->id }})" wire:confirm="Tutup periode ini?" class="btn btn-sm btn-warning">
                                <i class="bx bx-lock me-1"></i> Tutup
                            </button>
                        @elseif($period->status === 'closed')
                            <button wire:click="finalizePeriod({{ $period->id }})" wire:confirm="Finalisasi periode ini?" class="btn btn-sm btn-primary">
                                <i class="bx bx-check-double me-1"></i> Finalisasi
                            </button>
                        @endif
                        @if($period->submissions_count == 0)
                            <button wire:click="delete({{ $period->id }})" wire:confirm="Hapus periode ini?" class="btn btn-sm btn-label-danger">
                                <i class="bx bx-trash"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5 text-muted">
                    <i class="bx bx-calendar bx-lg d-block mb-3"></i>
                    <p>Belum ada periode RKAP. Klik "Buat Periode" untuk memulai.</p>
                </div>
            </div>
        </div>
        @endforelse
    </div>

    {{-- Modal --}}
    @if($isModalOpen)
    <div class="modal fade show" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bx bx-calendar me-2"></i>{{ $isEditMode ? 'Edit Periode' : 'Buat Periode RKAP' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal()"></button>
                </div>
                <form wire:submit.prevent="store">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Tahun <span class="text-danger">*</span></label>
                                <input type="number" class="form-control @error('year') is-invalid @enderror" wire:model="year" min="2000" max="2100">
                                @error('year') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-9 mb-3">
                                <label class="form-label">Judul Periode <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('title') is-invalid @enderror" wire:model="title" placeholder="RKAP {{ date('Y') + 1 }}">
                                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" wire:model="description" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" wire:model="status">
                                    <option value="draft">Draft</option>
                                    <option value="open">Open</option>
                                    <option value="closed">Closed</option>
                                    <option value="finalized">Finalized</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Tanggal Buka</label>
                                <input type="date" class="form-control @error('submission_start') is-invalid @enderror" wire:model="submission_start">
                                @error('submission_start') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Tanggal Tutup</label>
                                <input type="date" class="form-control @error('submission_end') is-invalid @enderror" wire:model="submission_end">
                                @error('submission_end') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
