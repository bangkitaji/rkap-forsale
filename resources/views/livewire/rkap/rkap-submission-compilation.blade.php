<div>
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
            top: 110%; /* Position below the element */
            bottom: auto;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            font-size: 0.72rem;
            line-height: 1.4;
            pointer-events: none; /* Make sure it doesn't block mouse movements */
            font-weight: normal;
        }
        .custom-tooltip-content::after {
            content: "";
            position: absolute;
            bottom: 100%; /* At the top of the tooltip */
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
        .table-responsive, .card, .card-header {
            overflow: visible !important;
        }
    </style>
    {{-- Page header --}}
    <div class="d-flex justify-content-between align-items-center py-3 mb-4 flex-wrap gap-2">
        <h4 class="mb-0"><span class="text-muted fw-light">RKAP /</span> Kompilasi Pengajuan RKAP</h4>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            @if($selectedPeriod)
            <span class="badge bg-label-primary fs-6 px-3 py-2">
                <i class="bx bx-calendar me-1"></i>{{ $selectedPeriod->title }}
            </span>
            @endif
            <button
                wire:click="exportExcel"
                wire:loading.attr="disabled"
                class="btn btn-success d-flex align-items-center gap-1"
                title="Export Kompilasi ke Excel">
                <span wire:loading.remove wire:target="exportExcel">
                    <i class="bx bx-download me-1"></i> Export Excel
                </span>
                <span wire:loading wire:target="exportExcel">
                    <span class="spinner-border spinner-border-sm me-1"></span> Memproses...
                </span>
            </button>
        </div>
    </div>

    {{-- Flash messages --}}
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

    {{-- Access Scope Badge --}}
    <div class="mb-4">
        @if($canSeeAll)
        <div class="alert alert-info py-2 mb-0 d-inline-flex align-items-center gap-2">
            <i class="bx bx-globe fs-5"></i>
            <span><strong>Cakupan:</strong> Semua Direktorat</span>
        </div>
        @elseif($canSeeDir)
        <div class="alert alert-warning py-2 mb-0 d-inline-flex align-items-center gap-2">
            <i class="bx bx-buildings fs-5"></i>
            <span><strong>Cakupan:</strong> Direktorat Anda</span>
        </div>
        @else
        <div class="alert alert-secondary py-2 mb-0 d-inline-flex align-items-center gap-2">
            <i class="bx bx-group fs-5"></i>
            <span><strong>Cakupan:</strong> Departemen Anda</span>
        </div>
        @endif
    </div>

    {{-- Period Filter --}}
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="row g-3 align-items-center">
                <div class="col-auto">
                    <label class="col-form-label fw-semibold">
                        <i class="bx bx-filter-alt me-1"></i>Filter Periode
                    </label>
                </div>
                <div class="col-md-4">
                    <select class="form-select" wire:model.live="filterPeriod" id="filter-period-compilation">
                        <option value="">Semua Periode</option>
                        @foreach($periods as $period)
                        <option value="{{ $period->id }}">{{ $period->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto ms-auto">
                    <span class="text-muted small">
                        <i class="bx bx-info-circle me-1"></i>
                        Hanya menampilkan pengajuan berstatus <strong>Disetujui Final</strong>
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary Stats --}}
    <div class="row g-4 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="avatar avatar-md flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-file bx-sm"></i></span>
                    </div>
                    <div>
                        <div class="fw-bold fs-4">{{ $submissions->count() }}</div>
                        <div class="text-muted small">Total Pengajuan</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="avatar avatar-md flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-info"><i class="bx bx-buildings bx-sm"></i></span>
                    </div>
                    <div>
                        <div class="fw-bold fs-4">{{ $bureauCount }}</div>
                        <div class="text-muted small">Biro</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="avatar avatar-md flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-warning"><i class="bx bx-layer bx-sm"></i></span>
                    </div>
                    <div>
                        <div class="fw-bold fs-4">{{ $deptCount }}</div>
                        <div class="text-muted small">Departemen</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="avatar avatar-md flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-success"><i class="bx bx-money bx-sm"></i></span>
                    </div>
                    <div>
                        <div class="fw-bold fs-5">Rp {{ number_format($grandTotal, 0, ',', '.') }}</div>
                        <div class="text-muted small">Total Anggaran</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Compilation Table --}}
    @if($grouped->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="bx bx-file-blank bx-lg d-block mb-3"></i>
            <div class="fw-semibold mb-1">Belum ada data kompilasi</div>
            <small>
                @if(!$filterPeriod)
                    Pilih periode untuk melihat kompilasi pengajuan yang telah disetujui.
                @else
                    Tidak ada pengajuan berstatus <strong>Disetujui Final</strong> pada periode ini dalam cakupan Anda.
                @endif
            </small>
        </div>
    </div>
    @else

    {{-- Grouped by Directorate --}}
    @php $dirLoop = 0; @endphp
    @foreach($grouped as $dirName => $deptGroups)
    @php
        $dirTotal   = 0;
        $dirBureaus = 0;
        foreach ($deptGroups as $deptSubmissions) {
            $dirTotal   += $deptSubmissions->sum('total_budget');
            $dirBureaus += $deptSubmissions->count();
        }
        $collapseId = 'dir-collapse-' . $dirLoop++;
    @endphp

    <div class="card mb-3">
        {{-- Directorate accordion header --}}
        <div class="card-header py-3 d-flex align-items-center justify-content-between"
             style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="true">
            <div class="d-flex align-items-center gap-2">
                <i class="bx bx-chevron-down fs-5 text-primary transition-transform"></i>
                <div>
                    <span class="fw-bold text-primary">{{ $dirName }}</span>
                    <span class="badge bg-label-primary ms-2">{{ $deptGroups->count() }} Departemen</span>
                    <span class="badge bg-label-info ms-1">{{ $dirBureaus }} Biro</span>
                </div>
            </div>
            <div class="text-end">
                <span class="has-tooltip fw-bold text-dark">
                    Rp {{ number_format($dirTotal, 0, ',', '.') }}
                    <span class="custom-tooltip-content tooltip-align-right">
                        @if(isset($prevDataMap['directorates'][$dirName]))
                            @php
                                $prev = $prevDataMap['directorates'][$dirName];
                            @endphp
                            <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya ({{ $prev['period_title'] }})</div>
                            <div class="row text-center">
                                <div class="col-4 border-end">
                                    <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                                    <div class="fw-bold text-white" style="font-size: 0.75rem;">Rp {{ number_format($prev['budget'], 0, ',', '.') }}</div>
                                </div>
                                <div class="col-4 border-end">
                                    <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                                    <div class="fw-bold text-white text-success" style="font-size: 0.75rem;">Rp {{ number_format($prev['realization'], 0, ',', '.') }}</div>
                                </div>
                                <div class="col-4">
                                    <div class="text-white-50 small" style="font-size: 0.65rem;">Proyeksi</div>
                                    <div class="fw-bold text-white text-warning" style="font-size: 0.75rem;">Rp {{ number_format($prev['projection'] ?? 0, 0, ',', '.') }}</div>
                                </div>
                            </div>
                        @else
                            <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
                        @endif
                    </span>
                </span>
                <div class="text-muted small">Total Direktorat</div>
            </div>
        </div>

        <div class="collapse show" id="{{ $collapseId }}">
            @foreach($deptGroups as $deptName => $deptSubmissions)
            @php
                $deptTotal = $deptSubmissions->sum('total_budget');
            @endphp
            <div class="border-top">
                {{-- Department sub-header --}}
                <div class="px-4 py-2 bg-lighter d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bx bx-layer text-secondary me-1"></i>
                        <span class="fw-semibold text-secondary">{{ $deptName }}</span>
                        <span class="badge bg-label-secondary ms-2">{{ $deptSubmissions->count() }} Biro</span>
                    </div>
                    <span class="has-tooltip fw-semibold text-secondary small">
                        Rp {{ number_format($deptTotal, 0, ',', '.') }}
                        <span class="custom-tooltip-content tooltip-align-right">
                            @if(isset($prevDataMap['departments'][$deptName]))
                                @php
                                    $prev = $prevDataMap['departments'][$deptName];
                                @endphp
                                <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya ({{ $prev['period_title'] }})</div>
                                <div class="row text-center">
                                    <div class="col-4 border-end">
                                        <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                                        <div class="fw-bold text-white" style="font-size: 0.75rem;">Rp {{ number_format($prev['budget'], 0, ',', '.') }}</div>
                                    </div>
                                    <div class="col-4 border-end">
                                        <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                                        <div class="fw-bold text-white text-success" style="font-size: 0.75rem;">Rp {{ number_format($prev['realization'], 0, ',', '.') }}</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-white-50 small" style="font-size: 0.65rem;">Proyeksi</div>
                                        <div class="fw-bold text-white text-warning" style="font-size: 0.75rem;">Rp {{ number_format($prev['projection'] ?? 0, 0, ',', '.') }}</div>
                                    </div>
                                </div>
                            @else
                                <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
                            @endif
                        </span>
                    </span>
                </div>

                {{-- Bureau rows --}}
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:30px" class="text-center">#</th>
                                <th>Biro</th>
                                <th class="text-center">Periode</th>
                                <th class="text-center">Versi</th>
                                <th class="text-center">Rencana Kerja</th>
                                <th class="text-end">Total Anggaran</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($deptSubmissions as $idx => $submission)
                            <tr>
                                <td class="text-center text-muted">{{ $idx + 1 }}</td>
                                <td>
                                    <strong>{{ $submission->bureau?->name ?? '-' }}</strong>
                                    <div class="small text-muted">{{ $submission->bureau?->code ?? '' }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-label-secondary">
                                        {{ $submission->period?->title ?? '-' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-label-info">v{{ $submission->current_version }}</span>
                                </td>
                                <td class="text-center text-muted">
                                    {{ $submission->workPlans()->count() }}
                                </td>
                                <td class="text-end">
                                    <span class="has-tooltip fw-bold text-dark">
                                        Rp {{ number_format($submission->total_budget, 0, ',', '.') }}
                                        <span class="custom-tooltip-content tooltip-align-right">
                                            @if(isset($prevDataMap['submissions'][$submission->id]))
                                                @php
                                                    $prev = $prevDataMap['submissions'][$submission->id];
                                                @endphp
                                                <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya ({{ $prev['period_title'] }})</div>
                                                <div class="row text-center">
                                                    <div class="col-4 border-end">
                                                        <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                                                        <div class="fw-bold text-white" style="font-size: 0.75rem;">Rp {{ number_format($prev['budget'], 0, ',', '.') }}</div>
                                                    </div>
                                                    <div class="col-4 border-end">
                                                        <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                                                        <div class="fw-bold text-white text-success" style="font-size: 0.75rem;">Rp {{ number_format($prev['realization'], 0, ',', '.') }}</div>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="text-white-50 small" style="font-size: 0.65rem;">Proyeksi</div>
                                                        <div class="fw-bold text-white text-warning" style="font-size: 0.75rem;">Rp {{ number_format($prev['projection'] ?? 0, 0, ',', '.') }}</div>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
                                            @endif
                                        </span>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success">
                                        <i class="bx bx-check-circle me-1"></i>Disetujui
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('rkap-submissions-review', $submission->id) }}"
                                       class="btn btn-sm btn-icon btn-text-secondary rounded-pill"
                                       title="Lihat Detail">
                                        <i class="bx bx-show"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        {{-- Department sub-total --}}
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="5" class="text-end fw-semibold text-secondary">Sub-Total {{ $deptName }}</td>
                                <td class="text-end">
                                    <span class="has-tooltip fw-bold text-secondary">
                                        Rp {{ number_format($deptTotal, 0, ',', '.') }}
                                        <span class="custom-tooltip-content tooltip-align-right">
                                            @if(isset($prevDataMap['departments'][$deptName]))
                                                @php
                                                    $prev = $prevDataMap['departments'][$deptName];
                                                @endphp
                                                <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya ({{ $prev['period_title'] }})</div>
                                                <div class="row text-center">
                                                    <div class="col-4 border-end">
                                                        <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                                                        <div class="fw-bold text-white" style="font-size: 0.75rem;">Rp {{ number_format($prev['budget'], 0, ',', '.') }}</div>
                                                    </div>
                                                    <div class="col-4 border-end">
                                                        <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                                                        <div class="fw-bold text-white text-success" style="font-size: 0.75rem;">Rp {{ number_format($prev['realization'], 0, ',', '.') }}</div>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="text-white-50 small" style="font-size: 0.65rem;">Proyeksi</div>
                                                        <div class="fw-bold text-white text-warning" style="font-size: 0.75rem;">Rp {{ number_format($prev['projection'] ?? 0, 0, ',', '.') }}</div>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
                                            @endif
                                        </span>
                                    </span>
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @endforeach

            {{-- Directorate total row --}}
            <div class="bg-primary text-white px-4 py-2 d-flex justify-content-between align-items-center rounded-bottom">
                <span class="fw-semibold">
                    <i class="bx bx-check-double me-1"></i>Total Direktorat {{ $dirName }}
                </span>
                <span class="has-tooltip fw-bold fs-6 text-white">
                    Rp {{ number_format($dirTotal, 0, ',', '.') }}
                    <span class="custom-tooltip-content tooltip-align-right text-dark">
                        @if(isset($prevDataMap['directorates'][$dirName]))
                            @php
                                $prev = $prevDataMap['directorates'][$dirName];
                            @endphp
                            <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya ({{ $prev['period_title'] }})</div>
                            <div class="row text-center">
                                <div class="col-4 border-end">
                                    <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                                    <div class="fw-bold text-white" style="font-size: 0.75rem;">Rp {{ number_format($prev['budget'], 0, ',', '.') }}</div>
                                </div>
                                <div class="col-4 border-end">
                                    <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                                    <div class="fw-bold text-white text-success" style="font-size: 0.75rem;">Rp {{ number_format($prev['realization'], 0, ',', '.') }}</div>
                                </div>
                                <div class="col-4">
                                    <div class="text-white-50 small" style="font-size: 0.65rem;">Proyeksi</div>
                                    <div class="fw-bold text-white text-warning" style="font-size: 0.75rem;">Rp {{ number_format($prev['projection'] ?? 0, 0, ',', '.') }}</div>
                                </div>
                            </div>
                        @else
                            <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
                        @endif
                    </span>
                </span>
            </div>
        </div>
    </div>
    @endforeach

    {{-- Grand Total Card --}}
    <div class="card border-0 mt-3" style="background: linear-gradient(135deg, #1A3C6E 0%, #2563EB 100%);">
        <div class="card-body py-3 d-flex justify-content-between align-items-center text-white">
            <div class="d-flex align-items-center gap-3">
                <i class="bx bx-wallet-alt bx-md"></i>
                <div>
                    <div class="fw-bold fs-5">GRAND TOTAL KOMPILASI</div>
                    <div class="small opacity-75">
                        {{ $submissions->count() }} Pengajuan &bull;
                        {{ $bureauCount }} Biro &bull;
                        {{ $deptCount }} Departemen
                        @if($canSeeAll || $canSeeDir)
                        &bull; {{ $dirCount }} Direktorat
                        @endif
                    </div>
                </div>
            </div>
             <div class="text-end">
                <span class="has-tooltip fw-bold fs-4 text-white">
                    Rp {{ number_format($grandTotal, 0, ',', '.') }}
                    <span class="custom-tooltip-content tooltip-align-right text-dark">
                        @if($prevDataMap['grand_total']['period_title'])
                            <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya ({{ $prevDataMap['grand_total']['period_title'] }})</div>
                            <div class="row text-center">
                                <div class="col-4 border-end">
                                    <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                                    <div class="fw-bold text-white" style="font-size: 0.75rem;">Rp {{ number_format($prevDataMap['grand_total']['budget'], 0, ',', '.') }}</div>
                                </div>
                                <div class="col-4 border-end">
                                    <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                                    <div class="fw-bold text-white text-success" style="font-size: 0.75rem;">Rp {{ number_format($prevDataMap['grand_total']['realization'], 0, ',', '.') }}</div>
                                </div>
                                <div class="col-4">
                                    <div class="text-white-50 small" style="font-size: 0.65rem;">Proyeksi</div>
                                    <div class="fw-bold text-white text-warning" style="font-size: 0.75rem;">Rp {{ number_format($prevDataMap['grand_total']['projection'] ?? 0, 0, ',', '.') }}</div>
                                </div>
                            </div>
                        @else
                            <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
                        @endif
                    </span>
                </span>
                @if($selectedPeriod)
                <div class="small opacity-75">{{ $selectedPeriod->title }}</div>
                @endif
            </div>
        </div>
    </div>

    @endif
</div>
