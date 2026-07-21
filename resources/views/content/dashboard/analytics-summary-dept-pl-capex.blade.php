@extends('layouts/contentNavbarLayout')

@section('title', __('Laporan Laba Rugi & Capex per Departemen'))

@section('page-style')
<style>
  /* Freeze pane style for P&L & Capex Department Summary table first column */
  #summaryDeptPlCapexTable thead tr:first-child th:first-child,
  #summaryDeptPlCapexTable tbody td:first-child,
  #summaryDeptPlCapexTable tfoot td:first-child {
    position: sticky;
    left: 0;
    z-index: 2;
    border-right: 2px solid #d9dee3 !important;
  }

  #summaryDeptPlCapexTable thead tr:first-child th:first-child {
    z-index: 4;
    background-color: #f5f5f9 !important;
  }

  #summaryDeptPlCapexTable tbody tr.zebra-even td:first-child {
    background-color: #f9fafb !important;
  }

  #summaryDeptPlCapexTable tbody tr.zebra-odd td:first-child {
    background-color: #ffffff !important;
  }

  #summaryDeptPlCapexTable tbody tr:hover td:first-child {
    background-color: #f5f5f9 !important;
  }

  #summaryDeptPlCapexTable tbody tr.bg-label-primary td:first-child,
  #summaryDeptPlCapexTable tbody tr.bg-label-info td:first-child {
    z-index: 3;
  }
</style>
@endsection

