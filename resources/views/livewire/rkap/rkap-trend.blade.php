<div x-data="{
    showToast: false,
    toastMessage: '',
    toastType: 'success'
}"
  x-on:trend-justification-saved.window="
    showToast = true;
    toastMessage = '{{ __('Justifikasi berhasil disimpan.') }}';
    toastType = 'success';
    setTimeout(() => showToast = false, 4000);
">

  {{-- Header Section --}}
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center py-3 mb-4 gap-3">
    <div>
      <h4 class="mb-1">
        <span class="text-muted fw-light">{{ __('Proses RKAP') }} /</span> {{ __('Trend') }}
      </h4>
      <p class="text-muted mb-0 small">
        {{ __('Daftar kegiatan usulan dan justifikasi deviasi anggaran tahun berjalan dengan usulan.') }}
      </p>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <button type="button" wire:click="expandAll"
        class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 shadow-sm">
        <i class="bx bx-expand-alt"></i> {{ __('Buka Semua') }}
      </button>
      <button type="button" wire:click="collapseAll"
        class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 shadow-sm">
        <i class="bx bx-collapse-alt"></i> {{ __('Tutup Semua') }}
      </button>
      <button type="button" wire:click="exportExcel"
        class="btn btn-label-success btn-sm d-flex align-items-center gap-1 shadow-sm" wire:loading.attr="disabled">
        <span wire:loading.remove wire:target="exportExcel"><i class="bx bx-download me-1"></i>
          {{ __('Export Excel') }}</span>
        <span wire:loading wire:target="exportExcel"><i class="bx bx-loader-alt bx-spin me-1"></i>
          {{ __('Mengekspor...') }}</span>
      </button>
    </div>
  </div>

  {{-- Toast Notification --}}
  <div class="toast-container position-fixed top-0 end-0 p-3 rkap-z-1090" style="z-index: 1090;">
    <div x-show="showToast" x-cloak x-transition:enter="transition ease-out duration-300"
      x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
      x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0"
      x-transition:leave-end="opacity-0 translate-y-2" class="bs-toast toast show text-white" :class="'bg-' + toastType"
      role="alert" aria-live="assertive" aria-atomic="true">
      <div class="toast-header text-white" :class="'bg-' + toastType">
        <i class="bx me-2 text-white" :class="toastType === 'success' ? 'bx-check-circle' : 'bx-x-circle'"></i>
        <div class="me-auto fw-semibold"
          x-text="toastType === 'success' ? '{{ __('Berhasil') }}' : '{{ __('Error') }}'"></div>
        <button type="button" class="btn-close btn-close-white" @click="showToast = false" aria-label="Close"></button>
      </div>
      <div class="toast-body" x-text="toastMessage"></div>
    </div>
  </div>

  {{-- Period Status & Scope Info Banner --}}
  <div class="card border-0 shadow-sm mb-4 bg-primary text-white">
    <div class="card-body p-3 p-md-4">
      <div class="row align-items-center g-3">
        <div class="col-md-7">
          <div class="d-flex align-items-center gap-3">
            <div
              class="avatar avatar-md flex-shrink-0 bg-primary bg-opacity-20 text-white rounded p-2 d-flex align-items-center justify-content-center">
              <i class="bx bx-trending-up fs-3 text-white"></i>
            </div>
            <div>
              <h5 class="text-white mb-1 fw-bold">{{ __('Analisis Trend & Justifikasi Deviasi RKAP') }}</h5>
              <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                <span class="badge bg-white text-primary px-3 py-1.5 fw-semibold shadow-xs">
                  <i class="bx bx-calendar-check me-1"></i>{{ __('Berjalan') }}: <strong
                    class="text-dark">{{ $currentPeriodTitle ?? '-' }}</strong>
                </span>
                <span class="badge bg-white text-primary px-3 py-1.5 fw-semibold shadow-xs">
                  <i class="bx bx-git-pull-request me-1"></i>{{ __('Usulan') }}: <strong
                    class="text-dark">{{ $proposalPeriodTitle ?? '-' }}</strong>
                </span>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-5 text-md-end">
          <span
            class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold shadow-xs d-inline-flex align-items-center gap-1">
            <i class="bx bx-building"></i> {{ Auth::user()->organization_name }}
            <span class="text-muted fw-normal">({{ Auth::user()->roles->pluck('name')->join(', ') }})</span>
          </span>
        </div>
      </div>
    </div>
  </div>

  @if (session()->has('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm" role="alert">
      <i class="bx bx-error-circle me-1"></i> {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  {{-- Stats / Resume KPI Cards --}}
  @php
    $summary = $trendData['summary'];
  @endphp
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-3">
          <div class="d-flex align-items-center gap-3">
            <div class="avatar avatar-md flex-shrink-0">
              <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-list-check fs-4"></i></span>
            </div>
            <div>
              <div class="fw-bold fs-4">{{ number_format($summary['total_activities']) }}</div>
              <div class="text-muted small">{{ __('Total Kegiatan') }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-3">
          <div class="d-flex align-items-center gap-3">
            <div class="avatar avatar-md flex-shrink-0">
              <span class="avatar-initial rounded bg-label-success"><i class="bx bx-check-double fs-4"></i></span>
            </div>
            <div class="w-100">
              <div class="d-flex justify-content-between align-items-baseline">
                <span class="fw-bold fs-4 text-success">{{ number_format($summary['filled_count']) }}</span>
                <span class="badge bg-label-success fs-tiny">{{ $summary['percentage'] }}%</span>
              </div>
              <div class="text-muted small">{{ __('Terjustifikasi') }}</div>
              <div class="progress mt-1" style="height: 4px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $summary['percentage'] }}%"
                  aria-valuenow="{{ $summary['percentage'] }}" aria-valuemin="0" aria-valuemax="100"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-3">
          <div class="d-flex align-items-center gap-3">
            <div class="avatar avatar-md flex-shrink-0">
              <span class="avatar-initial rounded bg-label-warning"><i class="bx bx-edit-alt fs-4"></i></span>
            </div>
            <div>
              <div class="fw-bold fs-4 text-warning">{{ number_format($summary['unfilled_count']) }}</div>
              <div class="text-muted small">{{ __('Belum Terjustifikasi') }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-3">
          <div class="d-flex align-items-center gap-3">
            <div class="avatar avatar-md flex-shrink-0">
              <span class="avatar-initial rounded bg-label-info"><i class="bx bx-line-chart fs-4"></i></span>
            </div>
            <div class="overflow-hidden">
              <div
                class="fw-bold fs-6 text-truncate {{ $summary['total_dev_prop'] >= 0 ? 'text-primary' : 'text-danger' }}">
                Rp {{ number_format($summary['total_dev_prop'], 0, ',', '.') }}
              </div>
              <div class="text-muted small text-truncate">{{ __('Net Deviasi Usulan') }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Filters Card --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
      <div class="row g-3 align-items-end">
        {{-- Directorate Filter --}}
        <div class="col-md-3">
          <label class="form-label small fw-semibold text-muted mb-1">{{ __('Direktorat') }}</label>
          <select wire:model.live="directorateId" class="form-select form-select-sm" @disabled(Auth::user()->isKepalaBiro() || Auth::user()->isKepalaDepartemen() || Auth::user()->isDireksi())>
            <option value="">{{ __('-- Semua Direktorat --') }}</option>
            @foreach ($directorateOptions as $dir)
              <option value="{{ $dir->id }}">{{ $dir->code }} — {{ $dir->name }}</option>
            @endforeach
          </select>
        </div>

        {{-- Department Filter --}}
        <div class="col-md-3">
          <label class="form-label small fw-semibold text-muted mb-1">{{ __('Departemen') }}</label>
          <select wire:model.live="departmentId" class="form-select form-select-sm" @disabled(Auth::user()->isKepalaBiro() || Auth::user()->isKepalaDepartemen())>
            <option value="">{{ __('-- Semua Departemen --') }}</option>
            @foreach ($departmentOptions as $dept)
              <option value="{{ $dept->id }}">{{ $dept->code }} — {{ $dept->name }}</option>
            @endforeach
          </select>
        </div>

        {{-- Bureau Filter --}}
        <div class="col-md-3">
          <label class="form-label small fw-semibold text-muted mb-1">{{ __('Biro') }}</label>
          <select wire:model.live="bureauId" class="form-select form-select-sm" @disabled(Auth::user()->isKepalaBiro())>
            <option value="">{{ __('-- Semua Biro --') }}</option>
            @foreach ($bureauOptions as $bur)
              <option value="{{ $bur->id }}">{{ $bur->code }} — {{ $bur->name }}</option>
            @endforeach
          </select>
        </div>

        {{-- Search & Status Filter --}}
        <div class="col-md-3">
          <label class="form-label small fw-semibold text-muted mb-1">{{ __('Status Justifikasi') }}</label>
          <select wire:model.live="filterStatus" class="form-select form-select-sm">
            <option value="all">{{ __('Semua Status') }} ({{ $summary['total_activities'] }})</option>
            <option value="filled">{{ __('Sudah Terjustifikasi') }} ({{ $summary['filled_count'] }})</option>
            <option value="unfilled">{{ __('Belum Terjustifikasi') }} ({{ $summary['unfilled_count'] }})</option>
          </select>
        </div>
      </div>

      <div class="row mt-2 g-3 align-items-center">
        <div class="col-md-8">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-light border-end-0"><i class="bx bx-search text-muted"></i></span>
            <input type="text" wire:model.live.debounce.300ms="search" class="form-control border-start-0"
              placeholder="{{ __('Cari kode, nama kegiatan, biro, atau kata kunci justifikasi...') }}">
            @if ($search)
              <button type="button" class="btn btn-outline-secondary" wire:click="$set('search', '')">
                <i class="bx bx-x"></i>
              </button>
            @endif
          </div>
        </div>
        <div class="col-md-4 text-md-end text-muted small">
          {{ __('Menampilkan') }} <strong>{{ count($trendData['items']) }}</strong> {{ __('kegiatan') }}
        </div>
      </div>
    </div>
  </div>

  {{-- Main Trend Table Card --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 w-100" style="font-size: 0.85rem; width: 100%;">
          <thead class="table-light">
            <tr>
              <th style="width: 28%;">{{ __('KEGIATAN') }}</th>
              <th class="text-end" style="width: 15%;">
                <div>{{ __('RKAP') }} {{ $currentPeriodYear ?? '' }}</div>
                <small class="text-muted fw-normal">{{ __('PROYEKSI') }} {{ $currentPeriodYear ?? '' }}</small>
              </th>
              <th class="text-end" style="width: 14%;">
                <div>{{ __('Deviasi') }}</div>
                <small class="text-muted fw-normal">(RKAP - Proyeksi)</small>
              </th>
              <th class="text-end" style="width: 14%;">
                <div>{{ $proposalPeriodTitle ?? 'RKAP Usulan' }}</div>
                <small class="text-muted fw-normal">({{ __('Usulan') }})</small>
              </th>
              <th class="text-end" style="width: 14%;">
                <div>{{ __('Deviasi') }}</div>
                <small class="text-muted fw-normal">(Usulan - Berjalan)</small>
              </th>
              <th class="text-center" style="width: 10%;">{{ __('STATUS') }}</th>
              <th class="text-center" style="width: 5%; min-width: 45px;">{{ __('AKSI') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($trendData['items'] as $index => $item)
              @php
                $itemType   = $item['item_type'] ?? 'matched'; // 'matched' | 'new' | 'discontinued'
                $isExpanded = in_array($item['work_plan_id'], $expandedRows);

                $rowClass = match($itemType) {
                    'new'          => 'table-success',        // hijau muda = kegiatan baru
                    'discontinued' => 'table-warning opacity-75', // kuning muda = tidak diusulkan kembali
                    default        => '',                      // matched = normal
                };
              @endphp
              {{-- Main Row --}}
              <tr class="{{ $isExpanded ? 'table-active' : $rowClass }}"
                style="cursor: pointer;"
                wire:key="row-{{ $item['work_plan_id'] }}">

                {{-- Kolom: Kode & Nama Kegiatan --}}
                <td wire:click="toggleRow({{ $item['work_plan_id'] }})">
                  <div class="text-primary fw-semibold small">{{ $item['code'] }}</div>
                  <div class="fw-bold text-dark text-break">{{ $item['name'] }}</div>
                  @if ($itemType === 'new')
                    <div class="mt-1">
                      <span class="badge bg-success bg-opacity-85"
                        title="{{ __('Kegiatan ini baru diusulkan dan tidak ada di RKAP tahun berjalan') }}">
                        <i class="bx bx-plus-circle me-1"></i>{{ __('Kegiatan Baru') }}
                      </span>
                    </div>
                  @elseif ($itemType === 'discontinued')
                    <div class="mt-1">
                      <span class="badge bg-danger bg-opacity-75"
                        title="{{ __('Kegiatan ini ada di RKAP berjalan tetapi tidak diusulkan kembali untuk periode berikutnya') }}">
                        <i class="bx bx-x-circle me-1"></i>{{ __('Kegiatan Tidak Diusulkan Kembali') }}
                      </span>
                    </div>
                  @endif
                </td>

                {{-- Kolom: RKAP Berjalan & Proyeksi --}}
                <td class="text-end" wire:click="toggleRow({{ $item['work_plan_id'] }})">
                  @if ($itemType === 'new')
                    <div class="text-muted small fst-italic">{{ __('Tidak ada') }}</div>
                    <div class="text-muted small fst-italic">{{ __('Tidak ada') }}</div>
                  @else
                    <div class="fw-bold text-dark text-nowrap">Rp {{ number_format($item['rkap_current'], 0, ',', '.') }}</div>
                    <div class="text-muted small text-nowrap">Rp {{ number_format($item['projection_current'], 0, ',', '.') }}</div>
                  @endif
                </td>

                {{-- Kolom: Deviasi RKAP vs Proyeksi --}}
                <td class="text-end" wire:click="toggleRow({{ $item['work_plan_id'] }})">
                  @if ($itemType === 'new')
                    <span class="badge bg-label-secondary">-</span>
                  @elseif ($item['dev_projection'] > 0)
                    <span class="badge bg-label-success">
                      +Rp {{ number_format($item['dev_projection'], 0, ',', '.') }} <i class="bx bx-up-arrow-alt"></i>
                    </span>
                  @elseif ($item['dev_projection'] < 0)
                    <span class="badge bg-label-danger">
                      -Rp {{ number_format(abs($item['dev_projection']), 0, ',', '.') }} <i class="bx bx-down-arrow-alt"></i>
                    </span>
                  @else
                    <span class="badge bg-label-secondary">Rp 0</span>
                  @endif
                </td>

                {{-- Kolom: RKAP Usulan --}}
                <td class="text-end fw-bold text-primary"
                  wire:click="toggleRow({{ $item['work_plan_id'] }})">
                  @if ($itemType === 'discontinued')
                    <span class="text-muted fst-italic small">{{ __('Tidak diusulkan') }}</span>
                  @else
                    <span class="text-nowrap">Rp {{ number_format($item['rkap_proposed'], 0, ',', '.') }}</span>
                  @endif
                </td>

                {{-- Kolom: Deviasi Usulan vs Berjalan --}}
                <td class="text-end" wire:click="toggleRow({{ $item['work_plan_id'] }})">
                  @if ($itemType === 'discontinued')
                    <span class="badge bg-label-secondary">-</span>
                  @elseif ($item['dev_proposal'] > 0)
                    <span class="badge bg-label-primary">
                      +Rp {{ number_format($item['dev_proposal'], 0, ',', '.') }} <i class="bx bx-up-arrow-alt"></i>
                    </span>
                  @elseif ($item['dev_proposal'] < 0)
                    <span class="badge bg-label-warning">
                      -Rp {{ number_format(abs($item['dev_proposal']), 0, ',', '.') }} <i class="bx bx-down-arrow-alt"></i>
                    </span>
                  @else
                    <span class="badge bg-label-secondary">Rp 0</span>
                  @endif
                </td>

                {{-- Kolom: Status Justifikasi --}}
                <td class="text-center" wire:click="toggleRow({{ $item['work_plan_id'] }})">
                  @if ($item['is_fully_filled'])
                    <span class="badge bg-success shadow-sm"><i class="bx bx-check me-1"></i>{{ __('Lengkap') }}</span>
                  @elseif ($item['is_filled'])
                    <span class="badge bg-info shadow-sm">{{ __('Sebagian') }}</span>
                  @else
                    <span class="badge bg-label-danger">{{ __('Belum Diisi') }}</span>
                  @endif
                </td>

                {{-- Kolom: Aksi --}}
                <td class="text-center">
                  <button type="button"
                    class="btn btn-icon btn-sm {{ $isExpanded ? 'btn-primary' : 'btn-outline-secondary' }}"
                    wire:click="toggleRow({{ $item['work_plan_id'] }})"
                    title="{{ $isExpanded ? __('Tutup Justifikasi') : __('Isi/Lihat Justifikasi') }}">
                    <i class="bx {{ $isExpanded ? 'bx-chevron-up' : 'bx-edit' }}"></i>
                  </button>
                </td>
              </tr>

              {{-- Accordion Expanded Detail Form --}}
              @if ($isExpanded)
                <tr class="bg-light" wire:key="detail-{{ $item['work_plan_id'] }}">
                  <td colspan="7" class="p-3 p-md-4 border-bottom shadow-inner">
                    <div class="card border border-primary border-opacity-25 shadow-sm bg-white">
                      <div
                        class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center py-2 px-3 border-bottom gap-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                          <span class="badge {{ $itemType === 'discontinued' ? 'bg-label-danger' : ($itemType === 'new' ? 'bg-label-success' : 'bg-label-primary') }}">
                            <i class="bx {{ $itemType === 'discontinued' ? 'bx-x-circle' : ($itemType === 'new' ? 'bx-plus-circle' : 'bx-message-square-detail') }} me-1"></i>
                            @if ($itemType === 'discontinued')
                              {{ __('Justifikasi Kegiatan Tidak Diusulkan Kembali') }}
                            @elseif ($itemType === 'new')
                              {{ __('Justifikasi Kegiatan Baru') }}
                            @else
                              {{ __('Justifikasi Deviasi') }}
                            @endif
                          </span>
                          <span class="fw-bold text-dark text-break">{{ $item['code'] }} — {{ $item['name'] }}</span>
                          <span class="text-muted small">({{ $item['bureau_name'] }})</span>
                        </div>
                        @if ($item['updater_name'])
                          <div class="text-muted small">
                            <i class="bx bx-user me-1"></i>{{ __('Diperbarui:') }}
                            <strong>{{ $item['updater_name'] }}</strong> ({{ $item['updated_at'] }})
                          </div>
                        @endif
                      </div>
                      <div class="card-body p-3 bg-white">
                        @if (session()->has('success_' . $item['work_plan_id']))
                          <div class="alert alert-success alert-dismissible fade show p-2 mb-3 small" role="alert">
                            <i class="bx bx-check-circle me-1"></i> {{ session('success_' . $item['work_plan_id']) }}
                            <button type="button" class="btn-close p-2" data-bs-dismiss="alert"
                              aria-label="Close"></button>
                          </div>
                        @endif

                        <div class="row g-3">
                          {{-- Deviation 1: RKAP vs Proyeksi --}}
                          <div class="col-12">
                            <div class="p-3 bg-white rounded border shadow-xs">
                              <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                <label class="form-label fw-bold text-dark mb-0">
                                  1. {{ __('Deviasi RKAP dengan Proyeksi') }} ({{ $currentPeriodTitle ?? '2026' }})
                                </label>
                                @if ($itemType === 'new')
                                  <span class="badge bg-label-secondary">-</span>
                                @else
                                  <span
                                    class="badge {{ $item['dev_projection'] >= 0 ? 'bg-label-success' : 'bg-label-danger' }}">
                                    {{ $item['dev_projection'] >= 0 ? '+' : '' }}Rp
                                    {{ number_format($item['dev_projection'], 0, ',', '.') }}
                                  </span>
                                @endif
                              </div>
                              <p class="text-muted fs-tiny mb-2">
                                @if ($itemType === 'new')
                                  {{ __('Kegiatan baru tidak memiliki data anggaran dan proyeksi pada tahun berjalan.') }}
                                @else
                                  {{ __('Jelaskan penyebab selisih antara pagu anggaran RKAP berjalan dengan proyeksi akhir tahun.') }}
                                @endif
                              </p>
                              @if ($itemType !== 'new')
                                @if ($item['can_edit'])
                                  <textarea wire:model.defer="justificationForm.{{ $item['work_plan_id'] }}.projection" rows="3"
                                    class="form-control bg-white text-dark" style="background-color: #ffffff !important;"
                                    placeholder="{{ __('Tuliskan keterangan justifikasi deviasi RKAP dengan Proyeksi di sini...') }}"></textarea>
                                @else
                                  <div class="p-2 bg-white rounded border min-vh-25 text-dark"
                                    style="min-height: 80px; background-color: #ffffff !important;">
                                    {{ $item['justification_projection'] ?: __('(Belum ada keterangan justifikasi)') }}
                                  </div>
                                @endif
                              @endif
                            </div>
                          </div>

                          {{-- Deviation 2: RKAP Berjalan vs RKAP Usulan / Alasan Tidak Diusulkan --}}
                          <div class="col-12">
                            <div class="p-3 bg-white rounded border shadow-xs">
                              <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                <label class="form-label fw-bold text-dark mb-0">
                                  @if ($itemType === 'discontinued')
                                    2. {{ __('Alasan Tidak Diusulkan Kembali') }} ({{ $proposalPeriodTitle ?? '2027' }})
                                  @elseif ($itemType === 'new')
                                    2. {{ __('Justifikasi Pengusulan Kegiatan Baru') }} ({{ $proposalPeriodTitle ?? '2027' }})
                                  @else
                                    2. {{ __('Deviasi RKAP Berjalan dengan RKAP Usulan') }} ({{ $proposalPeriodTitle ?? '2027' }})
                                  @endif
                                </label>
                                @if ($itemType === 'discontinued')
                                  <span class="badge bg-label-danger">
                                    {{ __('Tidak Diusulkan Kembali') }}
                                  </span>
                                @elseif ($itemType === 'new')
                                  <span class="badge bg-label-success">
                                    Rp {{ number_format($item['rkap_proposed'], 0, ',', '.') }}
                                  </span>
                                @else
                                  <span
                                    class="badge {{ $item['dev_proposal'] >= 0 ? 'bg-label-primary' : 'bg-label-warning' }}">
                                    {{ $item['dev_proposal'] >= 0 ? '+' : '' }}Rp
                                    {{ number_format($item['dev_proposal'], 0, ',', '.') }}
                                  </span>
                                @endif
                              </div>
                              <p class="text-muted fs-tiny mb-2">
                                @if ($itemType === 'discontinued')
                                  {{ __('Jelaskan alasan mengapa kegiatan ini tidak diusulkan kembali untuk periode RKAP berikutnya (misal: pekerjaan telah selesai, program dialihkan, atau efisiensi).') }}
                                @elseif ($itemType === 'new')
                                  {{ __('Jelaskan latar belakang, urgensi, dan pertimbangan anggaran pengusulan kegiatan baru ini.') }}
                                @else
                                  {{ __('Jelaskan pertimbangan kenaikan / penurunan anggaran yang diusulkan untuk periode berikutnya.') }}
                                @endif
                              </p>
                              @if ($item['can_edit'])
                                <textarea wire:model.defer="justificationForm.{{ $item['work_plan_id'] }}.proposal" rows="3"
                                  class="form-control bg-white text-dark" style="background-color: #ffffff !important;"
                                  placeholder="{{ $itemType === 'discontinued' ? __('Tuliskan alasan mengapa kegiatan tidak diusulkan kembali di sini...') : ($itemType === 'new' ? __('Tuliskan alasan/urgensi pengusulan kegiatan baru di sini...') : __('Tuliskan keterangan justifikasi deviasi RKAP berjalan dengan RKAP usulan di sini...')) }}"></textarea>
                              @else
                                <div class="p-2 bg-white rounded border min-vh-25 text-dark"
                                  style="min-height: 80px; background-color: #ffffff !important;">
                                  {{ $item['justification_proposal'] ?: ($itemType === 'discontinued' ? __('(Belum ada alasan mengapa tidak diusulkan kembali)') : __('(Belum ada keterangan justifikasi)')) }}
                                </div>
                              @endif
                            </div>
                          </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-2 border-top gap-2">
                          <div class="text-muted fs-tiny">
                            @if ($item['can_edit'])
                              <i class="bx bx-edit text-primary me-1"></i>
                              {{ __('Anda memiliki wewenang untuk mengisi & memperbarui justifikasi ini.') }}
                            @else
                              <i class="bx bx-lock-alt text-muted me-1"></i>
                              {{ __('Mode hanya lihat (Read-only sesuai hak akses organisasi).') }}
                            @endif
                          </div>
                          <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                              wire:click="toggleRow({{ $item['work_plan_id'] }})">
                              {{ __('Tutup') }}
                            </button>
                            @if ($item['can_edit'])
                              <button type="button"
                                class="btn btn-primary btn-sm d-flex align-items-center gap-1 shadow-sm"
                                wire:click="saveJustification({{ $item['work_plan_id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="saveJustification({{ $item['work_plan_id'] }})">
                                <span wire:loading.remove
                                  wire:target="saveJustification({{ $item['work_plan_id'] }})">
                                  <i class="bx bx-save me-1"></i> {{ __('Simpan Justifikasi') }}
                                </span>
                                <span wire:loading wire:target="saveJustification({{ $item['work_plan_id'] }})">
                                  <i class="bx bx-loader-alt bx-spin me-1"></i> {{ __('Menyimpan...') }}
                                </span>
                              </button>
                            @endif
                          </div>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              @endif
            @empty
              <tr>
                <td colspan="7" class="text-center py-5">
                  <div class="d-flex flex-column align-items-center justify-content-center">
                    <div
                      class="avatar avatar-xl bg-label-secondary mb-3 rounded-circle p-3 d-flex align-items-center justify-content-center">
                      <i class="bx bx-folder-open fs-1 text-secondary"></i>
                    </div>
                    <h6 class="fw-bold mb-1">{{ __('Tidak ada kegiatan usulan ditemukan') }}</h6>
                    <p class="text-muted small mb-0" style="max-width: 400px;">
                      @if ($search || $filterStatus !== 'all')
                        {{ __('Coba sesuaikan filter atau kata kunci pencarian Anda.') }}
                      @else
                        {{ __('Belum ada data pengajuan RKAP usulan untuk periode terpilih pada biro/organisasi ini.') }}
                      @endif
                    </p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
          {{-- Table Footer Summary --}}
          @if (count($trendData['items']) > 0)
            <tfoot class="table-light fw-bold">
              <tr>
                <td>{{ __('TOTAL') }} ({{ count($trendData['items']) }} {{ __('Kegiatan') }})</td>
                <td class="text-end">
                  <div class="fw-bold text-dark text-nowrap">Rp {{ number_format($summary['total_rkap_current'], 0, ',', '.') }}</div>
                  <div class="text-muted small fw-normal text-nowrap">Rp {{ number_format($summary['total_proj_current'], 0, ',', '.') }}</div>
                </td>
                <td class="text-end">
                  <span class="badge {{ $summary['total_dev_proj'] >= 0 ? 'bg-label-success' : 'bg-label-danger' }}">
                    {{ $summary['total_dev_proj'] >= 0 ? '+' : '' }}Rp
                    {{ number_format($summary['total_dev_proj'], 0, ',', '.') }}
                  </span>
                </td>
                <td class="text-end text-primary">
                  <span class="text-nowrap">Rp {{ number_format($summary['total_rkap_proposed'], 0, ',', '.') }}</span>
                </td>
                <td class="text-end">
                  <span
                    class="badge {{ $summary['total_dev_prop'] >= 0 ? 'bg-label-primary' : 'bg-label-warning' }}">
                    {{ $summary['total_dev_prop'] >= 0 ? '+' : '' }}Rp
                    {{ number_format($summary['total_dev_prop'], 0, ',', '.') }}
                  </span>
                </td>
                <td class="text-center">
                  <span
                    class="badge bg-label-info">{{ $summary['filled_count'] }}/{{ $summary['total_activities'] }}
                    ({{ $summary['percentage'] }}%)</span>
                </td>
                <td></td>
              </tr>
            </tfoot>
          @endif
        </table>
      </div>
    </div>
  </div>
</div>
