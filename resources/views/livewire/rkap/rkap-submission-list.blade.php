<div>
    <div class="d-flex justify-content-between align-items-center py-3 mb-4">
        <h4 class="mb-0"><span class="text-muted fw-light">RKAP /</span> Pengajuan RKAP</h4>
        @can('rkap.create')
        <button wire:click="openPeriodSelector" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> Buat Pengajuan
        </button>
        @endcan
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

    {{-- Stats Cards --}}
    <div class="row g-4 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="avatar avatar-md flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-file bx-sm"></i></span>
                    </div>
                    <div>
                        <div class="fw-bold fs-4">{{ $stats['total'] }}</div>
                        <div class="text-muted small">Total Pengajuan</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="avatar avatar-md flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-info"><i class="bx bx-time bx-sm"></i></span>
                    </div>
                    <div>
                        <div class="fw-bold fs-4">{{ $stats['pending'] }}</div>
                        <div class="text-muted small">Menunggu Review</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="avatar avatar-md flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-warning"><i class="bx bx-revision bx-sm"></i></span>
                    </div>
                    <div>
                        <div class="fw-bold fs-4">{{ $stats['revision'] }}</div>
                        <div class="text-muted small">Perlu Revisi</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="avatar avatar-md flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-success"><i class="bx bx-check-circle bx-sm"></i></span>
                    </div>
                    <div>
                        <div class="fw-bold fs-4">{{ $stats['approved'] }}</div>
                        <div class="text-muted small">Disetujui</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-1">
                    <select class="form-select" wire:model.live="perPage" title="Baris per halaman">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" placeholder="Cari biro atau periode..." wire:model.live.debounce.300ms="search">
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select" wire:model.live="filterStatus">
                        <option value="">Semua Status</option>
                        <option value="draft">Draft</option>
                        <option value="submitted">Diajukan</option>
                        <option value="dept_review">Review Kadep</option>
                        <option value="dept_approved">Disetujui Kadep</option>
                        <option value="dept_revision">Revisi Kadep</option>
                        <option value="dir_review">Review Direksi</option>
                        <option value="dir_approved">Disetujui Direksi</option>
                        <option value="dir_revision">Revisi Direksi</option>
                        <option value="final_review">Verifikasi Final</option>
                        <option value="final_revision">Revisi Verifikator</option>
                        <option value="approved">Disetujui Final</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" wire:model.live="filterPeriod">
                        <option value="">Semua Periode</option>
                        @foreach($periods as $period)
                        <option value="{{ $period->id }}">{{ $period->title }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-primary text-white fw-semibold">
                    <tr>
                        <th>Biro</th>
                        <th>Departemen / Direktorat</th>
                        <th>Periode</th>
                        <th class="text-center">Versi</th>
                        <th class="text-end">Total Anggaran</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Diperbarui</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($submissions as $submission)
                    <tr>
                        <td>
                            <strong>{{ $submission->bureau->name ?? '-' }}</strong>
                            <div class="small text-muted">{{ $submission->bureau->code ?? '' }}</div>
                        </td>
                        <td>
                            <div class="small">{{ $submission->bureau->department->name ?? '-' }}</div>
                            <div class="small text-muted">{{ $submission->bureau->department->directorate->name ?? '-' }}</div>
                        </td>
                        <td><span class="badge bg-label-secondary">{{ $submission->period->title ?? '-' }}</span></td>
                        <td><span class="badge bg-label-info">v{{ $submission->current_version }}</span></td>
                        <td class="text-end">
                            <strong>Rp {{ number_format($submission->total_budget, 0, ',', '.') }}</strong>
                        </td>
                        <td>
                            <span class="badge bg-{{ $submission->status_color }}">{{ $submission->status_label }}</span>
                        </td>
                        <td><small class="text-muted">{{ $submission->updated_at->diffForHumans() }}</small></td>
                        <td>
                            <div class="d-flex gap-1">
                                @if($submission->canBeEditedBy(Auth::user()))
                                <a href="{{ route('rkap-submissions-edit', $submission->id) }}" class="btn btn-sm btn-icon btn-text-primary rounded-pill" title="Edit">
                                    <i class="bx bx-edit-alt"></i>
                                </a>
                                @endif
                                @if($submission->canBeReviewedBy(Auth::user()))
                                <a href="{{ route('rkap-submissions-review', $submission->id) }}" class="btn btn-sm btn-icon btn-text-success rounded-pill" title="Review">
                                    <i class="bx bx-check-circle"></i>
                                </a>
                                @endif
                                <a href="{{ route('rkap-submissions-review', $submission->id) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="Detail">
                                    <i class="bx bx-show"></i>
                                </a>
                                <a href="{{ route('rkap-submissions-versions', $submission->id) }}" class="btn btn-sm btn-icon btn-text-info rounded-pill" title="Riwayat Versi">
                                    <i class="bx bx-history"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bx bx-file bx-lg d-block mb-3"></i>
                            Tidak ada pengajuan ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $submissions->links() }}
        </div>
    </div>

    {{-- Period Selector Modal --}}
    @if($showPeriodSelector)
    <div class="modal fade show" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bx bx-calendar-plus me-2"></i>Pilih Periode RKAP</h5>
                    <button type="button" class="btn-close" wire:click="closePeriodSelector"></button>
                </div>
                <div class="modal-body">
                    @forelse($activePeriods as $period)
                    @php
                    $isSubmitted = in_array($period->id, $submittedPeriodIds);
                    @endphp
                    @if($isSubmitted)
                    <button class="btn btn-outline-secondary w-100 mb-2 text-start" disabled>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>{{ $period->title }}</strong>
                                <div class="small text-muted">{{ $period->year }} &bull; <span class="badge bg-label-danger">Sudah Diinput</span></div>
                            </div>
                            <i class="bx bx-check-double text-success"></i>
                        </div>
                    </button>
                    @else
                    <button wire:click="selectPeriod({{ $period->id }})" class="btn btn-outline-primary w-100 mb-2 text-start">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>{{ $period->title }}</strong>
                                <div class="small text-muted">{{ $period->year }} &bull; <span class="badge bg-label-success">{{ ucfirst($period->status) }}</span></div>
                            </div>
                            <i class="bx bx-chevron-right"></i>
                        </div>
                    </button>
                    @endif
                    @empty
                    <div class="text-center text-muted py-4">
                        <i class="bx bx-calendar-x bx-lg d-block mb-2"></i>
                        Tidak ada periode aktif.<br>
                        <small>Hubungi admin untuk mengaktifkan periode RKAP.</small>
                    </div>
                    @endforelse
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" wire:click="closePeriodSelector">Batal</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>