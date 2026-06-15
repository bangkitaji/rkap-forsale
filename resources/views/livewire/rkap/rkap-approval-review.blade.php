<div>
    <style>
        /* Custom CSS Tooltip styling */
        .has-tooltip {
            position: relative;
            cursor: help;
        }
        .custom-tooltip-content {
            visibility: hidden;
            width: 400px;
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
    <div class="d-flex justify-content-between align-items-center py-3 mb-4">
        <h4 class="mb-0">
            <span class="text-muted fw-light">RKAP / <a href="{{ route('rkap-submissions') }}" class="text-muted text-decoration-none">Pengajuan</a> /</span>
            Review & Persetujuan RKAP
        </h4>
        <div>
            <span class="badge bg-{{ $submission->status_color }} fs-6">{{ $submission->status_label }}</span>
        </div>
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

    @if(auth()->user()->isPresidentDirector() && !$this->presidentApprovalStatus['is_ready'])
    <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
        <span class="badge bg-warning text-white me-3 p-1"><i class="bx bx-error fs-4"></i></span>
        <div>
            <h6 class="alert-heading mb-1 fw-bold text-warning" style="color: #ffab00 !important;">Persetujuan Ditangguhkan (Persetujuan Belum Dapat Dilakukan)</h6>
            <span>
                Persetujuan akhir oleh Direktur Utama hanya dapat dilakukan setelah <strong>seluruh departemen</strong> menyelesaikan pengajuan RKAP dan telah diverifikasi oleh verifikator. 
                Saat ini baru <strong>{{ $this->presidentApprovalStatus['verified_count'] }} dari {{ $this->presidentApprovalStatus['total_count'] }}</strong> departemen yang terverifikasi.
            </span>
            @if(!empty($this->presidentApprovalStatus['pending_departments']))
                <div class="mt-2 small text-muted">
                    <strong>Departemen yang belum terverifikasi:</strong> 
                    <span class="text-danger fw-bold">{{ implode(', ', $this->presidentApprovalStatus['pending_departments']) }}</span>
                </div>
            @endif
        </div>
    </div>
    @endif

    <div class="row">
        <!-- Main Content: RKAP Details -->
        <div class="col-xl-9 col-lg-8">
            <!-- Header Info with Comparison -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="text-muted small">Biro Pengaju</label>
                            <div class="fw-semibold">{{ $submission->bureau->name ?? '-' }}</div>
                            <div class="text-muted small">{{ $submission->bureau->department->directorate->name ?? '-' }}</div>
                        </div>
                        <div class="col-sm-3">
                            <label class="text-muted small">Periode</label>
                            <div class="fw-semibold">{{ $submission->period->title ?? '-' }}</div>
                        </div>
                        <div class="col-sm-3">
                            <label class="text-muted small">Total Anggaran Ajuan</label>
                            <div class="fw-bold text-primary fs-5">Rp {{ number_format($submission->total_budget, 0, ',', '.') }}</div>
                            
                            @if(isset($prevData['total_budget']))
                                @php
                                    $prevTotal = $prevData['total_budget'];
                                    $totalDiff = $submission->total_budget - $prevTotal;
                                    $totalPct = $prevTotal > 0 ? ($totalDiff / $prevTotal) * 100 : 0;
                                @endphp
                                <div class="small mt-1 p-1 bg-lighter rounded">
                                    <span class="text-muted d-block" style="font-size: 0.65rem;">Sebelumnya ({{ $prevData['period'] }}):</span>
                                    <span class="fw-semibold text-secondary" style="font-size: 0.72rem;">Rp {{ number_format($prevTotal, 0, ',', '.') }}</span>
                                    <span class="fw-bold d-block mt-0.5 @if($totalDiff > 0) text-danger @elseif($totalDiff < 0) text-success @else text-muted @endif" style="font-size: 0.72rem;">
                                        @if($totalDiff > 0)
                                            <i class="bx bx-trending-up" style="font-size: 0.8rem;"></i> +{{ number_format($totalPct, 1) }}%
                                        @elseif($totalDiff < 0)
                                            <i class="bx bx-trending-down" style="font-size: 0.8rem;"></i> -{{ number_format(abs($totalPct), 1) }}%
                                        @else
                                            = 0%
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                    @if($submission->notes)
                    <hr class="my-3">
                    <label class="text-muted small">Catatan Pengajuan</label>
                    <p class="mb-0">{{ $submission->notes }}</p>
                    @endif
                </div>
            </div>

            @if(auth()->user()->isPresidentDirector())
                <!-- Helicopter View -->
                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                    <div>
                        <h5 class="mb-1"><i class="bx bx-spreadsheet me-2 text-primary"></i>Helikopter-View Pengajuan RKAP</h5>
                        <p class="text-muted small mb-0">Menampilkan akumulasi anggaran yang dikompilasi berdasarkan kategori Profit & Loss.</p>
                    </div>
                    <span class="badge bg-label-primary">Direktur Utama Approval</span>
                </div>

                @php
                    $groupedCategories = collect($helicopterViewData['categories'])->groupBy('group');
                @endphp

                @foreach($groupedCategories as $groupName => $cats)
                    <h6 class="text-uppercase text-muted small fw-bold mb-3 mt-4" style="letter-spacing: 1px;">
                        <i class="bx bx-folder-open me-1 text-secondary"></i> {{ $groupName }}
                    </h6>
                    <div class="row g-3">
                        @foreach($cats as $cat)
                            <div class="col-12">
                                <div class="card mb-2 border-start border-{{ $cat['color'] }} border-3 shadow-sm">
                                    <div class="card-body py-3">
                                        <div class="d-flex justify-content-between align-items-center cursor-pointer collapsed" data-bs-toggle="collapse" data-bs-target="#cat-collapse-{{ $cat['key'] }}" aria-expanded="false" style="user-select: none;">
                                            <div>
                                                <h6 class="mb-1 fw-bold text-dark">{{ $cat['label'] }}</h6>
                                                <small class="text-muted"><i class="bx bx-chevron-down me-1"></i> Klik untuk melihat rincian {{ count($cat['coas']) }} COA</small>
                                            </div>
                                            <div class="text-end">
                                                <span class="text-muted small d-block" style="font-size: 0.7rem;">Anggaran Diajukan</span>
                                                <span class="fw-bold text-{{ $cat['color'] }} fs-5">Rp {{ number_format($cat['current_total'], 0, ',', '.') }}</span>
                                                
                                                @if($helicopterViewData['prevPeriod'])
                                                    @php
                                                        $prevTotal = $cat['prev_total'];
                                                        $diff = $cat['current_total'] - $prevTotal;
                                                        $pct = $prevTotal > 0 ? ($diff / $prevTotal) * 100 : 0;
                                                    @endphp
                                                    <div style="font-size: 0.75rem; line-height: 1.2;">
                                                        <span class="text-muted">Sebelumnya ({{ $helicopterViewData['prevPeriod'] }}): Rp {{ number_format($prevTotal, 0, ',', '.') }}</span>
                                                        <span class="fw-bold ms-1 @if($diff > 0) text-danger @elseif($diff < 0) text-success @else text-muted @endif">
                                                            @if($diff > 0)
                                                                ↑ +{{ number_format($pct, 1) }}%
                                                            @elseif($diff < 0)
                                                                ↓ -{{ number_format(abs($pct), 1) }}%
                                                            @else
                                                                = 0%
                                                            @endif
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        <!-- Collapsible Details -->
                                        <div class="collapse mt-3" id="cat-collapse-{{ $cat['key'] }}">
                                            <hr class="my-2">
                                            @if(empty($cat['coas']))
                                                <div class="text-center py-2 text-muted small">
                                                    Tidak ada COA yang dianggarkan dalam kategori ini.
                                                </div>
                                            @else
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-hover table-striped mb-0" style="font-size: 0.8rem;">
                                                        <thead>
                                                            <tr>
                                                                <th style="width: 20%;">Kode Akun</th>
                                                                <th style="width: 50%;">Judul Akun (COA)</th>
                                                                <th class="text-end" style="width: 15%;">Anggaran Ajuan</th>
                                                                <th class="text-end" style="width: 15%;">Selisih (Δ)</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($cat['coas'] as $coaItem)
                                                                @php
                                                                    $coaDiff = $coaItem['current_total'] - $coaItem['prev_total'];
                                                                    $coaPct = $coaItem['prev_total'] > 0 ? ($coaDiff / $coaItem['prev_total']) * 100 : 0;
                                                                @endphp
                                                                <tr>
                                                                    <td><strong>{{ $coaItem['code'] }}</strong></td>
                                                                    <td class="text-wrap">{{ $coaItem['title'] }}</td>
                                                                    <td class="text-end fw-semibold text-primary">Rp {{ number_format($coaItem['current_total'], 0, ',', '.') }}</td>
                                                                    <td class="text-end fw-semibold @if($coaDiff > 0) text-danger @elseif($coaDiff < 0) text-success @else text-muted @endif">
                                                                        @if($coaItem['prev_total'] > 0)
                                                                            @if($coaDiff > 0)
                                                                                ↑ +{{ number_format($coaPct, 1) }}%
                                                                            @elseif($coaDiff < 0)
                                                                                ↓ -{{ number_format(abs($coaPct), 1) }}%
                                                                            @else
                                                                                = 0%
                                                                            @endif
                                                                        @else
                                                                            <span class="text-muted small">Baru</span>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                @php
                    $hasUnmapped = collect($helicopterViewData['unmappedGroups'])->contains(fn($g) => $g['current_total'] > 0 || $g['prev_total'] > 0);
                @endphp

                @if($hasUnmapped)
                    <h5 class="mt-5 mb-3 text-secondary border-bottom pb-2">
                        <i class="bx bx-bracket me-2 text-secondary"></i>Akun COA Neraca & Lainnya (Belum Dipetakan)
                    </h5>
                    <div class="row g-3">
                        @foreach($helicopterViewData['unmappedGroups'] as $group)
                            @if($group['current_total'] > 0 || $group['prev_total'] > 0)
                                <div class="col-12">
                                    <div class="card mb-2 border-start border-secondary border-3 shadow-sm">
                                        <div class="card-body py-3">
                                            <div class="d-flex justify-content-between align-items-center cursor-pointer collapsed" data-bs-toggle="collapse" data-bs-target="#group-collapse-{{ $group['group_id'] }}" aria-expanded="false" style="user-select: none;">
                                                <div>
                                                    <h6 class="mb-1 fw-bold text-secondary">{{ $group['group_name'] }} (Grup: {{ $group['group_code'] }})</h6>
                                                    <small class="text-muted"><i class="bx bx-chevron-down me-1"></i> Klik untuk melihat rincian {{ count($group['coas']) }} COA</small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="text-muted small d-block" style="font-size: 0.7rem;">Anggaran Diajukan</span>
                                                    <span class="fw-bold text-secondary fs-5">Rp {{ number_format($group['current_total'], 0, ',', '.') }}</span>
                                                    
                                                    @if($helicopterViewData['prevPeriod'])
                                                        @php
                                                            $prevTotal = $group['prev_total'];
                                                            $diff = $group['current_total'] - $prevTotal;
                                                            $pct = $prevTotal > 0 ? ($diff / $prevTotal) * 100 : 0;
                                                        @endphp
                                                        <div style="font-size: 0.75rem; line-height: 1.2;">
                                                            <span class="text-muted">Sebelumnya ({{ $helicopterViewData['prevPeriod'] }}): Rp {{ number_format($prevTotal, 0, ',', '.') }}</span>
                                                            <span class="fw-bold ms-1 @if($diff > 0) text-danger @elseif($diff < 0) text-success @else text-muted @endif">
                                                                @if($diff > 0)
                                                                    ↑ +{{ number_format($pct, 1) }}%
                                                                @elseif($diff < 0)
                                                                    ↓ -{{ number_format(abs($pct), 1) }}%
                                                                @else
                                                                    = 0%
                                                                @endif
                                                            </span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                            
                                            <!-- Collapsible Details -->
                                            <div class="collapse mt-3" id="group-collapse-{{ $group['group_id'] }}">
                                                <hr class="my-2">
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-hover table-striped mb-0" style="font-size: 0.8rem;">
                                                        <thead>
                                                            <tr>
                                                                <th style="width: 20%;">Kode Akun</th>
                                                                <th style="width: 50%;">Judul Akun (COA)</th>
                                                                <th class="text-end" style="width: 15%;">Anggaran Ajuan</th>
                                                                <th class="text-end" style="width: 15%;">Selisih (Δ)</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($group['coas'] as $coaItem)
                                                                @php
                                                                    $coaDiff = $coaItem['current_total'] - $coaItem['prev_total'];
                                                                    $coaPct = $coaItem['prev_total'] > 0 ? ($coaDiff / $coaItem['prev_total']) * 100 : 0;
                                                                @endphp
                                                                <tr>
                                                                    <td><strong>{{ $coaItem['code'] }}</strong></td>
                                                                    <td class="text-wrap">{{ $coaItem['title'] }}</td>
                                                                    <td class="text-end fw-semibold text-primary">Rp {{ number_format($coaItem['current_total'], 0, ',', '.') }}</td>
                                                                    <td class="text-end fw-semibold @if($coaDiff > 0) text-danger @elseif($coaDiff < 0) text-success @else text-muted @endif">
                                                                        @if($coaItem['prev_total'] > 0)
                                                                            @if($coaDiff > 0)
                                                                                ↑ +{{ number_format($coaPct, 1) }}%
                                                                            @elseif($coaDiff < 0)
                                                                                ↓ -{{ number_format(abs($coaPct), 1) }}%
                                                                            @else
                                                                                = 0%
                                                                            @endif
                                                                        @else
                                                                            <span class="text-muted small">Baru</span>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

            @else
                <!-- Work Plans -->
                <h5 class="mb-3">Rincian Program Kerja</h5>
                @foreach($combinedWorkPlans as $idx => $wp)
                @php
                    $isWpVirtual = $wp['is_virtual'] ?? false;
                @endphp
                <div class="card mb-3 border-start border-primary border-3 @if($isWpVirtual) border-danger @endif" style="@if($isWpVirtual) border-left-color: #ff3e1d !important; background-color: #fff5f5; @endif">
                    <div class="card-header border-bottom" style="@if($isWpVirtual) background-color: #fff0f0; @endif">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 @if($isWpVirtual) text-danger @else text-primary @endif">
                                    {{ $wp['program_code'] }} - {{ $wp['program_name'] }}
                                    @if($isWpVirtual)
                                        <span class="badge bg-danger ms-2">Tidak Diajukan Kembali</span>
                                    @endif
                                </h6>
                                @if($wp['description'])
                                <p class="text-muted small mb-0">{{ $wp['description'] }}</p>
                                @endif
                            </div>
                            <div class="text-end">
                                <span class="text-muted small d-block">Subtotal Kegiatan</span>
                                <strong class="text-dark has-tooltip">
                                    Rp {{ number_format($wp['total_budget'], 0, ',', '.') }}
                                    <span class="custom-tooltip-content tooltip-align-right">
                                        @php
                                            $prevWpId = $wp['work_plan_id'] ?? null;
                                            $prevActId = $wp['activity_id'] ?? null;
                                            $actKey = ($prevWpId && $prevActId) ? "{$prevWpId}-{$prevActId}" : null;
                                            $prevActivityData = ($actKey && isset($prevData['map']['activities'][$actKey])) ? $prevData['map']['activities'][$actKey] : null;
                                            $prevPeriod = $prevData['period'] ?? '-';
                                        @endphp
                                        @if ($prevActivityData)
                                            <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya ({{ $prevPeriod }})</div>
                                            <div class="row text-center">
                                                <div class="col-4 border-end">
                                                    <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                                                    <div class="fw-bold text-white" style="font-size: 0.75rem;">Rp {{ number_format($prevActivityData['budget'], 0, ',', '.') }}</div>
                                                </div>
                                                <div class="col-4 border-end">
                                                    <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                                                    <div class="fw-bold text-white text-success" style="font-size: 0.75rem;">Rp {{ number_format($prevActivityData['realization'], 0, ',', '.') }}</div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="text-white-50 small" style="font-size: 0.65rem;">Proyeksi</div>
                                                    <div class="fw-bold text-white text-warning" style="font-size: 0.75rem;">Rp {{ number_format($prevActivityData['projection'] ?? 0, 0, ',', '.') }}</div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
                                        @endif
                                    </span>
                                </strong>
                            </div>
                        </div>
                        <div class="row mt-2 g-2 small">
                            <div class="col-auto">
                                <span class="text-muted">Target Output:</span> <span class="fw-medium">{{ $wp['output_target'] ?? '-' }}</span>
                            </div>
                            <div class="col-auto">
                                <span class="text-muted">Volume:</span> <span class="fw-medium">{{ $wp['quantity'] }} {{ $wp['unit'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Kode Akun</th>
                                        <th>Uraian & Detail Belanja</th>
                                        <th class="text-center">Vol</th>
                                        <th>Satuan</th>
                                        <th class="text-end">Harga Satuan</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end" style="width: 12%;">RKAP Sblm</th>
                                        <th class="text-end" style="width: 12%;">Selisih (Δ)</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($wp['grouped_items'] as $accountCode => $items)
                                    @php
                                    $firstItem = $items[0];
                                    $coaGroupSubtotal = collect($items)->sum('total_price');
                                    $prevWpId   = $wp['work_plan_id'] ?? null;
                                    $prevCode   = $accountCode;
                                    @endphp
                                    <tr class="table-light fw-semibold">
                                        <td colspan="9" class="text-dark bg-lighter py-2 px-3">
                                            <div class="d-flex justify-content-between align-items-center gap-2">
                                                <div class="d-flex align-items-center gap-1 min-w-0">
                                                    <i class="bx bx-subdirectory-right text-primary flex-shrink-0"></i>
                                                    <span class="text-truncate"><strong>{{ $accountCode ?? '-' }}</strong> — {{ $firstItem['description'] }}</span>
                                                </div>
                                                <div class="d-flex align-items-center gap-3 flex-shrink-0 text-end">
                                                    @php
                                                        $prevActId = $wp['activity_id'] ?? null;
                                                        $coaKey = ($prevWpId && $prevActId && $prevCode) ? "{$prevWpId}-{$prevActId}-{$prevCode}" : null;
                                                        $prevCoaData = ($coaKey && isset($prevData['map']['coas'][$coaKey])) ? $prevData['map']['coas'][$coaKey] : null;
                                                        $prevPeriod = $prevData['period'] ?? '-';
                                                        
                                                        $prevCoaBudget = $prevCoaData ? (float) $prevCoaData['budget'] : 0.0;
                                                        $coaDiff = $coaGroupSubtotal - $prevCoaBudget;
                                                        $coaPct = $prevCoaBudget > 0 ? ($coaDiff / $prevCoaBudget) * 100 : 0;
                                                    @endphp
                                                    
                                                    @if($prevCoaData)
                                                    <div style="font-size:0.75rem; line-height:1.2;">
                                                        <span class="text-muted" style="font-size:0.68rem;">Sblm ({{ $prevPeriod }}):</span>
                                                        <span class="fw-semibold text-secondary">Rp {{ number_format($prevCoaBudget, 0, ',', '.') }}</span>
                                                    </div>
                                                    <div style="font-size:0.75rem; line-height:1.2;">
                                                        <span class="text-muted" style="font-size:0.68rem;">Selisih:</span>
                                                        <span class="fw-bold @if($coaDiff > 0) text-danger @elseif($coaDiff < 0) text-success @else text-muted @endif">
                                                            @if($coaDiff > 0)
                                                                ↑ +{{ number_format($coaPct, 1) }}%
                                                            @elseif($coaDiff < 0)
                                                                ↓ -{{ number_format(abs($coaPct), 1) }}%
                                                            @else
                                                                = 0%
                                                            @endif
                                                        </span>
                                                    </div>
                                                    @endif

                                                    <div class="has-tooltip" style="font-size:0.78rem; line-height:1.2;">
                                                        <span class="text-muted" style="font-size:0.68rem;">Sub-total:</span>
                                                        <span class="fw-bold text-primary">
                                                            Rp {{ number_format($coaGroupSubtotal, 0, ',', '.') }}
                                                        </span>
                                                        <span class="custom-tooltip-content tooltip-align-right">
                                                            @if ($prevCoaData)
                                                                <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya ({{ $prevPeriod }})</div>
                                                                <div class="row text-center">
                                                                    <div class="col-4 border-end">
                                                                        <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                                                                        <div class="fw-bold text-white" style="font-size: 0.75rem;">Rp {{ number_format($prevCoaData['budget'], 0, ',', '.') }}</div>
                                                                    </div>
                                                                    <div class="col-4 border-end">
                                                                        <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                                                                        <div class="fw-bold text-white text-success" style="font-size: 0.75rem;">Rp {{ number_format($prevCoaData['realization'], 0, ',', '.') }}</div>
                                                                    </div>
                                                                    <div class="col-4">
                                                                        <div class="text-white-50 small" style="font-size: 0.65rem;">Proyeksi</div>
                                                                        <div class="fw-bold text-white text-warning" style="font-size: 0.75rem;">Rp {{ number_format($prevCoaData['projection'] ?? 0, 0, ',', '.') }}</div>
                                                                    </div>
                                                                </div>
                                                            @else
                                                                <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
                                                            @endif
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @foreach($items as $bi)
                                    @php
                                    $isBiVirtual = $bi['is_virtual'] ?? false;
                                    $allocationModalId = 'allocationDetailModal-' . ($isBiVirtual ? md5($bi['account_code'] . $bi['description']) : $bi['model']->id);
                                    $monthNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
                                    
                                    $prevActId = $wp['activity_id'] ?? null;
                                    $itemKey = ($prevWpId && $prevActId && $accountCode) ? "{$prevWpId}-{$prevActId}-{$accountCode}-" . trim(strtolower($bi['description'])) : null;
                                    $prevItemData = ($itemKey && isset($prevData['map']['items'][$itemKey])) ? $prevData['map']['items'][$itemKey] : null;
                                    $prevItemBudget = $prevItemData ? (float) $prevItemData['budget'] : null;
                                    $itemDiff = $prevItemBudget !== null ? ($bi['total_price'] - $prevItemBudget) : null;
                                    $itemPct = ($prevItemBudget !== null && $prevItemBudget > 0) ? ($itemDiff / $prevItemBudget) * 100 : 0;
                                    @endphp
                                    <tr @if($isBiVirtual) style="background-color: #fff9f9;" @endif>
                                        <td class="text-center text-muted">
                                            <span class="ps-2">•</span>
                                        </td>
                                        <td>
                                            <span class="@if($isBiVirtual) text-danger text-decoration-line-through @endif">
                                                {{ $bi['remarks'] ?: $bi['description'] }}
                                            </span>
                                            @if($isBiVirtual)
                                                <span class="badge bg-label-danger ms-1" style="font-size: 0.6rem;">Dihapus</span>
                                            @endif
                                        </td>
                                        <td class="text-center @if($isBiVirtual) text-danger @endif">{{ $bi['quantity'] }}</td>
                                        <td class="@if($isBiVirtual) text-danger @endif">{{ $bi['unit'] }}</td>
                                        <td class="text-end @if($isBiVirtual) text-danger @endif">Rp {{ number_format($bi['unit_price'], 0, ',', '.') }}</td>
                                        <td class="text-end">
                                            <div class="fw-semibold @if($isBiVirtual) text-danger @else text-primary @endif">Rp {{ number_format($bi['total_price'], 0, ',', '.') }}</div>
                                        </td>
                                        <!-- RKAP Sblm -->
                                        <td class="text-end text-secondary" style="font-size: 0.8rem;">
                                            @if($prevItemBudget !== null)
                                                Rp {{ number_format($prevItemBudget, 0, ',', '.') }}
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                        <!-- Selisih (Δ) -->
                                        <td class="text-end fw-semibold @if($itemDiff > 0) text-danger @elseif($itemDiff < 0) text-success @else text-muted @endif" style="font-size: 0.8rem;">
                                            @if($itemDiff !== null)
                                                @if($itemDiff > 0)
                                                    ↑ +{{ number_format($itemPct, 1) }}%
                                                @elseif($itemDiff < 0)
                                                    ↓ -{{ number_format(abs($itemPct), 1) }}%
                                                @else
                                                    = 0%
                                                @endif
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if(!$isBiVirtual && ($bi['monthlies']->isNotEmpty() || $bi['cashOuts']->isNotEmpty()))
                                            <div class="d-flex justify-content-center">
                                                <button type="button" class="btn btn-xs btn-outline-primary" data-bs-toggle="modal" data-bs-target="#{{ $allocationModalId }}" title="Detail Alokasi">
                                                    <i class="bx bx-detail"></i>
                                                </button>
                                                <!-- Modal Detail Alokasi (Merged) -->
                                                <div class="modal fade" id="{{ $allocationModalId }}" tabindex="-1" aria-hidden="true" wire:key="allocation-modal-{{ $bi['model']->id }}">
                                                    <div class="modal-content text-start">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title d-flex align-items-center">
                                                                <i class="bx bx-info-circle me-2 text-primary fs-4"></i>Detail Alokasi Anggaran
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <!-- Banner Informasi Rincian Belanja -->
                                                            <div class="card bg-lighter shadow-none border mb-4">
                                                                <div class="card-body py-3 px-4">
                                                                    <div class="row g-3 small">
                                                                        <div class="col-md-3 border-end">
                                                                            <span class="text-muted d-block mb-1">Kode Akun</span>
                                                                            <span class="fw-semibold text-dark fs-6">{{ $bi['account_code'] ?? '-' }}</span>
                                                                        </div>
                                                                        <div class="col-md-5 border-end">
                                                                            <span class="text-muted d-block mb-1">Deskripsi / Detail Belanja</span>
                                                                            <span class="fw-semibold text-dark fs-6 text-wrap">{{ $bi['description'] }}</span>
                                                                            @if($bi['remarks'])
                                                                            <div class="text-muted mt-1 small">Ket: {{ $bi['remarks'] }}</div>
                                                                            @endif
                                                                        </div>
                                                                        <div class="col-md-2 border-end">
                                                                            <span class="text-muted d-block mb-1">Volume</span>
                                                                            <span class="fw-semibold text-dark fs-6">{{ $bi['quantity'] }} {{ $bi['unit'] }}</span>
                                                                        </div>
                                                                        <div class="col-md-2">
                                                                            <span class="text-muted d-block mb-1">Total Anggaran</span>
                                                                            <span class="fw-bold text-primary fs-6">Rp {{ number_format($bi['total_price'], 0, ',', '.') }}</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- Side-by-side Tables -->
                                                            <div class="row g-4">
                                                                <!-- Left Column: Distribusi Bulanan -->
                                                                <div class="col-md-6">
                                                                    <div class="border rounded p-3 h-100">
                                                                        <h6 class="fw-semibold mb-3 text-primary d-flex align-items-center">
                                                                            <i class="bx bx-calendar me-2"></i>Distribusi Bulanan
                                                                        </h6>
                                                                        @php
                                                                        $activeMonthlies = $bi['monthlies']->filter(fn($m) => (float) $m->amount > 0);
                                                                        @endphp
                                                                        @if($activeMonthlies->isEmpty())
                                                                        <div class="text-center text-muted py-4">
                                                                            <i class="bx bx-info-circle fs-3 mb-2 d-block"></i>
                                                                            <span class="small">Tidak ada data distribusi bulanan</span>
                                                                        </div>
                                                                        @else
                                                                        <div class="table-responsive">
                                                                            <table class="table table-sm table-hover align-middle mb-0">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th>Bulan</th>
                                                                                        <th class="text-end">Jumlah</th>
                                                                                        <th class="text-center" style="width: 25%">Porsi</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    @foreach($activeMonthlies as $monthlyRecord)
                                                                                    @php
                                                                                    $percentage = $bi['total_price'] > 0 ? ($monthlyRecord->amount / $bi['total_price']) * 100 : 0;
                                                                                    @endphp
                                                                                    <tr class="fw-medium text-primary">
                                                                                        <td>{{ $monthNames[$monthlyRecord->month] }}</td>
                                                                                        <td class="text-end">Rp {{ number_format($monthlyRecord->amount, 0, ',', '.') }}</td>
                                                                                        <td class="text-center">
                                                                                            <span class="badge bg-label-primary">{{ number_format($percentage, 0) }}%</span>
                                                                                        </td>
                                                                                    </tr>
                                                                                    @endforeach
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                <!-- Right Column: Rencana Kas Keluar -->
                                                                <div class="col-md-6">
                                                                    <div class="border rounded p-3 h-100">
                                                                        <h6 class="fw-semibold mb-3 text-success d-flex align-items-center">
                                                                            <i class="bx bx-wallet me-2"></i>Rencana Kas Keluar
                                                                        </h6>
                                                                        @php
                                                                        $activeCashOuts = $bi['cashOuts']->filter(fn($c) => (float) $c->amount > 0);
                                                                        @endphp
                                                                        @if($activeCashOuts->isEmpty())
                                                                        <div class="text-center text-muted py-4">
                                                                            <i class="bx bx-info-circle fs-3 mb-2 d-block"></i>
                                                                            <span class="small">Tidak ada data rencana kas keluar</span>
                                                                        </div>
                                                                        @else
                                                                        <div class="table-responsive">
                                                                            <table class="table table-sm table-hover align-middle mb-0">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th>Bulan</th>
                                                                                        <th class="text-end">Jumlah</th>
                                                                                        <th class="text-center" style="width: 25%">Porsi</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    @foreach($activeCashOuts as $cashOutRecord)
                                                                                    @php
                                                                                    $percentage = $bi['total_price'] > 0 ? ($cashOutRecord->amount / $bi['total_price']) * 100 : 0;
                                                                                    @endphp
                                                                                    <tr class="fw-medium text-success">
                                                                                        <td>{{ $monthNames[$cashOutRecord->month] }}</td>
                                                                                        <td class="text-end">Rp {{ number_format($cashOutRecord->amount, 0, ',', '.') }}</td>
                                                                                        <td class="text-center">
                                                                                            <span class="badge bg-label-success">{{ number_format($percentage, 0) }}%</span>
                                                                                        </td>
                                                                                    </tr>
                                                                                    @endforeach
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endforeach
            @endif
        </div>

        <!-- Sidebar: Actions & History -->
        <div class="col-xl-3 col-lg-4">

            <!-- Review Actions (Only if user can review) -->
            @if($this->canApprove())
            <div class="card mb-4 border-primary">
                <div class="card-header bg-label-primary">
                    <h5 class="mb-0 text-primary"><i class="bx bx-check-shield me-2"></i>Aksi Review</h5>
                </div>
                <div class="card-body mt-3">
                    @if($showRevisionForm)
                    <div class="mb-3">
                        <label class="form-label text-danger">Alasan Permintaan Revisi <span class="text-danger">*</span></label>
                        <textarea class="form-control @error('revisionReason') is-invalid @enderror" wire:model="revisionReason" rows="3" placeholder="Sebutkan bagian mana yang perlu diperbaiki..."></textarea>
                        @error('revisionReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-label-secondary w-50" wire:click="$set('showRevisionForm', false)">Batal</button>
                        <button class="btn btn-danger w-50" wire:click="requestRevision" wire:loading.attr="disabled">Kirim Permintaan</button>
                    </div>
                    @else
                    <div class="mb-3">
                        <label class="form-label">Catatan Review (Opsional)</label>
                        <textarea class="form-control" wire:model="reviewComments" rows="2" placeholder="Tinggalkan catatan untuk persetujuan..."></textarea>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <button class="btn btn-success w-100" wire:click="approve" wire:loading.attr="disabled" wire:confirm="Yakin menyetujui RKAP ini?">
                            <i class="bx bx-check-circle me-1"></i> Setujui RKAP
                        </button>
                        <button class="btn btn-outline-danger w-100" wire:click="$set('showRevisionForm', true)">
                            <i class="bx bx-x-circle me-1"></i> Minta Revisi
                        </button>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Comments / Discussion -->
            <div class="card mb-4">
                <div class="card-header border-bottom">
                    <h5 class="mb-0"><i class="bx bx-message-rounded-dots me-2"></i>Pembahasan</h5>
                </div>
                <div class="card-body mt-3" style="max-height: 400px; overflow-y: auto;">
                    @forelse($submission->comments as $comment)
                    <div class="d-flex mb-3">
                        <div class="avatar avatar-sm me-3 flex-shrink-0">
                            <span class="avatar-initial rounded-circle bg-label-primary">{{ substr($comment->user->name, 0, 2) }}</span>
                        </div>
                        <div class="w-100">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="mb-0">{{ $comment->user->name }}</h6>
                                <small class="text-muted">{{ $comment->created_at->diffForHumans() }}</small>
                            </div>
                            <div class="p-2 bg-lighter rounded small">
                                {{ $comment->content }}
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center text-muted my-3">
                        <small>Belum ada pembahasan.</small>
                    </div>
                    @endforelse
                </div>
                <div class="card-footer border-top">
                    <div class="input-group">
                        <input type="text" class="form-control @error('newComment') is-invalid @enderror" wire:model.defer="newComment" placeholder="Ketik pesan..." wire:keydown.enter="addComment">
                        <button class="btn btn-primary" type="button" wire:click="addComment"><i class="bx bx-send"></i></button>
                    </div>
                    @error('newComment') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            <!-- Approval History -->
            <div class="card">
                <div class="card-header border-bottom">
                    <h5 class="mb-0"><i class="bx bx-history me-2"></i>Riwayat Persetujuan</h5>
                </div>
                <div class="card-body mt-3">
                    <ul class="timeline mb-0">
                        @foreach($submission->approvals as $approval)
                        <li class="timeline-item timeline-item-transparent ps-4">
                            <span class="timeline-point timeline-point-{{ $approval->action_color }}"></span>
                            <div class="timeline-event">
                                <div class="timeline-header mb-1">
                                    <h6 class="mb-0">{{ $approval->action_label }}</h6>
                                    <small class="text-muted">{{ $approval->created_at->format('d M Y, H:i') }}</small>
                                </div>
                                <p class="mb-0 small">Oleh: <strong>{{ $approval->user->name }}</strong> ({{ \Illuminate\Support\Str::headline($approval->role) }})</p>
                                @if($approval->comments)
                                <div class="mt-2 p-2 bg-lighter rounded small border-start border-{{ $approval->action_color }} border-3">
                                    <em>"{{ $approval->comments }}"</em>
                                </div>
                                @endif
                            </div>
                        </li>
                        @endforeach
                        <li class="timeline-item timeline-item-transparent ps-4">
                            <span class="timeline-point timeline-point-secondary"></span>
                            <div class="timeline-event pb-0">
                                <div class="timeline-header mb-1">
                                    <h6 class="mb-0">Diajukan</h6>
                                    <small class="text-muted">{{ $submission->created_at->format('d M Y, H:i') }}</small>
                                </div>
                                <p class="mb-0 small">Oleh: <strong>{{ $submission->creator->name ?? '-' }}</strong></p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>
