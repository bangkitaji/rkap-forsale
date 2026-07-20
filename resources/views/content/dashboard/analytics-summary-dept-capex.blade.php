@extends('layouts/contentNavbarLayout')

@section('title', __('Laporan Capex per Departemen'))

@section('content')
<div class="py-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
  <div>
    <h4 class="mb-1"><span class="text-muted fw-light">{{ __('Analytics') }} / {{ __('Summary Department') }} /</span> {{ __('Capex') }}</h4>
    @if ($activePeriod)
    <p class="text-muted mb-0">{{ __('Menampilkan Laporan Belanja Modal (Capex) per Departemen untuk periode:') }} <strong>{{ $activePeriod->title }}</strong></p>
    @else
    <div class="alert alert-warning mt-2 mb-0 py-2">
      <i class="bx bx-info-circle me-1"></i> {{ __('Belum ada periode RKAP yang aktif.') }}
    </div>
    @endif
  </div>

  <div class="d-flex align-items-center gap-3 flex-wrap">
    <!-- Filter Direktorat -->
    <div class="d-flex align-items-center gap-2 bg-white px-3 py-2 rounded shadow-sm border">
      <label for="directorateSelect" class="text-muted fw-semibold mb-0 text-nowrap d-flex align-items-center gap-1 rkap-font-09">
        <i class="bx bx-building text-primary fs-4"></i>
        <span>{{ __('Direktorat:') }}</span>
      </label>
      <form action="{{ route('analytics-summary-dept-capex') }}" method="GET" id="filterForm" class="m-0 d-flex gap-2">
        <input type="hidden" name="period_id" value="{{ $activePeriod?->id }}">
        <select name="directorate_id" id="directorateSelect"
          class="form-select form-select-sm border-0 fw-semibold text-primary cursor-pointer focus-ring-none rkap-font-09"
          onchange="this.form.submit()">
          <option value="">-- {{ __('Semua Departemen') }} --</option>
          @foreach ($directorates as $dir)
          <option value="{{ $dir->id }}" {{ $selectedDirectorateId == $dir->id ? 'selected' : '' }}>
            {{ $dir->code }} - {{ $dir->name }}
          </option>
          @endforeach
        </select>
      </form>
    </div>

    <!-- Filter Periode RKAP -->
    @if ($finalizedPeriods->isNotEmpty())
    <div class="d-flex align-items-center gap-2 bg-white px-3 py-2 rounded shadow-sm border">
      <label for="periodSelect" class="text-muted fw-semibold mb-0 text-nowrap d-flex align-items-center gap-1 rkap-font-09">
        <i class="bx bx-calendar text-primary fs-4"></i>
        <span>{{ __('Periode:') }}</span>
      </label>
      <select name="period_id" id="periodSelect" form="filterForm"
        class="form-select form-select-sm border-0 fw-semibold text-primary cursor-pointer focus-ring-none rkap-font-09"
        onchange="document.getElementById('filterForm').submit()">
        @foreach ($finalizedPeriods as $p)
        <option value="{{ $p->id }}" {{ $activePeriod && $activePeriod->id == $p->id ? 'selected' : '' }}>
          {{ $p->title }} ({{ $p->year }})
        </option>
        @endforeach
      </select>
    </div>
    @endif
  </div>
</div>

