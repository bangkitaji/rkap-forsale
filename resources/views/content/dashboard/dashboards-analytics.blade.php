@extends('layouts/contentNavbarLayout')

@section('title', 'Dashboard - Analytics')

@section('vendor-style')
@vite('resources/assets/vendor/libs/apex-charts/apex-charts.scss')
@endsection

@section('vendor-script')
@vite('resources/assets/vendor/libs/apex-charts/apexcharts.js')
@endsection

@section('content')
<style>
    .btn-check:checked + .btn-outline-primary {
        color: #fff !important;
        background-color: #696cff !important;
        border-color: #696cff !important;
    }
</style>
<div class="py-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h4 class="mb-1"><span class="text-muted fw-light">RKAP /</span> Analytics</h4>
        @if($activePeriod)
            <p class="text-muted mb-0">Menampilkan visualisasi data untuk periode: <strong>{{ $activePeriod->title }}</strong></p>
        @else
            <div class="alert alert-warning mt-2 mb-0 py-2">
                <i class="bx bx-info-circle me-1"></i> Belum ada periode RKAP yang aktif.
            </div>
        @endif
    </div>

    @if($finalizedPeriods->isNotEmpty())
    <div class="d-flex align-items-center gap-2 bg-white px-3 py-2 rounded shadow-sm border">
        <label for="periodSelect" class="text-muted fw-semibold mb-0 text-nowrap d-flex align-items-center gap-1" style="font-size: 0.9rem;">
            <i class="bx bx-calendar text-primary fs-4"></i>
            <span>Pilih Periode RKAP:</span>
        </label>
        <form action="{{ route('dashboard-analytics') }}" method="GET" id="periodForm" class="m-0">
            <select name="period_id" id="periodSelect" class="form-select form-select-sm border-0 fw-semibold text-primary cursor-pointer focus-ring-none" onchange="this.form.submit()" style="font-size: 0.9rem; padding-right: 2.5rem; background-position: right 0.75rem center;">
                @if($activePeriod && !$finalizedPeriods->contains('id', $activePeriod->id))
                    <option value="" disabled selected>
                        -- Pilih Periode Finalized (Saat ini: {{ $activePeriod->title }}) --
                    </option>
                @endif
                @foreach($finalizedPeriods as $p)
                    <option value="{{ $p->id }}" {{ $activePeriod && $activePeriod->id == $p->id ? 'selected' : '' }}>
                        {{ $p->title }} ({{ $p->year }})
                    </option>
                @endforeach
            </select>
        </form>
    </div>
    @endif
</div>

