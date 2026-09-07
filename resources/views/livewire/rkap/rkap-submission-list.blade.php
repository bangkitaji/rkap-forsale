<div x-data="{
    showToast: @js(session()->has('message') || session()->has('error')),
    toastMessage: @js(session('message') ?: session('error') ?: ''),
    toastType: @js(session()->has('error') ? 'danger' : 'success')
}" x-init="if (showToast) { setTimeout(() => showToast = false, 5000); }">
  <div class="d-flex justify-content-between align-items-center py-3 mb-4">
    <h4 class="mb-0"><span class="text-muted fw-light">RKAP /</span> {{ __('Pengajuan RKAP') }}</h4>
    <div class="d-flex gap-2 align-items-center">
      @canany(['rkap.compilation.dept', 'rkap.compilation.dir', 'rkap.compilation.all'])
      <a href="{{ route('rkap-submissions-compilation') }}" class="btn btn-outline-info d-flex align-items-center gap-1">
        <i class="bx bx-layer me-1"></i> {{ __('Kompilasi RKAP') }}
      </a>
      @endcanany
      @can('rkap.create')
      <button wire:click="openPeriodSelector" class="btn btn-primary">
        <i class="bx bx-plus me-1"></i> {{ __('Buat Pengajuan') }}
      </button>
      @endcan
    </div>
  </div>

  {{-- Toast Notification --}}
  <div class="toast-container position-fixed top-0 end-0 p-3 rkap-z-1090">
    <div x-show="showToast" x-cloak x-transition:enter="transition ease-out duration-300"
      x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
      x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0"
      x-transition:leave-end="opacity-0 translate-y-2" class="bs-toast toast show text-white" :class="'bg-' + toastType"
      role="alert" aria-live="assertive" aria-atomic="true">
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
            <div class="text-muted small">{{ __('Total Pengajuan') }}</div>
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
            <div class="text-muted small">{{ __('Menunggu Review') }}</div>
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
            <div class="text-muted small">{{ __('Perlu Revisi') }}</div>
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
            <div class="text-muted small">{{ __('Disetujui') }}</div>
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
          <select class="form-select" wire:model.live="perPage" title="{{ __('Baris per halaman') }}">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </div>
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text"><i class="bx bx-search"></i></span>
            <input type="text" class="form-control" placeholder="{{ __('Cari biro (nama/kode) atau periode...') }}"
              wire:model.live.debounce.300ms="search">
          </div>
        </div>
        <div class="col-md-4">
          <select class="form-select" wire:model.live="filterStatus">
            <option value="">{{ __('Semua Status') }}</option>
            <option value="draft">Draft</option>
            <option value="submitted">{{ __('Diajukan') }}</option>
            <option value="dept_review">{{ __('Review Kadep') }}</option>
            <option value="dept_approved">{{ __('Disetujui Kadep') }}</option>
            <option value="dept_revision">{{ __('Revisi Kadep') }}</option>
            <option value="dir_review">{{ __('Review Direksi') }}</option>
            <option value="dir_approved">{{ __('Disetujui Direksi') }}</option>
            <option value="dir_revision">{{ __('Revisi Direksi') }}</option>
            <option value="final_review">{{ __('Verifikasi Final') }}</option>
            <option value="final_revision">{{ __('Revisi Verifikator') }}</option>
            <option value="approved">{{ __('Disetujui Final') }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <select class="form-select" wire:model.live="filterPeriod">
            <option value="">{{ __('Semua Periode') }}</option>
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
            <th>{{ __('Biro') }}</th>
            <th>{{ __('Periode (Versi)') }}</th>
            <th class="text-end">{{ __('Total Anggaran') }}</th>
            <th class="text-center">{{ __('Status') }}</th>
            <th class="text-center">{{ __('Diperbarui') }}</th>
            <th class="text-center">{{ __('Aksi') }}</th>
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
                  <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">{{ __('RKAP Periode Sebelumnya') }}
                    ({{ $prev['period_title'] }})
                  </div>
                  <div class="row text-center">
                    <div class="col-4 border-end">
                      <div class="text-white-50 small rkap-font-065">{{ __('Anggaran') }}</div>
                      <div class="fw-bold text-white rkap-font-075">Rp
                        {{ number_format($prev['budget'], 0, ',', '.') }}
                      </div>
                    </div>
                    <div class="col-4 border-end">
                      <div class="text-white-50 small rkap-font-065">{{ __('Realisasi') }}</div>
                      <div class="fw-bold text-white text-success rkap-font-075">Rp
                        {{ number_format($prev['realization'], 0, ',', '.') }}
                      </div>
                    </div>
                    <div class="col-4">
                      <div class="text-white-50 small rkap-font-065">{{ __('Proyeksi') }}</div>
                      <div class="fw-bold text-white text-warning rkap-font-075">Rp
                        {{ number_format($prev['projection'] ?? 0, 0, ',', '.') }}
                      </div>
                    </div>
                  </div>
                  @else
                  <div class="text-center text-white-50 py-1">{{ __('Tidak ada data di periode sebelumnya') }}</div>
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
                      <i class="bx bx-edit-alt me-2"></i> {{ __('Edit') }}
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
                      <i class="bx bx-show me-2"></i> {{ __('Detail') }}
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item text-info" href="{{ route('rkap-submissions-versions', $submission->id) }}">
                      <i class="bx bx-history me-2"></i> {{ __('Riwayat Versi') }}
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
              {{ __('Tidak ada pengajuan ditemukan.') }}
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
  <div class="modal fade show rkap-modal-show" tabindex="-1"
    aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bx bx-calendar-plus me-2"></i>{{ __('Pilih Periode RKAP') }}</h5>
          <button type="button" class="btn-close" wire:click="closePeriodSelector"></button>
        </div>
        <div class="modal-body">
          @forelse($activePeriods as $period)
          @php
          $existingSubmission = isset($bureauSubmissions[$period->id]) ? $bureauSubmissions[$period->id] : null;
          $isDraft = $existingSubmission && $existingSubmission->status === 'draft';
          $isSubmitted = $existingSubmission && $existingSubmission->status !== 'draft';
          @endphp
          @if ($isSubmitted)
          <button class="btn btn-outline-secondary w-100 mb-2 text-start" disabled>
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <strong>{{ $period->title }}</strong>
                <div class="small text-muted">{{ $period->year }} &bull; <span
                    class="badge bg-label-danger">{{ __('Sudah Diinput') }}</span></div>
              </div>
              <i class="bx bx-check-double text-success"></i>
            </div>
          </button>
          @elseif ($isDraft)
          <div class="border rounded p-3 mb-2 bg-lighter">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <strong>{{ $period->title }}</strong>
                <div class="small text-muted">{{ $period->year }} &bull; <span
                    class="badge bg-label-secondary">Draft</span></div>
              </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <a href="{{ route('rkap-submissions-edit', ['id' => $existingSubmission->id]) }}"
                class="btn btn-sm btn-warning flex-grow-1">
                <i class="bx bx-edit-alt me-1"></i> {{ __('Edit Draft') }}
              </a>
              <a href="{{ route('rkap-submissions-bulk-upload', ['periodId' => $period->id]) }}"
                class="btn btn-sm btn-outline-success flex-grow-1">
                <i class="bx bx-upload me-1"></i> {{ __('Upload Massal') }}
              </a>
            </div>
          </div>
          @else
          <div class="border rounded p-3 mb-2 bg-lighter">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <strong>{{ $period->title }}</strong>
                <div class="small text-muted">{{ $period->year }} &bull; <span
                    class="badge bg-label-success">{{ ucfirst($period->status) }}</span></div>
              </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <button wire:click="selectPeriod({{ $period->id }})"
                class="btn btn-sm btn-primary flex-grow-1">
                <i class="bx bx-plus me-1"></i> {{ __('Input Baru') }}
              </button>
              <a href="{{ route('rkap-submissions-bulk-upload', ['periodId' => $period->id]) }}"
                class="btn btn-sm btn-outline-success flex-grow-1">
                <i class="bx bx-upload me-1"></i> {{ __('Upload Massal') }}
              </a>
              @if (count($previousSubmissions) > 0)
              <button wire:click="openDuplicateModal({{ $period->id }})"
                class="btn btn-sm btn-outline-primary flex-grow-1">
                <i class="bx bx-copy me-1"></i> {{ __('Duplikasi') }}
              </button>
              @endif
            </div>
          </div>
          @endif
          @empty
          <div class="text-center text-muted py-4">
            <i class="bx bx-calendar-x bx-lg d-block mb-2"></i>
            {{ __('Tidak ada periode aktif.') }}<br>
            <small>{{ __('Hubungi admin untuk mengaktifkan periode RKAP.') }}</small>
          </div>
          @endforelse
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" wire:click="closePeriodSelector">{{ __('Batal') }}</button>
        </div>
      </div>
    </div>
  </div>
  @endif

  {{-- Duplication Modal --}}
  @if ($showDuplicateModal)
  <div class="modal fade show rkap-modal-show" tabindex="-1"
    aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bx bx-copy me-2"></i>{{ __('Duplikasi Pengajuan RKAP') }}</h5>
          <button type="button" class="btn-close" wire:click="closeDuplicateModal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small">{{ __('Pilih pengajuan sebelumnya yang ingin diduplikasi ke periode baru.') }}</p>

          <div class="mb-3">
            <label class="form-label fw-semibold">{{ __('Pilih Pengajuan Sumber') }}</label>
            <select class="form-select" wire:model.live="selectedSourceSubmissionId">
              <option value="">-- {{ __('Pilih Pengajuan Sumber') }} --</option>
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
            <div class="fw-semibold">{{ __('Detail Pengajuan Sumber:') }}</div>
            <ul class="mb-0 ps-3 mt-1">
              <li>{{ __('Versi:') }} v{{ $selectedSource->current_version }}</li>
              <li>{{ __('Total Rencana Kerja:') }} {{ $selectedSource->workPlans()->count() }}</li>
              <li>{{ __('Total Anggaran:') }} Rp {{ number_format($selectedSource->total_budget, 0, ',', '.') }}</li>
            </ul>
          </div>
          @endif
          @endif
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" wire:click="closeDuplicateModal">{{ __('Batal') }}</button>
          <button type="button" class="btn btn-primary" wire:click="duplicateSubmission"
            @if (!$selectedSourceSubmissionId) disabled @endif>
            <i class="bx bx-copy me-1"></i> {{ __('Mulai Duplikasi') }}
          </button>
        </div>
      </div>
    </div>
  </div>
  @endif
</div>