@if ($activePeriod)
<!-- KPI Summary Cards -->
<div class="row g-4 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 shadow-none border">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold text-muted">{{ __('Total Budget Capex') }}</span>
          <span class="badge bg-label-primary rounded p-2"><i class="bx bx-wallet fs-4"></i></span>
        </div>
        <h4 class="mb-1 fw-bold">Rp {{ number_format($totalStats['total_budget'], 0, ',', '.') }}</h4>
        <p class="mb-0 text-muted small">{{ __('Seluruh Departemen') }}</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 shadow-none border">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold text-muted">{{ __('Total Realisasi Capex') }}</span>
          <span class="badge bg-label-success rounded p-2"><i class="bx bx-check-circle fs-4"></i></span>
        </div>
        <h4 class="mb-1 fw-bold text-success">Rp {{ number_format($totalStats['total_realization'], 0, ',', '.') }}</h4>
        <p class="mb-0 text-muted small">{{ __('YTD Realisasi Capex') }}</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 shadow-none border">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold text-muted">{{ __('Total Proyeksi Capex') }}</span>
          <span class="badge bg-label-info rounded p-2"><i class="bx bx-line-chart fs-4"></i></span>
        </div>
        <h4 class="mb-1 fw-bold text-info">Rp {{ number_format($totalStats['total_projection'], 0, ',', '.') }}</h4>
        <p class="mb-0 text-muted small">{{ __('Outlook Akhir Tahun') }}</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 shadow-none border">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold text-muted">{{ __('Tingkat Penyerapan') }}</span>
          <span class="badge bg-label-warning rounded p-2"><i class="bx bx-pie-chart-alt fs-4"></i></span>
        </div>
        <h4 class="mb-1 fw-bold text-warning">{{ $totalStats['absorption_rate'] }}%</h4>
        <p class="mb-0 text-muted small">{{ __('Realisasi vs Anggaran') }}</p>
      </div>
    </div>
  </div>
</div>

<!-- Table Card -->
<div class="row">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header border-bottom py-3">
        <h5 class="card-title mb-0">{{ __('Ringkasan Capex per Departemen') }}</h5>
        <small class="text-muted">{{ __('Perbandingan alokasi anggaran dan penyerapan belanja modal tiap departemen') }}</small>
      </div>
      <div class="table-responsive text-nowrap">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="rkap-mw-250">{{ __('DEPARTEMEN') }}</th>
              <th class="rkap-mw-180">{{ __('DIREKTORAT') }}</th>
              <th class="text-end rkap-mw-150">{{ __('ANGGARAN (B)') }}</th>
              <th class="text-end rkap-mw-150">{{ __('REALISASI (R)') }}</th>
              <th class="text-end rkap-mw-150">{{ __('PROYEKSI (P)') }}</th>
              <th class="text-end rkap-mw-150">{{ __('VARIANCE (B - P)') }}</th>
              <th class="text-center rkap-mw-120">{{ __('PENYERAPAN') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($deptCapexData as $row)
            <tr>
              <td>
                <div class="fw-bold text-dark">{{ $row['department']->code }}</div>
                <small class="text-muted">{{ $row['department']->name }}</small>
              </td>
              <td>
                <span class="badge bg-label-secondary">{{ $row['department']->directorate?->code ?? '-' }}</span>
              </td>
              <td class="text-end fw-semibold">{{ number_format($row['budget'], 0, ',', '.') }}</td>
              <td class="text-end text-success fw-semibold">{{ number_format($row['realization'], 0, ',', '.') }}</td>
              <td class="text-end text-info fw-semibold">{{ number_format($row['projection'], 0, ',', '.') }}</td>
              <td class="text-end {{ $row['variance'] < 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($row['variance'], 0, ',', '.') }}</td>
              <td class="text-center">
                <div class="d-flex align-items-center justify-content-center gap-2">
                  <div class="progress w-100" style="height: 6px; max-width: 60px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ min($row['absorption_rate'], 100) }}%"></div>
                  </div>
                  <span class="small fw-semibold text-muted">{{ $row['absorption_rate'] }}%</span>
                </div>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">{{ __('Tidak ada data capex departemen.') }}</td>
            </tr>
            @endforelse
          </tbody>
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="2" class="text-uppercase text-dark">{{ __('TOTAL KONSOLIDASI') }}</td>
              <td class="text-end text-primary">{{ number_format($totalStats['total_budget'], 0, ',', '.') }}</td>
              <td class="text-end text-success">{{ number_format($totalStats['total_realization'], 0, ',', '.') }}</td>
              <td class="text-end text-info">{{ number_format($totalStats['total_projection'], 0, ',', '.') }}</td>
              <td class="text-end text-muted">{{ number_format($totalStats['total_budget'] - $totalStats['total_projection'], 0, ',', '.') }}</td>
              <td class="text-center text-warning">{{ $totalStats['absorption_rate'] }}%</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>
@endif
@endsection
