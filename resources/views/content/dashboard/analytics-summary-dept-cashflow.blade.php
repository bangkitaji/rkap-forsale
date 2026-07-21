@extends('layouts/contentNavbarLayout')

@section('title', __('Laporan Cash Flow per Departemen'))

@section('content')
<div class="py-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
  <div>
    <h4 class="mb-1"><span class="text-muted fw-light">{{ __('Analytics') }} / {{ __('Summary Department') }} /</span> {{ __('Cash Flow') }}</h4>
    @if ($activePeriod)
    <p class="text-muted mb-0">{{ __('Periode:') }} <strong>{{ $activePeriod->title }}</strong></p>
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
        <input type="hidden" name="active_tab" value="{{ request('active_tab', 'matrix') }}">
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

{{-- Tab Navigation --}}
@php $activeTab = request('active_tab', 'matrix'); @endphp
<ul class="nav nav-pills flex-column flex-md-row mb-4">
  <li class="nav-item">
    <a class="nav-link {{ $activeTab === 'matrix' ? 'active' : '' }}"
       href="{{ route('analytics-summary-dept-cashflow', array_merge(request()->query(), ['active_tab' => 'matrix'])) }}">
      <i class="bx bx-table me-1"></i> {{ __('Matriks per Departemen') }}
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $activeTab === 'report' ? 'active' : '' }}"
       href="{{ route('analytics-summary-dept-cashflow', array_merge(request()->query(), ['active_tab' => 'report'])) }}">
      <i class="bx bx-file me-1"></i> {{ __('Laporan Arus Kas') }}
    </a>
  </li>
</ul>

{{-- ================================================================= --}}
{{-- TAB 1: MATRIKS PER DEPARTEMEN                                      --}}
{{-- ================================================================= --}}
@if ($activeTab === 'matrix')
<div class="row">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
        <div>
          <h5 class="card-title mb-0">{{ __('Matriks Cash Flow per Departemen (Side-by-Side)') }}</h5>
          <small class="text-muted">{{ __('Rincian Arus Masuk (CASH IN) dan Arus Keluar (CASH OUT) per Departemen') }}</small>
        </div>
      </div>
      <div class="table-responsive text-nowrap" style="max-height: 650px;">
        <table class="table table-bordered table-hover align-middle mb-0">
          <thead class="table-light sticky-top bg-light">
            <tr>
              <th rowspan="2" class="align-middle bg-light text-center rkap-mw-250">{{ __('CASHFLOW GROUP') }}</th>
              <th rowspan="2" class="align-middle bg-light text-center" style="min-width:80px;">{{ __('Tipe') }}</th>
              @foreach ($departments as $dept)
              <th colspan="3" class="text-center border-bottom text-uppercase fw-bold"
                  style="background-color: var(--bs-primary); color: #fff;">
                {{ $dept->code }}<br>
                <small class="fw-normal" style="font-size: 0.75rem; opacity:.85;">{{ Str::limit($dept->name, 25) }}</small>
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
            <!-- CASH IN SECTION -->
            <tr class="table-success fw-bold text-uppercase">
              <td colspan="{{ count($departments) * 3 + 5 }}"><i class="bx bx-trending-up me-1"></i> {{ __('ARUS KAS MASUK (CASH IN)') }}</td>
            </tr>
            @forelse ($inflowGroups as $group)
            <tr>
              <td class="ps-4 fw-semibold text-dark">{{ $group->code }} - {{ $group->name }}</td>
              <td class="text-center"><span class="badge bg-label-success small">IN</span></td>
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
              <td colspan="{{ count($departments) * 3 + 5 }}" class="text-center py-2 text-muted small">{{ __('Tidak ada data cash in.') }}</td>
            </tr>
            @endforelse

            <!-- CASH OUT SECTION -->
            <tr class="table-danger fw-bold text-uppercase">
              <td colspan="{{ count($departments) * 3 + 5 }}"><i class="bx bx-trending-down me-1"></i> {{ __('ARUS KAS KELUAR (CASH OUT)') }}</td>
            </tr>
            @forelse ($outflowGroups as $group)
            <tr>
              <td class="ps-4 fw-semibold text-dark">{{ $group->code }} - {{ $group->name }}</td>
              <td class="text-center"><span class="badge bg-label-danger small">OUT</span></td>
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
              <td colspan="{{ count($departments) * 3 + 5 }}" class="text-center py-2 text-muted small">{{ __('Tidak ada data cash out.') }}</td>
            </tr>
            @endforelse
          </tbody>
          <tfoot class="table-light fw-bold">
            <tr class="table-active border-top">
              <td colspan="2" class="text-uppercase text-dark">{{ __('NET CASH FLOW') }}</td>
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

{{-- ================================================================= --}}
{{-- TAB 2: LAPORAN ARUS KAS (VERTIKAL / FORMAL)                        --}}
{{-- ================================================================= --}}
@if ($activeTab === 'report')
@php
  $categoryColors = [
    'Arus Kas Aktivitas Operasi'   => ['bg' => '#0d6efd', 'badge' => 'bg-primary'],
    'Arus Kas Aktivitas Investasi' => ['bg' => '#fd7e14', 'badge' => 'bg-warning'],
    'Arus Kas Aktivitas Pendanaan' => ['bg' => '#198754', 'badge' => 'bg-success'],
  ];
