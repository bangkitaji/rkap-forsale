@extends('layouts/contentNavbarLayout')

@section('title', __('Laporan Cash Flow per Departemen'))

@section('content')
<div class="py-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
  <div>
    <h4 class="mb-1"><span class="text-muted fw-light">{{ __('Analytics') }} / {{ __('Summary Department') }} /</span> {{ __('Cash Flow') }}</h4>
    @if ($activePeriod)
    <p class="text-muted mb-0">{{ __('Menampilkan Laporan Arus Kas per Departemen untuk periode:') }} <strong>{{ $activePeriod->title }}</strong></p>
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
      <form action="{{ route('analytics-summary-dept-cashflow') }}" method="GET" id="filterForm" class="m-0 d-flex gap-2">
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
          <h5 class="card-title mb-0">{{ __('Matriks Cash Flow per Departemen (Side-by-Side)') }}</h5>
          <small class="text-muted">{{ __('Rincian Arus Masuk (Inflow) dan Arus Keluar (Outflow) per Departemen') }}</small>
        </div>
      </div>
      <div class="table-responsive text-nowrap" style="max-height: 650px;">
        <table class="table table-bordered table-hover align-middle mb-0">
          <thead class="table-light sticky-top bg-light">
            <tr>
              <th rowspan="2" class="align-middle bg-light text-center rkap-mw-250">{{ __('CASHFLOW GROUP') }}</th>
              @foreach ($departments as $dept)
              <th colspan="3" class="text-center border-bottom text-uppercase fw-bold bg-label-secondary">
                {{ $dept->code }}<br>
                <small class="text-muted text-none fw-normal" style="font-size: 0.75rem;">{{ Str::limit($dept->name, 25) }}</small>
              </th>
              @endforeach
              <th colspan="3" class="text-center border-bottom text-uppercase fw-bold bg-label-primary">{{ __('TOTAL KONSOLIDASI') }}</th>
            </tr>
            <tr class="small text-center fw-bold">
              @foreach ($departments as $dept)
              <th class="text-end px-2 text-primary" style="min-width: 100px;">{{ __('B') }}</th>
              <th class="text-end px-2 text-success" style="min-width: 100px;">{{ __('R') }}</th>
              <th class="text-end px-2 text-info" style="min-width: 100px;">{{ __('P') }}</th>
              @endforeach
              <th class="text-end px-2 text-primary bg-light" style="min-width: 110px;">{{ __('B') }}</th>
              <th class="text-end px-2 text-success bg-light" style="min-width: 110px;">{{ __('R') }}</th>
              <th class="text-end px-2 text-info bg-light" style="min-width: 110px;">{{ __('P') }}</th>
            </tr>
          </thead>
          <tbody>
            <!-- INFLOW SECTION -->
            <tr class="table-success fw-bold text-uppercase">
              <td colspan="{{ count($departments) * 3 + 4 }}"><i class="bx bx-trending-up me-1"></i> {{ __('ARUS KAS MASUK (INFLOW)') }}</td>
            </tr>
            @forelse ($inflowGroups as $group)
            <tr>
              <td class="ps-4 fw-semibold text-dark">{{ $group->code }} - {{ $group->name }}</td>
              @foreach ($departments as $dept)
              @php
                $cell = $matrix[$group->id][$dept->id] ?? ['budget' => 0, 'realization' => 0, 'projection' => 0];
              @endphp
              <td class="text-end small px-2">{{ $cell['budget'] != 0 ? number_format($cell['budget'], 0, ',', '.') : '-' }}</td>
              <td class="text-end small px-2 text-success">{{ $cell['realization'] != 0 ? number_format($cell['realization'], 0, ',', '.') : '-' }}</td>
              <td class="text-end small px-2 text-info">{{ $cell['projection'] != 0 ? number_format($cell['projection'], 0, ',', '.') : '-' }}</td>
              @endforeach
              @php
                $gTot = $groupTotals[$group->id] ?? ['budget' => 0, 'realization' => 0, 'projection' => 0];
              @endphp
              <td class="text-end fw-bold small px-2 text-primary bg-light">{{ $gTot['budget'] != 0 ? number_format($gTot['budget'], 0, ',', '.') : '-' }}</td>
              <td class="text-end fw-bold small px-2 text-success bg-light">{{ $gTot['realization'] != 0 ? number_format($gTot['realization'], 0, ',', '.') : '-' }}</td>
              <td class="text-end fw-bold small px-2 text-info bg-light">{{ $gTot['projection'] != 0 ? number_format($gTot['projection'], 0, ',', '.') : '-' }}</td>
            </tr>
            @empty
            <tr>
              <td colspan="{{ count($departments) * 3 + 4 }}" class="text-center py-2 text-muted small">{{ __('Tidak ada data inflow.') }}</td>
            </tr>
            @endforelse

            <!-- OUTFLOW SECTION -->
            <tr class="table-danger fw-bold text-uppercase">
              <td colspan="{{ count($departments) * 3 + 4 }}"><i class="bx bx-trending-down me-1"></i> {{ __('ARUS KAS KELUAR (OUTFLOW)') }}</td>
            </tr>
            @forelse ($outflowGroups as $group)
            <tr>
              <td class="ps-4 fw-semibold text-dark">{{ $group->code }} - {{ $group->name }}</td>
              @foreach ($departments as $dept)
              @php
                $cell = $matrix[$group->id][$dept->id] ?? ['budget' => 0, 'realization' => 0, 'projection' => 0];
              @endphp
              <td class="text-end small px-2">{{ $cell['budget'] != 0 ? number_format($cell['budget'], 0, ',', '.') : '-' }}</td>
              <td class="text-end small px-2 text-success">{{ $cell['realization'] != 0 ? number_format($cell['realization'], 0, ',', '.') : '-' }}</td>
              <td class="text-end small px-2 text-info">{{ $cell['projection'] != 0 ? number_format($cell['projection'], 0, ',', '.') : '-' }}</td>
              @endforeach
              @php
                $gTot = $groupTotals[$group->id] ?? ['budget' => 0, 'realization' => 0, 'projection' => 0];
              @endphp
              <td class="text-end fw-bold small px-2 text-primary bg-light">{{ $gTot['budget'] != 0 ? number_format($gTot['budget'], 0, ',', '.') : '-' }}</td>
              <td class="text-end fw-bold small px-2 text-success bg-light">{{ $gTot['realization'] != 0 ? number_format($gTot['realization'], 0, ',', '.') : '-' }}</td>
              <td class="text-end fw-bold small px-2 text-info bg-light">{{ $gTot['projection'] != 0 ? number_format($gTot['projection'], 0, ',', '.') : '-' }}</td>
            </tr>
            @empty
            <tr>
              <td colspan="{{ count($departments) * 3 + 4 }}" class="text-center py-2 text-muted small">{{ __('Tidak ada data outflow.') }}</td>
            </tr>
            @endforelse
          </tbody>
          <tfoot class="table-light fw-bold">
            <tr class="table-active border-top">
              <td class="text-uppercase text-dark">{{ __('NET CASH FLOW') }}</td>
              @php
                $grandNetB = 0; $grandNetR = 0; $grandNetP = 0;
              @endphp
              @foreach ($departments as $dept)
              @php
                $dTot = $deptTotals[$dept->id] ?? ['net_budget' => 0, 'net_realization' => 0, 'net_projection' => 0];
                $grandNetB += $dTot['net_budget'];
                $grandNetR += $dTot['net_realization'];
                $grandNetP += $dTot['net_projection'];
              @endphp
              <td class="text-end px-2 {{ $dTot['net_budget'] >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($dTot['net_budget'], 0, ',', '.') }}</td>
              <td class="text-end px-2 {{ $dTot['net_realization'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($dTot['net_realization'], 0, ',', '.') }}</td>
              <td class="text-end px-2 {{ $dTot['net_projection'] >= 0 ? 'text-info' : 'text-danger' }}">{{ number_format($dTot['net_projection'], 0, ',', '.') }}</td>
              @endforeach
              <td class="text-end px-2 {{ $grandNetB >= 0 ? 'text-primary' : 'text-danger' }} bg-light">{{ number_format($grandNetB, 0, ',', '.') }}</td>
              <td class="text-end px-2 {{ $grandNetR >= 0 ? 'text-success' : 'text-danger' }} bg-light">{{ number_format($grandNetR, 0, ',', '.') }}</td>
              <td class="text-end px-2 {{ $grandNetP >= 0 ? 'text-info' : 'text-danger' }} bg-light">{{ number_format($grandNetP, 0, ',', '.') }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>
@endif
@endsection