@section('content')
<div class="py-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
  <div>
    <h4 class="mb-1"><span class="text-muted fw-light">{{ __('Analytics') }} / {{ __('Summary Department') }} /</span> {{ __('Laba Rugi & Capex') }}</h4>
    @if ($activePeriod)
    <p class="text-muted mb-0">
      {{ __('Menampilkan Laporan Laba Rugi dan Belanja Modal (Capex) per Departemen untuk periode:') }} 
      <strong>{{ $activePeriod->title }}</strong>
    </p>
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
      <form action="{{ route('analytics-summary-dept-pl-capex') }}" method="GET" id="filterForm" class="m-0 d-flex gap-2">
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
<div class="row">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
        <div>
          <h5 class="card-title mb-0">{{ __('Matriks Laba Rugi & Capex per Departemen') }}</h5>
          <small class="text-muted">{{ __('Perbandingan alokasi anggaran (B), realisasi (R), dan proyeksi (P) operasional serta belanja modal') }}</small>
        </div>
      </div>
      <div class="table-responsive text-nowrap" style="max-height: 700px;">
        <table class="table table-bordered table-hover align-middle mb-0" id="summaryDeptPlCapexTable">
          <thead class="table-light sticky-top bg-light">
            <tr>
              <th rowspan="2" class="align-middle bg-light text-center rkap-mw-250">{{ __('GOLONGAN REPORT GROUP') }}</th>
              @foreach ($departments as $dept)
              <th colspan="3" class="text-center border-bottom text-uppercase fw-bold bg-label-secondary">
                {{ $dept->code }}<br>
                <small class="text-muted text-none fw-normal" style="font-size: 0.75rem;">{{ Str::limit($dept->name, 25) }}</small>
              </th>
              @endforeach
            </tr>
            <tr class="small text-center fw-bold">
              @foreach ($departments as $dept)
              <th class="text-end px-2 text-primary" style="min-width: 100px;">{{ __('B') }}</th>
              <th class="text-end px-2 text-success" style="min-width: 100px;">{{ __('R') }}</th>
              <th class="text-end px-2 text-info" style="min-width: 100px;">{{ __('P') }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            <!-- P&L Header Row -->
            <tr class="bg-label-primary">
              <td colspan="{{ count($departments) * 3 + 1 }}" class="fw-bold text-uppercase py-2 text-primary">
                <i class="bx bx-file me-1"></i> {{ __('LABA RUGI (OPERASIONAL)') }}
              </td>
            </tr>

            <!-- P&L Data Rows -->
            @php $rowIdx = 0; @endphp
            @forelse ($plReportGroups as $group)
            <tr class="{{ $rowIdx++ % 2 === 0 ? 'zebra-even' : 'zebra-odd' }}">
              <td class="fw-semibold text-dark ps-3">{{ $group->code }} - {{ $group->name }}</td>
              @foreach ($departments as $dept)
              @php
                $cell = $matrix[$group->id][$dept->id] ?? ['budget' => 0, 'realization' => 0, 'projection' => 0];
              @endphp
              <td class="text-end small px-2">{{ $cell['budget'] != 0 ? number_format($cell['budget'], 0, ',', '.') : '-' }}</td>
              <td class="text-end small px-2 text-success">{{ $cell['realization'] != 0 ? number_format($cell['realization'], 0, ',', '.') : '-' }}</td>
              <td class="text-end small px-2 text-info">{{ $cell['projection'] != 0 ? number_format($cell['projection'], 0, ',', '.') : '-' }}</td>
              @endforeach
            </tr>
            @empty
            <tr>
              <td colspan="{{ count($departments) * 3 + 1 }}" class="text-center py-3 text-muted">{{ __('Tidak ada data report group P&L.') }}</td>
            </tr>
            @endforelse

            <!-- P&L Total Row -->
            <tr class="table-light fw-bold border-bottom-2">
              <td class="text-uppercase text-dark ps-3"><i class="bx bx-calculator me-1"></i> {{ __('TOTAL LABA RUGI') }}</td>
              @foreach ($departments as $dept)
              @php
                $dTot = $deptTotals[$dept->id] ?? ['budget' => 0, 'realization' => 0, 'projection' => 0];
              @endphp
              <td class="text-end px-2 text-primary">{{ number_format($dTot['budget'], 0, ',', '.') }}</td>
              <td class="text-end px-2 text-success">{{ number_format($dTot['realization'], 0, ',', '.') }}</td>
              <td class="text-end px-2 text-info">{{ number_format($dTot['projection'], 0, ',', '.') }}</td>
              @endforeach
            </tr>

            <!-- Separator Empty Row -->
            <tr style="height: 15px; border: none;">
              <td colspan="{{ count($departments) * 3 + 1 }}" style="background-color: #f5f5f9; border: none; height: 15px;"></td>
            </tr>

            <!-- Capex Header Row -->
            <tr class="bg-label-info">
              <td colspan="{{ count($departments) * 3 + 1 }}" class="fw-bold text-uppercase py-2 text-info">
                <i class="bx bx-wallet me-1"></i> {{ __('BELANJA MODAL (CAPEX)') }}
              </td>
            </tr>

            <!-- Capex Data Row -->
            <tr class="zebra-odd">
              <td class="fw-semibold text-dark ps-3">{{ __('Total Belanja Modal (Capex)') }}</td>
              @foreach ($departments as $dept)
              @php
                $cell = $capexMatrix[$dept->id] ?? ['budget' => 0, 'realization' => 0, 'projection' => 0];
              @endphp
              <td class="text-end small px-2 font-semibold text-primary">{{ $cell['budget'] != 0 ? number_format($cell['budget'], 0, ',', '.') : '-' }}</td>
              <td class="text-end small px-2 text-success font-semibold">{{ $cell['realization'] != 0 ? number_format($cell['realization'], 0, ',', '.') : '-' }}</td>
              <td class="text-end small px-2 text-info font-semibold">{{ $cell['projection'] != 0 ? number_format($cell['projection'], 0, ',', '.') : '-' }}</td>
              @endforeach
            </tr>

            <!-- Capex Grand Total / Consolidation summary -->
            <tr class="table-light fw-bold">
              <td class="text-uppercase text-dark ps-3"><i class="bx bx-check-double me-1"></i> {{ __('TOTAL KONSOLIDASI CAPEX') }}</td>
              @foreach ($departments as $dept)
              @php
                $cell = $capexMatrix[$dept->id] ?? ['budget' => 0, 'realization' => 0, 'projection' => 0];
              @endphp
              <td class="text-end px-2 text-primary">{{ number_format($cell['budget'], 0, ',', '.') }}</td>
              <td class="text-end px-2 text-success">{{ number_format($cell['realization'], 0, ',', '.') }}</td>
              <td class="text-end px-2 text-info">{{ number_format($cell['projection'], 0, ',', '.') }}</td>
              @endforeach
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endif
@endsection