@endphp
<div class="row">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header border-bottom py-3">
        <h5 class="card-title mb-0">{{ __('Laporan Arus Kas') }} — {{ $activePeriod->title }}</h5>
        <small class="text-muted">{{ __('Berdasarkan inputan anggaran RKAP yang dipetakan ke Cashflow Group') }}</small>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered align-middle mb-0" style="min-width: 600px;">
            <thead>
              <tr class="table-dark text-center fw-bold">
                <th class="text-start ps-4" style="min-width: 350px;">{{ __('KETERANGAN') }}</th>
                <th style="min-width: 160px; color: #93c5fd;">{{ __('Anggaran (B)') }}</th>
                <th style="min-width: 160px; color: #86efac;">{{ __('Realisasi (R)') }}</th>
                <th style="min-width: 160px; color: #67e8f9;">{{ __('Proyeksi (P)') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($reportData as $sectionId => $section)
              @php
                $color = $categoryColors[$section['label']] ?? ['bg' => '#6c757d', 'badge' => 'bg-secondary'];
              @endphp

              {{-- Category Header --}}
              <tr>
                <td colspan="4" class="fw-bold text-white text-uppercase py-2 ps-3"
                    style="background-color: {{ $color['bg'] }}; letter-spacing: 0.04em;">
                  <i class="bx bx-wallet me-2"></i>{{ $section['label'] }}
                </td>
              </tr>

              {{-- Sub-rows (cashflow groups) --}}
              @forelse ($section['rows'] as $row)
              <tr class="@if($loop->even) table-light @endif">
                <td class="ps-5">
                  <span class="badge {{ $row['is_inflow'] ? 'bg-label-success' : 'bg-label-danger' }} me-2" style="font-size:0.7rem;">
                    {{ $row['is_inflow'] ? 'IN' : 'OUT' }}
                  </span>
                  <span class="fw-semibold text-muted small me-1">{{ $row['code'] }}</span>
                  {{ $row['name'] }}
                </td>
                <td class="text-end fw-semibold text-primary">
                  {{ $row['budget'] != 0 ? 'Rp ' . number_format($row['budget'], 0, ',', '.') : '-' }}
                </td>
                <td class="text-end fw-semibold text-success">
                  {{ $row['realization'] != 0 ? 'Rp ' . number_format($row['realization'], 0, ',', '.') : '-' }}
                </td>
                <td class="text-end fw-semibold text-info">
                  {{ $row['projection'] != 0 ? 'Rp ' . number_format($row['projection'], 0, ',', '.') : '-' }}
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="4" class="text-center text-muted small py-2 ps-5">
                  <i class="bx bx-info-circle me-1"></i>{{ __('Tidak ada data untuk kategori ini.') }}
                </td>
              </tr>
              @endforelse

              {{-- Category Subtotal --}}
              <tr class="fw-bold border-top border-2">
                <td class="ps-4 text-uppercase" style="font-size: 0.8rem;">
                  <i class="bx bx-subdirectory-right me-1 text-muted"></i>
                  {{ __('Netto') }} {{ $section['label'] }}
                </td>
                @php
                  $sB = $section['subtotal']['budget'];
                  $sR = $section['subtotal']['realization'];
                  $sP = $section['subtotal']['projection'];
                @endphp
                <td class="text-end {{ $sB >= 0 ? 'text-primary' : 'text-danger' }}" style="background-color: rgba(13,110,253,.05);">
                  Rp {{ number_format($sB, 0, ',', '.') }}
                </td>
                <td class="text-end {{ $sR >= 0 ? 'text-success' : 'text-danger' }}" style="background-color: rgba(25,135,84,.05);">
                  Rp {{ number_format($sR, 0, ',', '.') }}
                </td>
                <td class="text-end {{ $sP >= 0 ? 'text-info' : 'text-danger' }}" style="background-color: rgba(13,202,240,.05);">
                  Rp {{ number_format($sP, 0, ',', '.') }}
                </td>
              </tr>

              {{-- Spacer row between categories --}}
              @if (!$loop->last)
              <tr style="height: 6px; background-color: #f8f9fa;">
                <td colspan="4" class="p-0 border-0"></td>
              </tr>
              @endif

              @endforeach

              {{-- Grand Total --}}
              <tr class="table-dark fw-bold border-top border-3">
                <td class="ps-3 text-uppercase" style="letter-spacing:0.05em;">
                  <i class="bx bx-coin-stack me-2"></i>{{ __('KENAIKAN / (PENURUNAN) NETTO KAS') }}
                </td>
                @php
                  $gB = $grandTotal['budget'];
                  $gR = $grandTotal['realization'];
                  $gP = $grandTotal['projection'];
                @endphp
                <td class="text-end fs-6 {{ $gB >= 0 ? 'text-info' : 'text-danger' }}">
                  Rp {{ number_format($gB, 0, ',', '.') }}
                </td>
                <td class="text-end fs-6 {{ $gR >= 0 ? 'text-info' : 'text-danger' }}">
                  Rp {{ number_format($gR, 0, ',', '.') }}
                </td>
                <td class="text-end fs-6 {{ $gP >= 0 ? 'text-info' : 'text-danger' }}">
                  Rp {{ number_format($gP, 0, ',', '.') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endif

@endif
@endsection
