<div x-data="{
    showToast: @js(session()->has('message') || session()->has('error')),
    toastMessage: @js(session('message') ?: session('error') ?: ''),
    toastType: @js(session()->has('error') ? 'danger' : 'success')
}" x-init="if (showToast) { setTimeout(() => showToast = false, 5000); }">
  <style>
    /* Custom CSS Tooltip styling */
    .has-tooltip {
      position: relative;
      cursor: help;
      display: inline-block;
    }

    .custom-tooltip-content {
      visibility: hidden;
      width: 520px;
      background-color: #2f3349;
      color: #ffffff;
      text-align: left;
      border-radius: 6px;
      padding: 10px;
      position: absolute;
      z-index: 1080;
      top: 110%;
      /* Position below the element */
      bottom: auto;
      left: 50%;
      transform: translateX(-50%);
      opacity: 0;
      transition: opacity 0.2s ease-in-out;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
      font-size: 0.72rem;
      line-height: 1.4;
      pointer-events: none;
      /* Make sure it doesn't block mouse movements */
      font-weight: normal;
    }

    .custom-tooltip-content::after {
      content: "";
      position: absolute;
      bottom: 100%;
      /* At the top of the tooltip */
      top: auto;
      left: 50%;
      margin-left: -5px;
      border-width: 5px;
      border-style: solid;
      border-color: transparent transparent #2f3349 transparent;
    }

    .has-tooltip:hover .custom-tooltip-content {
      visibility: visible;
      opacity: 1;
    }

    .tooltip-align-right {
      right: 0 !important;
      left: auto !important;
      transform: none !important;
    }

    .tooltip-align-right::after {
      left: auto !important;
      right: 15px !important;
      margin-left: 0 !important;
    }

    .table-responsive {
      overflow: visible !important;
    }

    .table-responsive table {
      font-size: 0.8rem;
    }
  </style>
  <div class="d-flex justify-content-between align-items-center py-3 mb-4">
    <h4 class="mb-0"><span class="text-muted fw-light">RKAP /</span> Pengajuan RKAP</h4>
    <div class="d-flex gap-2 align-items-center">
      @canany(['rkap.compilation.dept', 'rkap.compilation.dir', 'rkap.compilation.all'])
        <a href="{{ route('rkap-submissions-compilation') }}" class="btn btn-outline-info d-flex align-items-center gap-1">
          <i class="bx bx-layer me-1"></i> Kompilasi RKAP
        </a>
      @endcanany
      @can('rkap.create')
        <button wire:click="openPeriodSelector" class="btn btn-primary">
          <i class="bx bx-plus me-1"></i> Buat Pengajuan
        </button>
      @endcan
    </div>
  </div>

  {{-- Toast Notification --}}
  <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
    <div x-show="showToast" x-transition:enter="transition ease-out duration-300"
      x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
      x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0"
      x-transition:leave-end="opacity-0 translate-y-2" class="bs-toast toast show text-white" :class="'bg-' + toastType"
      role="alert" aria-live="assertive" aria-atomic="true" style="display: none;">
      <div class="toast-header text-white" :class="'bg-' + toastType">
        <i class="bx me-2 text-white" :class="toastType === 'success' ? 'bx-check-circle' : 'bx-x-circle'"></i>
        <div class="me-auto fw-semibold" x-text="toastType === 'success' ? 'Berhasil' : 'Error'"></div>
        <button type="button" class="btn-close btn-close-white" @click="showToast = false" aria-label="Close"></button>
      </div>
      <div class="toast-body" x-text="toastMessage"></div>
    </div>
  </div>

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
            <input type="text" class="form-control" placeholder="Cari biro atau periode..."
              wire:model.live.debounce.300ms="search">
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
            @foreach ($periods as $period)
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
            <th>Periode (Versi)</th>
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
                <div class="small text-muted">
                  {{ $submission->bureau->department->directorate->code ?? '-' }} - {{ $submission->bureau->department->code ?? '-' }} - {{ $submission->bureau->code ?? '-' }}
                </div>
                <strong>{{ $submission->bureau->name ?? '-' }}</strong>
              </td>
              <td>
                <span class="badge bg-label-secondary">{{ $submission->period->title ?? '-' }}
                  (v{{ $submission->current_version }})
                </span>
              </td>
              <td class="text-end">
                <span class="has-tooltip fw-bold text-dark">
                  Rp {{ number_format($submission->total_budget, 0, ',', '.') }}
                  <span class="custom-tooltip-content tooltip-align-right">
                    @if (isset($prevDataMap[$submission->id]))
                      @php
                        $prev = $prevDataMap[$submission->id];
                      @endphp
                      <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya
                        ({{ $prev['period_title'] }})
                      </div>
                      <div class="row text-center">
                        <div class="col-4 border-end">
                          <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                          <div class="fw-bold text-white" style="font-size: 0.75rem;">Rp
                            {{ number_format($prev['budget'], 0, ',', '.') }}</div>
                        </div>
                        <div class="col-4 border-end">
                          <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                          <div class="fw-bold text-white text-success" style="font-size: 0.75rem;">Rp
                            {{ number_format($prev['realization'], 0, ',', '.') }}</div>
                        </div>
                        <div class="col-4">
                          <div class="text-white-50 small" style="font-size: 0.65rem;">Proyeksi</div>
                          <div class="fw-bold text-white text-warning" style="font-size: 0.75rem;">Rp
                            {{ number_format($prev['projection'] ?? 0, 0, ',', '.') }}</div>
                        </div>
                      </div>
                    @else
                      <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
                    @endif
                  </span>
                </span>
              </td>
              <td>
                <span class="badge bg-{{ $submission->status_color }}">{{ $submission->status_label }}</span>
              </td>
              <td><small class="text-muted">{{ $submission->updated_at->diffForHumans() }}</small></td>
              <td>
                <div class="dropdown">
                  <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    Aksi
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    @if ($submission->canBeEditedBy(Auth::user()))
                      <li>
                        <a class="dropdown-item text-primary" href="{{ route('rkap-submissions-edit', $submission->id) }}">
                          <i class="bx bx-edit-alt me-2"></i> Edit
                        </a>
                      </li>
                    @endif
                    @if ($submission->canBeReviewedBy(Auth::user()))
                      <li>
                        <a class="dropdown-item text-success" href="{{ route('rkap-submissions-approval-review', $submission->id) }}">
                          <i class="bx bx-check-circle me-2"></i> Review
                        </a>
                      </li>
                    @endif
                    <li>
                      <a class="dropdown-item text-secondary" href="{{ route('rkap-submissions-review', $submission->id) }}">
                        <i class="bx bx-show me-2"></i> Detail
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item text-info" href="{{ route('rkap-submissions-versions', $submission->id) }}">
                        <i class="bx bx-history me-2"></i> Riwayat Versi
                      </a>
                    </li>
                    <li>
                      <button type="button" class="dropdown-item text-warning" wire:click="exportExcel({{ $submission->id }})">
                        <i class="bx bx-download me-2"></i> Export Excel
                      </button>
                    </li>
                  </ul>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-5">
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
  @if ($showPeriodSelector)
    <div class="modal fade show" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);"
      aria-modal="true" role="dialog">
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
              @if ($isSubmitted)
                <button class="btn btn-outline-secondary w-100 mb-2 text-start" disabled>
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <strong>{{ $period->title }}</strong>
                      <div class="small text-muted">{{ $period->year }} &bull; <span
                          class="badge bg-label-danger">Sudah Diinput</span></div>
                    </div>
                    <i class="bx bx-check-double text-success"></i>
                  </div>
                </button>
              @else
                <div class="border rounded p-3 mb-2 bg-lighter">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                      <strong>{{ $period->title }}</strong>
                      <div class="small text-muted">{{ $period->year }} &bull; <span
                          class="badge bg-label-success">{{ ucfirst($period->status) }}</span></div>
                    </div>
                  </div>
                  <div class="d-flex gap-2">
                    <button wire:click="selectPeriod({{ $period->id }})"
                      class="btn btn-sm btn-primary flex-grow-1">
                      <i class="bx bx-plus me-1"></i> Input Baru
                    </button>
                    @if (count($previousSubmissions) > 0)
                      <button wire:click="openDuplicateModal({{ $period->id }})"
                        class="btn btn-sm btn-outline-primary flex-grow-1">
                        <i class="bx bx-copy me-1"></i> Duplikasi
                      </button>
                    @endif
                  </div>
                </div>
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

  {{-- Duplication Modal --}}
  @if ($showDuplicateModal)
    <div class="modal fade show" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);"
      aria-modal="true" role="dialog">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bx bx-copy me-2"></i>Duplikasi Pengajuan RKAP</h5>
            <button type="button" class="btn-close" wire:click="closeDuplicateModal"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted small">Pilih pengajuan sebelumnya yang ingin diduplikasi ke periode baru.</p>

            <div class="mb-3">
              <label class="form-label fw-semibold">Pilih Pengajuan Sumber</label>
              <select class="form-select" wire:model.live="selectedSourceSubmissionId">
                <option value="">-- Pilih Pengajuan Sumber --</option>
                @foreach ($previousSubmissions as $prev)
                  <option value="{{ $prev->id }}">
                    {{ $prev->period->title }} (v{{ $prev->current_version }} - Rp
                    {{ number_format($prev->total_budget, 0, ',', '.') }})
                  </option>
                @endforeach
              </select>
            </div>

            @if ($selectedSourceSubmissionId)
              @php
                $selectedSource = collect($previousSubmissions)->firstWhere('id', $selectedSourceSubmissionId);
              @endphp
              @if ($selectedSource)
                <div class="alert alert-info py-2 px-3 small mb-0">
                  <div class="fw-semibold">Detail Pengajuan Sumber:</div>
                  <ul class="mb-0 ps-3 mt-1">
                    <li>Versi: v{{ $selectedSource->current_version }}</li>
                    <li>Total Rencana Kerja: {{ $selectedSource->workPlans()->count() }}</li>
                    <li>Total Anggaran: Rp {{ number_format($selectedSource->total_budget, 0, ',', '.') }}</li>
                  </ul>
                </div>
              @endif
            @endif
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-label-secondary" wire:click="closeDuplicateModal">Batal</button>
            <button type="button" class="btn btn-primary" wire:click="duplicateSubmission"
              @if (!$selectedSourceSubmissionId) disabled @endif>
              <i class="bx bx-copy me-1"></i> Mulai Duplikasi
            </button>
          </div>
        </div>
      </div>
    </div>
  @endif
</div>