@if($activePeriod)
    {{-- KPI Cards --}}
    <div class="row g-4 mb-4">
        <!-- Card 1: Total Anggaran -->
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 shadow-none border">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-semibold text-muted">Total Anggaran</span>
                        <span class="badge bg-label-primary rounded p-2"><i class="bx bx-wallet fs-4"></i></span>
                    </div>
                    <h4 class="mb-1 fw-bold">Rp {{ number_format($stats['total_budget'], 0, ',', '.') }}</h4>
                    <p class="mb-0 text-muted small">Pagu Rencana Kerja (RKAP)</p>
                </div>
            </div>
        </div>
        <!-- Card 2: Total Realisasi YTD -->
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 shadow-none border">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-semibold text-muted">Realisasi (YTD)</span>
                        <span class="badge bg-label-success rounded p-2"><i class="bx bx-trending-up fs-4"></i></span>
                    </div>
                    <h4 class="mb-1 fw-bold text-success">Rp {{ number_format($stats['total_realization'], 0, ',', '.') }}</h4>
                    <p class="mb-0 text-muted small">Penyerapan: <strong>{{ $stats['absorption_rate'] }}%</strong></p>
                </div>
            </div>
        </div>
        <!-- Card 3: Outlook Proyeksi -->
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 shadow-none border">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-semibold text-muted">Proyeksi Akhir Tahun</span>
                        <span class="badge bg-label-warning rounded p-2"><i class="bx bx-calculator fs-4"></i></span>
                    </div>
                    <h4 class="mb-1 fw-bold text-warning">Rp {{ number_format($stats['total_projection'], 0, ',', '.') }}</h4>
                    <p class="mb-0 text-muted small">Outlook Rate: <strong>{{ $stats['outlook_rate'] }}%</strong></p>
                </div>
            </div>
        </div>
        <!-- Card 4: Selisih -->
        @php
            $variance = $stats['total_budget'] - $stats['total_projection'];
            $isOver = $variance < 0;
        @endphp
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 shadow-none border">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-semibold text-muted">Sisa Pagu / Efisiensi</span>
                        <span class="badge bg-label-{{ $isOver ? 'danger' : 'info' }} rounded p-2"><i class="bx bx-pie-chart-alt fs-4"></i></span>
                    </div>
                    <h4 class="mb-1 fw-bold text-{{ $isOver ? 'danger' : 'info' }}">Rp {{ number_format(abs($variance), 0, ',', '.') }}</h4>
                    <p class="mb-0 text-muted small">{{ $isOver ? 'Melebihi Anggaran' : 'Sisa Alokasi Pagu' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 4: Annual RKAP Comparison (Relocated below resume cards) --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header border-bottom py-3">
                    <h5 class="card-title mb-0">Komparasi RKAP Antar Tahun</h5>
                    <small class="text-muted">Perbandingan Anggaran, Realisasi YTD, dan Proyeksi Akhir Tahun (Tahun Lalu, Tahun Berjalan, dan Tahun Depan)</small>
                </div>
                <div class="card-body pt-3">
                    <div id="annualComparisonChart" style="min-height: 350px;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 1: Line / Burn-Up & Radial Gauge --}}
    <div class="row mb-4 g-4">
        <!-- Line / Burn-Up Chart -->
        <div class="col-xl-8 col-lg-7 col-12">
            <div class="card h-100">
                <div class="card-header border-bottom py-3">
                    <h5 class="card-title mb-0">Tren Kumulatif Realisasi & Proyeksi</h5>
                    <small class="text-muted">Analisis Pacing Bulanan (Januari - Desember)</small>
                </div>
                <div class="card-body pt-3">
                    <div id="burnUpChart" style="min-height: 330px;"></div>
                </div>
            </div>
        </div>
        <!-- Radial Gauge Utilization -->
        <div class="col-xl-4 col-lg-5 col-12">
            <div class="card h-100">
                <div class="card-header border-bottom py-3">
                    <h5 class="card-title mb-0">Rasio Penyerapan</h5>
                    <small class="text-muted">Realisasi YTD vs. Proyeksi Akhir Tahun</small>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center pt-3">
                    <div id="utilizationGauge"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 2: Division Comparison & COA allocation --}}
    <div class="row g-4">
        <!-- Division Absorption Bar Chart -->
        <div class="{{ auth()->user()->isKepalaDepartemen() ? 'col-12' : 'col-lg-8 col-12' }}">
            <div class="card h-100">
                <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="card-title mb-0">Penyerapan Anggaran</h5>
                        <small class="text-muted">Komparasi Penyerapan per Unit Kerja</small>
                    </div>
                    <div class="btn-group" role="group" aria-label="Comparative data options">
                        @if(auth()->user()->isKepalaDepartemen())
                            <input type="radio" class="btn-check" name="btnComparativeGroup" id="groupDepartment" checked autocomplete="off">
                            <label class="btn btn-outline-primary btn-sm px-3" for="groupDepartment">Departemen</label>
                            
                            <input type="radio" class="btn-check" name="btnComparativeGroup" id="groupBureau" autocomplete="off">
                            <label class="btn btn-outline-primary btn-sm px-3" for="groupBureau">Biro</label>
                        @else
                            <input type="radio" class="btn-check" name="btnComparativeGroup" id="groupDirectorate" checked autocomplete="off">
                            <label class="btn btn-outline-primary btn-sm px-3" for="groupDirectorate">Direktorat</label>
                            
                            <input type="radio" class="btn-check" name="btnComparativeGroup" id="groupDepartment" autocomplete="off">
                            <label class="btn btn-outline-primary btn-sm px-3" for="groupDepartment">Departemen</label>
                        @endif
                    </div>
                </div>
                <div class="card-body pt-3">
                    <div id="divisionChart" style="min-height: 350px;"></div>
                </div>
            </div>
        </div>
        @if(!auth()->user()->isKepalaDepartemen())
        <!-- Profit & Loss Summary Card -->
        <div class="col-lg-4 col-12">
            <div class="card h-100">
                <div class="card-header border-bottom py-3">
                    <h5 class="card-title mb-0">Ringkasan Laba Rugi (P&L Summary)</h5>
                    <small class="text-muted">Ikhtisar Pendapatan & Beban Periode ini</small>
                </div>
                <div class="card-body pt-3">
                    <div class="d-flex flex-column gap-3">
                        <!-- Row 1: Pendapatan -->
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-label-success p-2 rounded me-3">
                                    <i class="bx bx-trending-up fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-semibold">Pendapatan</h6>
                                    <small class="text-muted">Revenue</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <h6 class="mb-0 fw-bold">Rp {{ number_format($plSummary['revenue']['budget'] ?? 0, 0, ',', '.') }}</h6>
                                <small class="text-success fw-medium">Real: Rp {{ number_format($plSummary['revenue']['realization'] ?? 0, 0, ',', '.') }}</small>
                            </div>
                        </div>
 
                        <!-- Row 2: Beban Langsung -->
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-label-info p-2 rounded me-3">
                                    <i class="bx bx-receipt fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-semibold">Beban Langsung</h6>
                                    <small class="text-muted">Direct Cost</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <h6 class="mb-0 fw-bold">Rp {{ number_format($plSummary['direct_cost']['budget'] ?? 0, 0, ',', '.') }}</h6>
                                <small class="text-info fw-medium">Real: Rp {{ number_format($plSummary['direct_cost']['realization'] ?? 0, 0, ',', '.') }}</small>
                            </div>
                        </div>
 
                        <!-- Row 3: Laba Kotor -->
                        <div class="d-flex align-items-center justify-content-between bg-lighter p-2 rounded">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-label-primary p-2 rounded me-3">
                                    <i class="bx bx-calculator fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-primary">Laba Kotor</h6>
                                    <small class="text-muted">Gross Profit</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <h6 class="mb-0 fw-bold text-primary">Rp {{ number_format($plSummary['gross_profit']['budget'] ?? 0, 0, ',', '.') }}</h6>
                                <small class="text-primary fw-medium">Real: Rp {{ number_format($plSummary['gross_profit']['realization'] ?? 0, 0, ',', '.') }}</small>
                            </div>
                        </div>
 
                        <!-- Row 4: Beban Tidak Langsung -->
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-label-warning p-2 rounded me-3">
                                    <i class="bx bx-credit-card fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-semibold">Beban Td. Langsung</h6>
                                    <small class="text-muted">Indirect Cost</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <h6 class="mb-0 fw-bold">Rp {{ number_format($plSummary['indirect_cost']['budget'] ?? 0, 0, ',', '.') }}</h6>
                                <small class="text-warning fw-medium">Real: Rp {{ number_format($plSummary['indirect_cost']['realization'] ?? 0, 0, ',', '.') }}</small>
                            </div>
                        </div>
 
                        <!-- Row 5: Laba Usaha -->
                        <div class="d-flex align-items-center justify-content-between bg-lighter p-2 rounded">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-label-info p-2 rounded me-3" style="background-color: rgba(3, 195, 236, 0.16) !important; color: #03c3ec !important;">
                                    <i class="bx bx-line-chart fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-info" style="color: #03c3ec !important;">Laba Usaha (EBITDA)</h6>
                                    <small class="text-muted">Operating Profit</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <h6 class="mb-0 fw-bold text-info" style="color: #03c3ec !important;">Rp {{ number_format($plSummary['operating_profit']['budget'] ?? 0, 0, ',', '.') }}</h6>
                                <small class="text-info fw-medium" style="color: #03c3ec !important;">Real: Rp {{ number_format($plSummary['operating_profit']['realization'] ?? 0, 0, ',', '.') }}</small>
                            </div>
                        </div>
 
                        <!-- Row 6: Laba Bersih -->
                        <div class="d-flex align-items-center justify-content-between bg-label-success p-3 rounded">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-success text-white p-2 rounded me-3">
                                    <i class="bx bx-money fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-success">Laba Bersih</h6>
                                    <small class="text-success opacity-75">Net Profit</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <h5 class="mb-0 fw-bold text-success">Rp {{ number_format($plSummary['net_profit']['budget'] ?? 0, 0, ',', '.') }}</h5>
                                <small class="text-success fw-medium">Real: Rp {{ number_format($plSummary['net_profit']['realization'] ?? 0, 0, ',', '.') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    @if(!auth()->user()->isKepalaDepartemen())
    {{-- Row 3: Profit and Loss Summary --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="card-title mb-0">Laporan Laba Rugi (Profit & Loss Summary)</h5>
                        <small class="text-muted">Akumulasi anggaran, realisasi, dan proyeksi berdasarkan pemetaan kategori P&L</small>
                    </div>
                </div>
                <div class="table-responsive text-nowrap">
                    <table class="table table-hover table-striped-columns mb-0 align-middle">
                        <thead>
                            <tr class="table-light">
                                <th>Kategori / Golongan</th>
                                <th class="text-end">Anggaran (Budget)</th>
                                <th class="text-end">Realisasi YTD</th>
                                <th class="text-center">% Realisasi</th>
                                <th class="text-end">Proyeksi (Outlook)</th>
                                <th class="text-center">% Proyeksi</th>
                                <th class="text-end">Selisih (Variance)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($plGroups as $groupKey => $group)
                                <!-- Group Header (Bold, Uppercase) -->
                                <tr class="table-light fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                                    <td colspan="7">
                                        <i class="bx bx-folder me-2 text-primary"></i>{{ $group['label'] }}
                                    </td>
                                </tr>
                                
                                <!-- Group Items -->
                                @foreach ($group['items'] as $item)
                                    @php
                                        $itemBudget = $item['budget'];
                                        $itemReal = $item['realization'];
                                        $itemProj = $item['projection'];
                                        $itemRealPct = $itemBudget > 0 ? ($itemReal / $itemBudget) * 100 : 0;
                                        $itemProjPct = $itemBudget > 0 ? ($itemProj / $itemBudget) * 100 : 0;
                                        
                                        $itemVariance = $groupKey === 'Revenue' ? ($itemProj - $itemBudget) : ($itemBudget - $itemProj);
                                    @endphp
                                    <tr>
                                        <td class="ps-4">
                                            <i class="bx bxs-circle text-{{ $item['color'] }} me-2" style="font-size: 8px; vertical-align: middle;"></i>
                                            {{ $item['label'] }}
                                        </td>
                                        <td class="text-end font-monospace">Rp {{ number_format($itemBudget, 0, ',', '.') }}</td>
                                        <td class="text-end font-monospace">Rp {{ number_format($itemReal, 0, ',', '.') }}</td>
                                        <td class="text-center font-monospace text-muted">
                                            @if($itemBudget > 0)
                                                {{ number_format($itemRealPct, 1, ',', '.') }}%
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end font-monospace">Rp {{ number_format($itemProj, 0, ',', '.') }}</td>
                                        <td class="text-center font-monospace text-muted">
                                            @if($itemBudget > 0)
                                                {{ number_format($itemProjPct, 1, ',', '.') }}%
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end font-monospace">
                                            @if($itemVariance > 0)
                                                <span class="text-success"><i class="bx bx-chevron-up me-1"></i>Rp {{ number_format($itemVariance, 0, ',', '.') }}</span>
                                            @elseif($itemVariance < 0)
                                                <span class="text-danger"><i class="bx bx-chevron-down me-1"></i>(Rp {{ number_format(abs($itemVariance), 0, ',', '.') }})</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
 
                                <!-- Group Subtotal Row -->
                                @php
                                    $subBudget = $group['budget_subtotal'];
                                    $subReal = $group['realization_subtotal'];
                                    $subProj = $group['projection_subtotal'];
                                    $subRealPct = $subBudget > 0 ? ($subReal / $subBudget) * 100 : 0;
                                    $subProjPct = $subBudget > 0 ? ($subProj / $subBudget) * 100 : 0;
                                    
                                    $subVariance = $groupKey === 'Revenue' ? ($subProj - $subBudget) : ($subBudget - $subProj);
                                @endphp
                                <tr class="fw-semibold bg-lighter">
                                    <td class="ps-3 text-secondary">
                                        Subtotal {{ $group['label'] }}
                                    </td>
                                    <td class="text-end font-monospace">Rp {{ number_format($subBudget, 0, ',', '.') }}</td>
                                    <td class="text-end font-monospace text-success">Rp {{ number_format($subReal, 0, ',', '.') }}</td>
                                    <td class="text-center font-monospace text-muted">
                                        @if($subBudget > 0)
                                            {{ number_format($subRealPct, 1, ',', '.') }}%
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-end font-monospace text-warning">Rp {{ number_format($subProj, 0, ',', '.') }}</td>
                                    <td class="text-center font-monospace text-muted">
                                        @if($subBudget > 0)
                                            {{ number_format($subProjPct, 1, ',', '.') }}%
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-end font-monospace">
                                        @if($subVariance > 0)
                                            <span class="text-success fw-semibold"><i class="bx bx-chevron-up me-1"></i>Rp {{ number_format($subVariance, 0, ',', '.') }}</span>
                                        @elseif($subVariance < 0)
                                            <span class="text-danger fw-semibold"><i class="bx bx-chevron-down me-1"></i>(Rp {{ number_format(abs($subVariance), 0, ',', '.') }})</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
 
                                <!-- Insert intermediate P&L summary rows if applicable -->
                                @if ($groupKey === 'Direct Cost')
                                    @php
                                        $gp = $plSummary['gross_profit'];
                                        $gpBudget = $gp['budget'];
                                        $gpReal = $gp['realization'];
                                        $gpProj = $gp['projection'];
                                        $gpRealPct = $gpBudget > 0 ? ($gpReal / $gpBudget) * 100 : 0;
                                        $gpProjPct = $gpBudget > 0 ? ($gpProj / $gpBudget) * 100 : 0;
                                        $gpVariance = $gpProj - $gpBudget;
                                    @endphp
                                    <tr class="table-primary fw-bold">
                                        <td class="text-primary">
                                            <i class="bx bx-calculator me-2"></i>{{ $gp['label'] }}
                                        </td>
                                        <td class="text-end font-monospace">Rp {{ number_format($gpBudget, 0, ',', '.') }}</td>
                                        <td class="text-end font-monospace">Rp {{ number_format($gpReal, 0, ',', '.') }}</td>
                                        <td class="text-center font-monospace text-muted">
                                            @if($gpBudget > 0)
                                                {{ number_format($gpRealPct, 1, ',', '.') }}%
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end font-monospace">Rp {{ number_format($gpProj, 0, ',', '.') }}</td>
                                        <td class="text-center font-monospace text-muted">
                                            @if($gpBudget > 0)
                                                {{ number_format($gpProjPct, 1, ',', '.') }}%
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end font-monospace">
                                            @if($gpVariance > 0)
                                                <span class="text-success"><i class="bx bx-chevron-up me-1"></i>Rp {{ number_format($gpVariance, 0, ',', '.') }}</span>
                                            @elseif($gpVariance < 0)
                                                <span class="text-danger"><i class="bx bx-chevron-down me-1"></i>(Rp {{ number_format(abs($gpVariance), 0, ',', '.') }})</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @elseif ($groupKey === 'Indirect Cost')
                                    @php
                                        $op = $plSummary['operating_profit'];
                                        $opBudget = $op['budget'];
                                        $opReal = $op['realization'];
                                        $opProj = $op['projection'];
                                        $opRealPct = $opBudget > 0 ? ($opReal / $opBudget) * 100 : 0;
                                        $opProjPct = $opBudget > 0 ? ($opProj / $opBudget) * 100 : 0;
                                        $opVariance = $opProj - $opBudget;
                                    @endphp
                                    <tr class="table-info fw-bold">
                                        <td class="text-info" style="color: #03c3ec !important;">
                                            <i class="bx bx-trending-up me-2"></i>{{ $op['label'] }}
                                        </td>
                                        <td class="text-end font-monospace">Rp {{ number_format($opBudget, 0, ',', '.') }}</td>
                                        <td class="text-end font-monospace">Rp {{ number_format($opReal, 0, ',', '.') }}</td>
                                        <td class="text-center font-monospace text-muted">
                                            @if($opBudget > 0)
                                                {{ number_format($opRealPct, 1, ',', '.') }}%
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end font-monospace">Rp {{ number_format($opProj, 0, ',', '.') }}</td>
                                        <td class="text-center font-monospace text-muted">
                                            @if($opBudget > 0)
                                                {{ number_format($opProjPct, 1, ',', '.') }}%
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end font-monospace">
                                            @if($opVariance > 0)
                                                <span class="text-success"><i class="bx bx-chevron-up me-1"></i>Rp {{ number_format($opVariance, 0, ',', '.') }}</span>
                                            @elseif($opVariance < 0)
                                                <span class="text-danger"><i class="bx bx-chevron-down me-1"></i>(Rp {{ number_format(abs($opVariance), 0, ',', '.') }})</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
 
                            <!-- Show unmapped if present -->
                            @if ($unmappedGroup)
                                <tr class="table-light fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                                    <td colspan="7">
                                        <i class="bx bx-question-mark me-2 text-secondary"></i>{{ $unmappedGroup['label'] }}
                                    </td>
                                </tr>
                                @foreach ($unmappedGroup['items'] as $item)
                                    @php
                                        $itemBudget = $item['budget'];
                                        $itemReal = $item['realization'];
                                        $itemProj = $item['projection'];
                                        $itemRealPct = $itemBudget > 0 ? ($itemReal / $itemBudget) * 100 : 0;
                                        $itemProjPct = $itemBudget > 0 ? ($itemProj / $itemBudget) * 100 : 0;
                                        $itemVariance = $itemBudget - $itemProj;
                                    @endphp
                                    <tr>
                                        <td class="ps-4">
                                            <i class="bx bxs-circle text-{{ $item['color'] }} me-2" style="font-size: 8px; vertical-align: middle;"></i>
                                            {{ $item['label'] }}
                                        </td>
                                        <td class="text-end font-monospace">Rp {{ number_format($itemBudget, 0, ',', '.') }}</td>
                                        <td class="text-end font-monospace">Rp {{ number_format($itemReal, 0, ',', '.') }}</td>
                                        <td class="text-center font-monospace text-muted">
                                            @if($itemBudget > 0)
                                                {{ number_format($itemRealPct, 1, ',', '.') }}%
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end font-monospace">Rp {{ number_format($itemProj, 0, ',', '.') }}</td>
                                        <td class="text-center font-monospace text-muted">
                                            @if($itemBudget > 0)
                                                {{ number_format($itemProjPct, 1, ',', '.') }}%
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end font-monospace">
                                            @if($itemVariance > 0)
                                                <span class="text-success"><i class="bx bx-chevron-up me-1"></i>Rp {{ number_format($itemVariance, 0, ',', '.') }}</span>
                                            @elseif($itemVariance < 0)
                                                <span class="text-danger"><i class="bx bx-chevron-down me-1"></i>(Rp {{ number_format(abs($itemVariance), 0, ',', '.') }})</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
 
                            <!-- Final Net Profit Summary Row -->
                            @php
                                $np = $plSummary['net_profit'];
                                $npBudget = $np['budget'];
                                $npReal = $np['realization'];
                                $npProj = $np['projection'];
                                $npRealPct = $npBudget > 0 ? ($npReal / $npBudget) * 100 : 0;
                                $npProjPct = $npBudget > 0 ? ($npProj / $npBudget) * 100 : 0;
                                $npVariance = $npProj - $npBudget;
                            @endphp
                            <tr class="table-success fw-bold border-top border-2">
                                <td class="text-success" style="font-size: 1.1rem;">
                                    <i class="bx bx-money me-2"></i>{{ $np['label'] }}
                                </td>
                                <td class="text-end font-monospace" style="font-size: 1.1rem;">Rp {{ number_format($npBudget, 0, ',', '.') }}</td>
                                <td class="text-end font-monospace" style="font-size: 1.1rem;">Rp {{ number_format($npReal, 0, ',', '.') }}</td>
                                <td class="text-center font-monospace text-muted" style="font-size: 1.1rem;">
                                    @if($npBudget > 0)
                                        {{ number_format($npRealPct, 1, ',', '.') }}%
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-end font-monospace" style="font-size: 1.1rem;">Rp {{ number_format($npProj, 0, ',', '.') }}</td>
                                <td class="text-center font-monospace text-muted" style="font-size: 1.1rem;">
                                    @if($npBudget > 0)
                                        {{ number_format($npProjPct, 1, ',', '.') }}%
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-end font-monospace" style="font-size: 1.1rem;">
                                    @if($npVariance > 0)
                                        <span class="text-success"><i class="bx bx-chevron-up me-1"></i>Rp {{ number_format($npVariance, 0, ',', '.') }}</span>
                                    @elseif($npVariance < 0)
                                        <span class="text-danger"><i class="bx bx-chevron-down me-1"></i>(Rp {{ number_format(abs($npVariance), 0, ',', '.') }})</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
    @endif

    @else
    <div class="card py-5 text-center">
        <div class="card-body">
            <i class="bx bx-error-circle bx-lg text-warning mb-3"></i>
            <h5>Belum Ada Data RKAP Aktif</h5>
            <p class="text-muted">Sistem tidak menemukan periode RKAP yang aktif untuk divisualisasikan.</p>
        </div>
    </div>
@endif
@endsection

@section('page-script')
@if($activePeriod)
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Burn-Up Chart Setup
    const burnUpChartOptions = {
        series: [
            {
                name: 'Pagu Anggaran',
                type: 'line',
                data: @json($cumulativeBudget)
            },
            {
                name: 'Realisasi Kumulatif',
                type: 'area',
                data: @json($cumulativeRealization)
            },
            {
                name: 'Proyeksi Kumulatif',
                type: 'line',
                data: @json($cumulativeProjection)
            }
        ],
        chart: {
            height: 330,
            type: 'line',
            toolbar: { show: false }
        },
        stroke: {
            width: [2, 3, 2],
            curve: 'smooth',
            dashArray: [0, 0, 5]
        },
        colors: ['#8592a3', '#71dd37', '#ffab00'],
        fill: {
            type: ['solid', 'gradient', 'solid'],
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.3,
                opacityTo: 0.05,
                stops: [0, 90, 100]
            }
        },
        xaxis: {
            categories: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'],
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            labels: {
                formatter: function (value) {
                    if (value >= 1e9) return 'Rp ' + (value / 1e9).toFixed(1) + ' M';
                    if (value >= 1e6) return 'Rp ' + (value / 1e6).toFixed(0) + ' Jt';
                    return 'Rp ' + value.toLocaleString('id-ID');
                }
            }
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return 'Rp ' + val.toLocaleString('id-ID');
                }
            }
        },
        legend: {
            position: 'top',
            horizontalAlign: 'left'
        }
    };
    const burnUpChart = new ApexCharts(document.querySelector("#burnUpChart"), burnUpChartOptions);
    burnUpChart.render();

    // 2. Radial Gauge ring Chart Setup
    const utilizationGaugeOptions = {
        series: [@json($stats['absorption_rate']), @json($stats['outlook_rate'])],
        chart: {
            height: 300,
            type: 'radialBar'
        },
        plotOptions: {
            radialBar: {
                offsetY: 0,
                startAngle: -135,
                endAngle: 135,
                hollow: {
                    margin: 5,
                    size: '50%',
                    background: 'transparent'
                },
                dataLabels: {
                    name: {
                        fontSize: '13px',
                        color: '#566a7f',
                        offsetY: 10
                    },
                    value: {
                        offsetY: -25,
                        fontSize: '20px',
                        color: '#566a7f',
                        formatter: function (val) {
                            return val + '%';
                        }
                    },
                    total: {
                        show: true,
                        label: 'Realisasi YTD',
                        formatter: function (w) {
                            return @json($stats['absorption_rate']) + '%';
                        }
                    }
                }
            }
        },
        colors: ['#71dd37', '#ffab00'],
        labels: ['Penyerapan YTD', 'Outlook Akhir Tahun'],
        legend: {
            show: true,
            position: 'bottom',
            labels: { useSeriesColors: true }
        }
    };
    const utilizationGauge = new ApexCharts(document.querySelector("#utilizationGauge"), utilizationGaugeOptions);
    utilizationGauge.render();

    // 3. Division Absorption Bar Chart Setup
    const directorateData = @json($directorateData);
    const departmentData = @json($departmentData);
    const bureauData = @json($bureauData);

    // Sort department and bureau data by budget descending so biggest budgets appear first
    departmentData.sort((a, b) => parseFloat(b.budget || 0) - parseFloat(a.budget || 0));
    if (bureauData) {
        bureauData.sort((a, b) => parseFloat(b.budget || 0) - parseFloat(a.budget || 0));
    }

    // Helper to format large numbers for data labels
    function formatValueShort(value) {
        if (value >= 1e12) return (value / 1e12).toFixed(1) + ' T';
        if (value >= 1e9) return (value / 1e9).toFixed(1) + ' M';
        if (value >= 1e6) return (value / 1e6).toFixed(0) + ' Jt';
        if (value === 0) return '';
        return value.toLocaleString('id-ID');
    }

    // Calculate dynamic height: 60px per item, min 350px
    function calcChartHeight(itemCount) {
        return Math.max(350, itemCount * 60);
    }

    // Build chart options for a given dataset
    function buildDivisionChartOptions(data) {
        const labels = data.map(item => item.label);
        const budgets = data.map(item => parseFloat(item.budget || 0));
        const realizations = data.map(item => parseFloat(item.realization || 0));
        const projections = data.map(item => parseFloat(item.projection || 0));

        return {
            series: [
                { name: 'Anggaran', data: budgets },
                { name: 'Realisasi YTD', data: realizations },
                { name: 'Proyeksi Akhir Tahun', data: projections }
            ],
            chart: {
                type: 'bar',
                height: calcChartHeight(data.length),
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '70%',
                    borderRadius: 3,
                    dataLabels: { position: 'top' }
                }
            },
            dataLabels: {
                enabled: true,
                offsetX: 30,
                style: {
                    fontSize: '10px',
                    colors: ['#566a7f']
                },
                formatter: function(val) {
                    return formatValueShort(val);
                }
            },
            colors: ['#1a1f5e', '#ED1C24', '#ffab00'],
            xaxis: {
                categories: labels,
                labels: {
                    formatter: function (value) {
                        if (value >= 1e12) return (value / 1e12).toFixed(1) + ' T';
                        if (value >= 1e9) return (value / 1e9).toFixed(1) + ' M';
                        if (value >= 1e6) return (value / 1e6).toFixed(0) + ' Jt';
                        return value.toLocaleString('id-ID');
                    }
                }
            },
            yaxis: {
                labels: {
                    maxWidth: 200,
                    style: {
                        fontSize: '11px'
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return 'Rp ' + val.toLocaleString('id-ID');
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'left'
            }
        };
    }

    // Create initial chart with appropriate data based on role
    const isKadept = @json(auth()->user()->isKepalaDepartemen());
    let divisionChart = new ApexCharts(
        document.querySelector("#divisionChart"),
        buildDivisionChartOptions(isKadept ? departmentData : directorateData)
    );
    divisionChart.render();

    // Destroy and recreate chart to avoid ApexCharts horizontal bar update bugs
    function switchDivisionChart(type) {
        let data;
        if (type === 'directorate') {
            data = directorateData;
        } else if (type === 'department') {
            data = departmentData;
        } else if (type === 'bureau') {
            data = bureauData;
        }
        divisionChart.destroy();
        divisionChart = new ApexCharts(
            document.querySelector("#divisionChart"),
            buildDivisionChartOptions(data)
        );
        divisionChart.render();
    }

    if (isKadept) {
        document.getElementById('groupDepartment').addEventListener('change', function() {
            if(this.checked) switchDivisionChart('department');
        });
        document.getElementById('groupBureau').addEventListener('change', function() {
            if(this.checked) switchDivisionChart('bureau');
        });
    } else {
        document.getElementById('groupDirectorate').addEventListener('change', function() {
            if(this.checked) switchDivisionChart('directorate');
        });
        document.getElementById('groupDepartment').addEventListener('change', function() {
            if(this.checked) switchDivisionChart('department');
        });
    }

    // 4. Annual Comparison Chart Setup
    const annualComparisonChartOptions = {
        series: [
            {
                name: 'Anggaran',
                data: @json(array_column($comparisonData, 'budget'))
            },
            {
                name: 'Realisasi YTD',
                data: @json(array_column($comparisonData, 'realization'))
            },
            {
                name: 'Proyeksi Akhir Tahun',
                data: @json(array_column($comparisonData, 'projection'))
            }
        ],
        chart: {
            type: 'bar',
            height: 350,
            toolbar: { show: false }
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                endingShape: 'rounded',
                borderRadius: 4
            },
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        colors: ['#1a1f5e', '#ED1C24', '#ffab00'],
        xaxis: {
            categories: @json(array_column($comparisonData, 'label')),
        },
        yaxis: {
            labels: {
                formatter: function (value) {
                    if (value >= 1e9) return 'Rp ' + (value / 1e9).toFixed(1) + ' M';
                    if (value >= 1e6) return 'Rp ' + (value / 1e6).toFixed(0) + ' Jt';
                    return 'Rp ' + value.toLocaleString('id-ID');
                }
            }
        },
        fill: {
            opacity: 1
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return 'Rp ' + val.toLocaleString('id-ID');
                }
            }
        },
        legend: {
            position: 'top',
            horizontalAlign: 'left'
        }
    };
    const annualComparisonChart = new ApexCharts(document.querySelector("#annualComparisonChart"), annualComparisonChartOptions);
    annualComparisonChart.render();

    // Note: COA Expense Category Allocation donut chart replaced by Profit & Loss Summary card widget.
});
</script>
@endif
@endsection
