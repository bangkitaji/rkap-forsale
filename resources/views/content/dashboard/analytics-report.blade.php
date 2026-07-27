@extends('layouts/contentNavbarLayout')

@section('title', __('Laporan - Laba Rugi'))

@section('content')
<div class="py-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
  <div>
    <h4 class="mb-1"><span class="text-muted fw-light">{{ __('RKAP') }} /</span> {{ __('Laporan Laba Rugi') }}</h4>
    @if ($activePeriod)
    <p class="text-muted mb-0">{{ __('Menampilkan Laporan Laba Rugi untuk periode:') }} <strong>{{ $activePeriod->title }}</strong>
    </p>
    @else
    <div class="alert alert-warning mt-2 mb-0 py-2">
      <i class="bx bx-info-circle me-1"></i> {{ __('Belum ada periode RKAP yang aktif.') }}
    </div>
    @endif
  </div>

  @if ($finalizedPeriods->isNotEmpty())
  <div class="d-flex align-items-center gap-2 bg-white px-3 py-2 rounded shadow-sm border">
    <label for="periodSelect" class="text-muted fw-semibold mb-0 text-nowrap d-flex align-items-center gap-1 rkap-font-09">
      <i class="bx bx-calendar text-primary fs-4"></i>
      <span>{{ __('Pilih Periode RKAP:') }}</span>
    </label>
    <form action="{{ route('analytics-report') }}" method="GET" id="periodForm" class="m-0">
      <select name="period_id" id="periodSelect"
        class="form-select form-select-sm border-0 fw-semibold text-primary cursor-pointer focus-ring-none rkap-font-09"
        onchange="this.form.submit()">
        @if ($activePeriod && !$finalizedPeriods->contains('id', $activePeriod->id))
        <option value="" disabled selected>
          -- {{ __('Pilih Periode Finalized') }} ({{ __('Saat ini') }}: {{ $activePeriod->title }}) --
        </option>
        @endif
        @foreach ($finalizedPeriods as $p)
        <option value="{{ $p->id }}" {{ $activePeriod && $activePeriod->id == $p->id ? 'selected' : '' }}>
          {{ $p->title }} ({{ $p->year }})
        </option>
        @endforeach
      </select>
    </form>
  </div>
  @endif
</div>

@if ($activePeriod)
@if (auth()->user()->isKepalaDepartemen())
<div class="card text-center py-5">
  <div class="card-body">
    <i class="bx bx-lock-alt bx-lg text-warning mb-3"></i>
    <h5>{{ __('Akses Dibatasi') }}</h5>
    <p class="text-muted mb-0">{{ __('Kepala Departemen tidak memiliki hak akses untuk melihat Laporan Laba Rugi.') }}</p>
  </div>
</div>
@else
<div class="row g-4">
  <!-- Profit & Loss Summary Card -->
  <div class="col-12">
    <div class="card">
      <div class="card-header border-bottom py-3">
        <h5 class="card-title mb-0">{{ __('Ringkasan Laba Rugi (P&L Summary)') }}</h5>
        <small class="text-muted">{{ __('Ikhtisar Pendapatan & Beban Periode ini') }}</small>
      </div>
      <div class="card-body pt-3">
        <!-- KPI Cards Row -->
        <div class="row g-3 mb-4">
          <!-- Revenue Card -->
          <div class="col-sm-6 col-lg-3">
            <div class="card bg-label-success border-0 shadow-none h-100">
              <div class="card-body p-3">
                <div class="d-flex align-items-center mb-2">
                  <div class="avatar avatar-sm me-2">
                    <span class="avatar-initial rounded bg-success"><i class="bx bx-trending-up fs-4"></i></span>
                  </div>
                  <span class="fw-semibold text-success small">{{ __('Revenue') }}</span>
                </div>
                <h5 class="card-title mb-1 fw-bold text-success">Rp {{ number_format($plSummary['revenue']['budget'] ?? 0, 0, ',', '.') }}</h5>
                <small class="text-muted d-block">Real: Rp {{ number_format($plSummary['revenue']['realization'] ?? 0, 0, ',', '.') }}</small>
              </div>
            </div>
          </div>
          <!-- Gross Profit Card -->
          <div class="col-sm-6 col-lg-3">
            <div class="card bg-label-primary border-0 shadow-none h-100">
              <div class="card-body p-3">
                <div class="d-flex align-items-center mb-2">
                  <div class="avatar avatar-sm me-2">
                    <span class="avatar-initial rounded bg-primary"><i class="bx bx-calculator fs-4"></i></span>
                  </div>
                  <span class="fw-semibold text-primary small">{{ __('Gross Profit') }}</span>
                </div>
                <h5 class="card-title mb-1 fw-bold text-primary">Rp {{ number_format($plSummary['gross_profit']['budget'] ?? 0, 0, ',', '.') }}</h5>
                <small class="text-muted d-block">Real: Rp {{ number_format($plSummary['gross_profit']['realization'] ?? 0, 0, ',', '.') }}</small>
              </div>
            </div>
          </div>
          <!-- EBITDA Card -->
          <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-none h-100 rkap-bg-primary-soft">
              <div class="card-body p-3">
                <div class="d-flex align-items-center mb-2">
                  <div class="avatar avatar-sm me-2">
                    <span class="avatar-initial rounded rkap-bg-primary-solid"><i class="bx bx-bar-chart-alt-2 fs-4"></i></span>
                  </div>
                  <span class="fw-semibold small rkap-text-primary-solid">{{ __('EBITDA') }}</span>
                </div>
                <h5 class="card-title mb-1 fw-bold rkap-text-primary-solid">Rp {{ number_format($plSummary['ebitda']['budget'] ?? 0, 0, ',', '.') }}</h5>
                <small class="text-muted d-block">Real: Rp {{ number_format($plSummary['ebitda']['realization'] ?? 0, 0, ',', '.') }}</small>
              </div>
            </div>
          </div>
          <!-- Net Profit Card -->
          <div class="col-sm-6 col-lg-3">
            <div class="card text-white border-0 shadow-none h-100 rkap-bg-gradient-success">
              <div class="card-body p-3">
                <div class="d-flex align-items-center mb-2">
                  <div class="avatar avatar-sm me-2">
                    <span class="avatar-initial rounded bg-white text-success"><i class="bx bx-money fs-4"></i></span>
                  </div>
                  <span class="fw-semibold text-white small">{{ __('Net Profit') }}</span>
                </div>
                <h5 class="card-title mb-1 fw-bold text-white">Rp {{ number_format($plSummary['net_profit']['budget'] ?? 0, 0, ',', '.') }}</h5>
                <small class="text-white rkap-text-white-80 d-block">Real: Rp {{ number_format($plSummary['net_profit']['realization'] ?? 0, 0, ',', '.') }}</small>
              </div>
            </div>
          </div>
        </div>

        <hr class="my-4">

        <!-- Statement Flow Grid -->
        <div class="row g-4">
          <!-- Col 1: Revenue to Operating Expenses -->
          <div class="col-md-6 border-end">
            <div class="d-flex flex-column gap-3">
              <!-- Row 1: Revenue -->
              <div class="d-flex align-items-center justify-content-between p-2 rounded">
                <div class="d-flex align-items-center">
                  <div class="badge bg-label-success p-2 rounded me-3">
                    <i class="bx bx-trending-up fs-4"></i>
                  </div>
                  <div>
                    <h6 class="mb-0 fw-semibold">{{ __('Pendapatan') }}</h6>
                    <small class="text-muted">{{ __('Revenue') }}</small>
                  </div>
                </div>
                <div class="text-end">
                  <h6 class="mb-0 fw-bold">Rp {{ number_format($plSummary['revenue']['budget'] ?? 0, 0, ',', '.') }}</h6>
                  <small class="text-success fw-medium">Real: Rp {{ number_format($plSummary['revenue']['realization'] ?? 0, 0, ',', '.') }}</small>
                </div>
              </div>

              <!-- Row 2: Direct Cost -->
              <div class="d-flex align-items-center justify-content-between p-2 rounded">
                <div class="d-flex align-items-center">
                  <div class="badge bg-label-info p-2 rounded me-3">
                    <i class="bx bx-receipt fs-4"></i>
                  </div>
                  <div>
                    <h6 class="mb-0 fw-semibold">{{ __('Beban Langsung') }}</h6>
                    <small class="text-muted">{{ __('Direct Cost') }}</small>
                  </div>
                </div>
                <div class="text-end">
                  <h6 class="mb-0 fw-bold">Rp {{ number_format($plSummary['direct_cost']['budget'] ?? 0, 0, ',', '.') }}</h6>
                  <small class="text-info fw-medium">Real: Rp {{ number_format($plSummary['direct_cost']['realization'] ?? 0, 0, ',', '.') }}</small>
                </div>
              </div>

              <!-- Row 3: Gross Profit -->
              <div class="d-flex align-items-center justify-content-between bg-lighter p-3 rounded">
                <div class="d-flex align-items-center">
                  <div class="badge bg-label-primary p-2 rounded me-3">
                    <i class="bx bx-calculator fs-4"></i>
                  </div>
                  <div>
                    <h6 class="mb-0 fw-bold text-primary">{{ __('Laba Kotor') }}</h6>
                    <small class="text-muted">{{ __('Gross Profit') }}</small>
                  </div>
                </div>
                <div class="text-end">
                  <h6 class="mb-0 fw-bold text-primary">Rp {{ number_format($plSummary['gross_profit']['budget'] ?? 0, 0, ',', '.') }}</h6>
                  <small class="text-primary fw-medium">Real: Rp {{ number_format($plSummary['gross_profit']['realization'] ?? 0, 0, ',', '.') }}</small>
                </div>
              </div>

              <!-- Row 4: Indirect Cost -->
              <div class="d-flex align-items-center justify-content-between p-2 rounded">
                <div class="d-flex align-items-center">
                  <div class="badge bg-label-warning p-2 rounded me-3">
                    <i class="bx bx-credit-card fs-4"></i>
                  </div>
                  <div>
                    <h6 class="mb-0 fw-semibold">{{ __('Beban Tidak Langsung') }}</h6>
                    <small class="text-muted">{{ __('Indirect Cost') }}</small>
                  </div>
                </div>
                <div class="text-end">
                  <h6 class="mb-0 fw-bold">Rp {{ number_format($plSummary['indirect_cost']['budget'] ?? 0, 0, ',', '.') }}</h6>
                  <small class="text-warning fw-medium">Real: Rp {{ number_format($plSummary['indirect_cost']['realization'] ?? 0, 0, ',', '.') }}</small>
                </div>
              </div>
            </div>
          </div>

          <!-- Col 2: Operating Profit, Other Income, Other Expense, Net Profit -->
          <div class="col-md-6">
            <div class="d-flex flex-column gap-3">
              <!-- Row 5: Laba Usaha -->
              <div class="d-flex align-items-center justify-content-between bg-lighter p-3 rounded">
                <div class="d-flex align-items-center">
                  <div class="badge bg-label-info p-2 rounded me-3 rkap-bg-info-soft rkap-text-info-solid">
                    <i class="bx bx-line-chart fs-4"></i>
                  </div>
                  <div>
                    <h6 class="mb-0 fw-bold text-info rkap-text-info-solid">{{ __('Laba (Rugi) Usaha') }}</h6>
                    <small class="text-muted">{{ __('Operating Profit') }}</small>
                  </div>
                </div>
                <div class="text-end">
                  <h6 class="mb-0 fw-bold text-info rkap-text-info-solid">Rp {{ number_format($plSummary['operating_profit']['budget'] ?? 0, 0, ',', '.') }}</h6>
                  <small class="text-info fw-medium rkap-text-info-solid">Real: Rp {{ number_format($plSummary['operating_profit']['realization'] ?? 0, 0, ',', '.') }}</small>
                </div>
              </div>

              <!-- Row 6: Other Income -->
              <div class="d-flex align-items-center justify-content-between p-2 rounded">
                <div class="d-flex align-items-center">
                  <div class="badge bg-label-success p-2 rounded me-3">
                    <i class="bx bx-plus-circle fs-4"></i>
                  </div>
                  <div>
                    <h6 class="mb-0 fw-semibold">{{ __('Pendapatan Lainnya') }}</h6>
                    <small class="text-muted">{{ __('Other Income') }}</small>
                  </div>
                </div>
                <div class="text-end">
                  <h6 class="mb-0 fw-bold">Rp {{ number_format($plSummary['other_income']['budget'] ?? 0, 0, ',', '.') }}</h6>
                  <small class="text-success fw-medium">Real: Rp {{ number_format($plSummary['other_income']['realization'] ?? 0, 0, ',', '.') }}</small>
                </div>
              </div>

              <!-- Row 7: Other Expense -->
              <div class="d-flex align-items-center justify-content-between p-2 rounded">
                <div class="d-flex align-items-center">
                  <div class="badge bg-label-danger p-2 rounded me-3">
                    <i class="bx bx-minus-circle fs-4"></i>
                  </div>
                  <div>
                    <h6 class="mb-0 fw-semibold">{{ __('Beban Lainnya') }}</h6>
                    <small class="text-muted">{{ __('Other Expense') }}</small>
                  </div>
                </div>
                <div class="text-end">
                  <h6 class="mb-0 fw-bold">Rp {{ number_format($plSummary['other_expense']['budget'] ?? 0, 0, ',', '.') }}</h6>
                  <small class="text-danger fw-medium">Real: Rp {{ number_format($plSummary['other_expense']['realization'] ?? 0, 0, ',', '.') }}</small>
                </div>
              </div>

              <!-- Row 8: Net Profit -->
              <div class="d-flex align-items-center justify-content-between bg-label-success p-3 rounded">
                <div class="d-flex align-items-center">
                  <div class="badge bg-success text-white p-2 rounded me-3">
                    <i class="bx bx-money fs-4"></i>
                  </div>
                  <div>
                    <h6 class="mb-0 fw-bold text-success">{{ __('Laba Bersih') }}</h6>
                    <small class="text-success opacity-75">{{ __('Net Profit') }}</small>
                  </div>
                </div>
                <div class="text-end">
                  <h6 class="mb-0 fw-bold text-success">Rp {{ number_format($plSummary['net_profit']['budget'] ?? 0, 0, ',', '.') }}</h6>
                  <small class="text-success fw-medium">Real: Rp {{ number_format($plSummary['net_profit']['realization'] ?? 0, 0, ',', '.') }}</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Profit and Loss Summary Table --}}
<div class="row mt-4">
  <div class="col-12">
    <div class="card h-100">
      <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
          <h5 class="card-title mb-0">{{ __('Laporan Laba Rugi (Profit & Loss Statement)') }}</h5>
          <small class="text-muted">{{ __('Akumulasi anggaran, realisasi, dan proyeksi berdasarkan pemetaan kategori P&L') }}</small>
        </div>
        <div>
          <div class="btn-group" role="group" aria-label="View mode toggle">
            <button type="button" class="btn btn-outline-primary btn-sm active" id="btnCumulativeView" onclick="switchPLViewMode('cumulative')">
              <i class="bx bx-list-ul me-1"></i>{{ __('Ringkasan Kumulatif') }}
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnMonthlyView" onclick="switchPLViewMode('monthly')">
              <i class="bx bx-calendar-event me-1"></i>{{ __('Rincian Bulanan') }}
            </button>
          </div>
        </div>
      </div>

      {{-- Cumulative View Container --}}
      <div id="cumulativeViewWrap">
        <div class="table-responsive text-nowrap">
          <table class="table table-hover table-striped-columns mb-0 align-middle table-pn-report">
            <thead>
              <tr class="table-light">
                <th>{{ __('Kategori / Golongan') }}</th>
                <th class="text-end">{{ __('Anggaran (Budget)') }}</th>
                <th class="text-end">{{ __('Realisasi YTD') }}</th>
                <th class="text-end">{{ __('Proyeksi (Outlook)') }}</th>
                <th class="text-end">{{ __('Selisih (Variance)') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($plGroups as $groupKey => $group)
              <!-- Group Header (Bold, Uppercase) -->
              <tr class="table-light fw-bold text-uppercase rkap-ls-05">
                <td colspan="5">
                  <i class="bx bx-folder me-2 text-primary"></i>{{ $group['label'] }}
                </td>
              </tr>

              <!-- Group Items -->
              @foreach ($group['items'] as $item)
              @php
              $itemBudget = $item['budget'];
              $itemReal = $item['realization'];
              $itemProj = $item['projection'];

              $isExpense =
              $groupKey === 'Direct Cost' ||
              $groupKey === 'Indirect Cost' ||
              in_array($item['key'], ['7000', '7001', '7001A', '7002', '7002A', '7004', '7005']);
              $itemVariance = $isExpense ? $itemBudget - $itemProj : $itemProj - $itemBudget;
              @endphp
              <tr>
                <td class="ps-4">
                  <i class="bx bxs-circle text-{{ $item['color'] }} me-2 rkap-font-05 rkap-v-align-middle"></i>
                  <a href="#" class="coa-group-link text-decoration-none fw-medium"
                    data-coa-group-id="{{ $item['id'] }}" data-coa-group-name="{{ $item['label'] }}"
                    data-period-id="{{ $activePeriod->id }}">
                    {{ $item['label'] }}
                    <i class="bx bx-info-circle ms-1 text-muted rkap-font-068"></i>
                  </a>
                </td>
                <td class="text-end font-monospace">Rp {{ number_format($itemBudget, 0, ',', '.') }}</td>
                <td class="text-end font-monospace">Rp {{ number_format($itemReal, 0, ',', '.') }}</td>
                <td class="text-end font-monospace">Rp {{ number_format($itemProj, 0, ',', '.') }}</td>
                <td class="text-end font-monospace">
                  @if ($itemVariance > 0)
                  <span class="text-success"><i class="bx bx-chevron-up me-1"></i>Rp
                    {{ number_format($itemVariance, 0, ',', '.') }}</span>
                  @elseif($itemVariance < 0)
                    <span class="text-danger"><i class="bx bx-chevron-down me-1"></i>(Rp
                    {{ number_format(abs($itemVariance), 0, ',', '.') }})</span>
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

              $isGroupExpense = $groupKey === 'Direct Cost' || $groupKey === 'Indirect Cost';
              $subVariance = $isGroupExpense ? $subBudget - $subProj : $subProj - $subBudget;
              @endphp
              <tr class="fw-semibold bg-lighter">
                <td class="ps-3 text-secondary">
                  {{ __('Subtotal') }} {{ $group['label'] }}
                </td>
                <td class="text-end font-monospace">Rp {{ number_format($subBudget, 0, ',', '.') }}</td>
                <td class="text-end font-monospace text-success">Rp {{ number_format($subReal, 0, ',', '.') }}
                </td>
                <td class="text-end font-monospace text-warning">Rp {{ number_format($subProj, 0, ',', '.') }}
                </td>
                <td class="text-end font-monospace">
                  @if ($subVariance > 0)
                  <span class="text-success fw-semibold"><i class="bx bx-chevron-up me-1"></i>Rp
                    {{ number_format($subVariance, 0, ',', '.') }}</span>
                  @elseif($subVariance < 0)
                    <span class="text-danger fw-semibold"><i class="bx bx-chevron-down me-1"></i>(Rp
                    {{ number_format(abs($subVariance), 0, ',', '.') }})</span>
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
              $gpVariance = $gpProj - $gpBudget;
              @endphp
              <tr class="table-primary fw-bold">
                <td class="text-primary">
                  <i class="bx bx-calculator me-2"></i>{{ $gp['label'] }}
                </td>
                <td class="text-end font-monospace">Rp {{ number_format($gpBudget, 0, ',', '.') }}</td>
                <td class="text-end font-monospace">Rp {{ number_format($gpReal, 0, ',', '.') }}</td>
                <td class="text-end font-monospace">Rp {{ number_format($gpProj, 0, ',', '.') }}</td>
                <td class="text-end font-monospace">
                  @if ($gpVariance > 0)
                  <span class="text-success"><i class="bx bx-chevron-up me-1"></i>Rp
                    {{ number_format($gpVariance, 0, ',', '.') }}</span>
                  @elseif($gpVariance < 0)
                    <span class="text-danger"><i class="bx bx-chevron-down me-1"></i>(Rp
                    {{ number_format(abs($gpVariance), 0, ',', '.') }})</span>
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
              $opVariance = $opProj - $opBudget;
              @endphp
              <tr class="table-info fw-bold">
                <td class="text-info rkap-color-info">
                  <i class="bx bx-trending-up me-2"></i>{{ $op['label'] }}
                </td>
                <td class="text-end font-monospace">Rp {{ number_format($opBudget, 0, ',', '.') }}</td>
                <td class="text-end font-monospace">Rp {{ number_format($opReal, 0, ',', '.') }}</td>
                <td class="text-end font-monospace">Rp {{ number_format($opProj, 0, ',', '.') }}</td>
                <td class="text-end font-monospace">
                  @if ($opVariance > 0)
                  <span class="text-success"><i class="bx bx-chevron-up me-1"></i>Rp
                    {{ number_format($opVariance, 0, ',', '.') }}</span>
                  @elseif($opVariance < 0)
                    <span class="text-danger"><i class="bx bx-chevron-down me-1"></i>(Rp
                    {{ number_format(abs($opVariance), 0, ',', '.') }})</span>
                    @else
                    <span class="text-muted">-</span>
                    @endif
                </td>
              </tr>
              @endif
              @endforeach

              <!-- Final Net Profit Summary Row -->
              @php
              $np = $plSummary['net_profit'];
              $npBudget = $np['budget'];
              $npReal = $np['realization'];
              $npProj = $np['projection'];
              $npVariance = $npProj - $npBudget;
              @endphp
              <tr class="table-success fw-bold border-top border-2">
                <td class="text-success rkap-font-11">
                  <i class="bx bx-money me-2"></i>{{ $np['label'] }}
                </td>
                <td class="text-end font-monospace rkap-font-11">Rp
                  {{ number_format($npBudget, 0, ',', '.') }}
                </td>
                <td class="text-end font-monospace rkap-font-11">Rp
                  {{ number_format($npReal, 0, ',', '.') }}
                </td>
                <td class="text-end font-monospace rkap-font-11">Rp
                  {{ number_format($npProj, 0, ',', '.') }}
                </td>
                <td class="text-end font-monospace rkap-font-11">
                  @if ($npVariance > 0)
                  <span class="text-success"><i class="bx bx-chevron-up me-1"></i>Rp
                    {{ number_format($npVariance, 0, ',', '.') }}</span>
                  @elseif($npVariance < 0)
                    <span class="text-danger"><i class="bx bx-chevron-down me-1"></i>(Rp
                    {{ number_format(abs($npVariance), 0, ',', '.') }})</span>
                    @else
                    <span class="text-muted">-</span>
                    @endif
                </td>
              </tr>

              <!-- EBITDA Summary Row -->
              @php
              $eb = $plSummary['ebitda'];
              $ebBudget = $eb['budget'];
              $ebReal = $eb['realization'];
              $ebProj = $eb['projection'];
              $ebVariance = $ebProj - $ebBudget;
              @endphp
              <tr class="fw-bold border-top rkap-bg-primary-lighter">
                <td class="rkap-text-primary-solid rkap-font-105 rkap-border-dashed-primary">
                  <i class="bx bx-bar-chart-alt-2 me-2"></i>{{ $eb['label'] }}
                </td>
                <td class="text-end font-monospace rkap-font-105 rkap-border-dashed-primary">Rp
                  {{ number_format($ebBudget, 0, ',', '.') }}
                </td>
                <td class="text-end font-monospace rkap-font-105 rkap-border-dashed-primary">Rp
                  {{ number_format($ebReal, 0, ',', '.') }}
                </td>
                <td class="text-end font-monospace rkap-font-105 rkap-border-dashed-primary">Rp
                  {{ number_format($ebProj, 0, ',', '.') }}
                </td>
                <td class="text-end font-monospace rkap-font-105 rkap-border-dashed-primary">
                  @if ($ebVariance > 0)
                  <span class="text-success"><i class="bx bx-chevron-up me-1"></i>Rp
                    {{ number_format($ebVariance, 0, ',', '.') }}</span>
                  @elseif($ebVariance < 0)
                    <span class="text-danger"><i class="bx bx-chevron-down me-1"></i>(Rp
                    {{ number_format(abs($ebVariance), 0, ',', '.') }})</span>
                    @else
                    <span class="text-muted">-</span>
                    @endif
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Monthly View Container --}}
      <div id="monthlyViewWrap" class="d-none">
        <div class="d-flex align-items-center justify-content-between px-3 py-2 bg-label-warning border-bottom gap-2 flex-wrap mb-3">
          <div class="d-flex align-items-center gap-2">
            <span class="text-muted small fw-semibold">{{ __('Pilih Tipe Data:') }}</span>
            <div class="btn-group" role="group" aria-label="Monthly data type selector">
              <button type="button" class="btn btn-outline-warning btn-sm active" id="btnMonthlyBudget" onclick="switchMonthlyDataType('budget')">
                {{ __('Anggaran (Budget)') }}
              </button>
              <button type="button" class="btn btn-outline-warning btn-sm" id="btnMonthlyRealization" onclick="switchMonthlyDataType('realization')">
                {{ __('Realisasi (Realization)') }}
              </button>
              <button type="button" class="btn btn-outline-warning btn-sm" id="btnMonthlyProjection" onclick="switchMonthlyDataType('projection')">
                {{ __('Proyeksi (Outlook)') }}
              </button>
            </div>
          </div>
          <div class="text-muted small">
            <i class="bx bx-info-circle me-1"></i>{{ __('Menampilkan rincian nominal per bulan dari Januari hingga Desember') }}
          </div>
        </div>

        @foreach (['budget', 'realization', 'projection'] as $type)
        @php
        $tableId = 'tableMonthly' . ucfirst($type);
        $tableClass = ($type === 'budget') ? '' : 'd-none';
        @endphp
        <div class="table-responsive text-nowrap {{ $tableClass }}" id="{{ $tableId }}">
          <table class="table table-hover table-striped-columns mb-0 align-middle table-pn-report">
            <thead>
              <tr class="table-light">
                <th>{{ __('Kategori / Golongan') }}</th>
                @foreach (['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'] as $mLabel)
                <th class="text-end">{{ __($mLabel) }}</th>
                @endforeach
                <th class="text-end">{{ __('Total') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($plGroupsMonthly as $groupKey => $group)
              <!-- Group Header -->
              <tr class="table-light fw-bold text-uppercase rkap-ls-05">
                <td colspan="14">
                  <i class="bx bx-folder me-2 text-primary"></i>{{ $group['label'] }}
                </td>
              </tr>

              <!-- Group Items -->
              @foreach ($group['items'] as $item)
              @php
              $itemValues = $item[$type];
              $rowTotal = array_sum($itemValues);
              @endphp
              <tr>
                <td class="ps-4">
                  <i class="bx bxs-circle text-{{ $item['color'] ?? 'secondary' }} me-2 rkap-font-05 rkap-v-align-middle"></i>
                  <a href="#" class="coa-group-link text-decoration-none fw-medium"
                    data-coa-group-id="{{ $item['id'] }}" data-coa-group-name="{{ $item['label'] }}"
                    data-period-id="{{ $activePeriod->id }}">
                    {{ $item['label'] }}
                    <i class="bx bx-info-circle ms-1 text-muted rkap-font-068"></i>
                  </a>
                </td>
                @for ($m = 1; $m <= 12; $m++)
                  <td class="text-end font-monospace text-nowrap">Rp {{ number_format($itemValues[$m] ?? 0, 0, ',', '.') }}</td>
                  @endfor
                  <td class="text-end font-monospace fw-bold text-nowrap">Rp {{ number_format($rowTotal, 0, ',', '.') }}</td>
              </tr>
              @endforeach

              <!-- Group Subtotal Row -->
              @php
              $subValues = $group[$type . '_subtotal'];
              $subTotal = array_sum($subValues);
              @endphp
              <tr class="fw-semibold bg-lighter">
                <td class="ps-3 text-secondary">
                  {{ __('Subtotal') }} {{ $group['label'] }}
                </td>
                @for ($m = 1; $m <= 12; $m++)
                  <td class="text-end font-monospace text-nowrap">Rp {{ number_format($subValues[$m] ?? 0, 0, ',', '.') }}</td>
                  @endfor
                  <td class="text-end font-monospace fw-bold text-primary text-nowrap">Rp {{ number_format($subTotal, 0, ',', '.') }}</td>
              </tr>

              <!-- Insert intermediate P&L summary rows if applicable -->
              @if ($groupKey === 'Direct Cost')
              @php
              $gpValues = $plSummaryMonthly['gross_profit'][$type];
              $gpTotal = array_sum($gpValues);
              @endphp
              <tr class="table-primary fw-bold">
                <td class="text-primary">
                  <i class="bx bx-calculator me-2"></i>{{ $plSummaryMonthly['gross_profit']['label'] }}
                </td>
                @for ($m = 1; $m <= 12; $m++)
                  <td class="text-end font-monospace text-nowrap">Rp {{ number_format($gpValues[$m] ?? 0, 0, ',', '.') }}</td>
                  @endfor
                  <td class="text-end font-monospace text-nowrap">Rp {{ number_format($gpTotal, 0, ',', '.') }}</td>
              </tr>
              @elseif ($groupKey === 'Indirect Cost')
              @php
              $opValues = $plSummaryMonthly['operating_profit'][$type];
              $opTotal = array_sum($opValues);
              @endphp
              <tr class="table-info fw-bold">
                <td class="text-info rkap-color-info">
                  <i class="bx bx-trending-up me-2"></i>{{ $plSummaryMonthly['operating_profit']['label'] }}
                </td>
                @for ($m = 1; $m <= 12; $m++)
                  <td class="text-end font-monospace text-nowrap">Rp {{ number_format($opValues[$m] ?? 0, 0, ',', '.') }}</td>
                  @endfor
                  <td class="text-end font-monospace text-nowrap">Rp {{ number_format($opTotal, 0, ',', '.') }}</td>
              </tr>
              @endif
              @endforeach

              <!-- Final Net Profit Summary Row -->
              @php
              $npValues = $plSummaryMonthly['net_profit'][$type];
              $npTotal = array_sum($npValues);
              @endphp
              <tr class="table-success fw-bold border-top border-2">
                <td class="text-success rkap-font-11">
                  <i class="bx bx-money me-2"></i>{{ $plSummaryMonthly['net_profit']['label'] }}
                </td>
                @for ($m = 1; $m <= 12; $m++)
                  <td class="text-end font-monospace rkap-font-11 text-nowrap">Rp {{ number_format($npValues[$m] ?? 0, 0, ',', '.') }}</td>
                  @endfor
                  <td class="text-end font-monospace rkap-font-11 text-nowrap">Rp {{ number_format($npTotal, 0, ',', '.') }}</td>
              </tr>

              <!-- EBITDA Summary Row -->
              @php
              $ebValues = $plSummaryMonthly['ebitda'][$type];
              $ebTotal = array_sum($ebValues);
              @endphp
              <tr class="fw-bold border-top rkap-bg-primary-lighter">
                <td class="rkap-text-primary-solid rkap-font-105 rkap-border-dashed-primary">
                  <i class="bx bx-bar-chart-alt-2 me-2"></i>{{ $plSummaryMonthly['ebitda']['label'] }}
                </td>
                @for ($m = 1; $m <= 12; $m++)
                  <td class="text-end font-monospace rkap-font-105 rkap-border-dashed-primary text-nowrap">Rp {{ number_format($ebValues[$m] ?? 0, 0, ',', '.') }}</td>
                  @endfor
                  <td class="text-end font-monospace rkap-font-105 rkap-border-dashed-primary text-nowrap">Rp {{ number_format($ebTotal, 0, ',', '.') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        @endforeach
      </div>

    </div>
  </div>
</div>
@endif
@else
<div class="card py-5 text-center">
  <div class="card-body">
    <i class="bx bx-error-circle bx-lg text-warning mb-3"></i>
    <h5>{{ __('Belum Ada Data RKAP Aktif') }}</h5>
    <p class="text-muted">{{ __('Sistem tidak menemukan periode RKAP yang aktif untuk divisualisasikan.') }}</p>
  </div>
</div>
@endif

{{-- Modal: COA Group Detail --}}
<div class="modal fade" id="coaGroupDetailModal" tabindex="-1" aria-labelledby="coaGroupDetailModalLabel"
  aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header d-flex align-items-center text-white rkap-bg-kcic-red">
        <h5 class="modal-title d-flex align-items-center text-white mb-0 rkap-text-white" id="coaGroupDetailModalLabel">
          <i class="bx bx-detail me-2 fs-4 text-white rkap-text-white"></i>
          <span id="coaGroupDetailTitle" class="text-white rkap-text-white">{{ __('Detail COA') }}</span>
        </h5>
        <div class="ms-auto d-flex gap-2 align-items-center">
          <button type="button" class="btn-close btn-close-white m-0" data-bs-dismiss="modal"
            aria-label="Tutup"></button>
        </div>
      </div>
      <div class="modal-body p-0">
        <div class="d-flex justify-content-end p-3 pb-3">
          <button type="button" class="btn btn-sm d-none text-white" id="btnExportCoaGroupDetail" style="background-color: #146c43; border-color: #146c43;">
            <i class="bx bx-export me-1"></i>{{ __('Export Excel') }}
          </button>
        </div>
        <div id="coaGroupDetailLoading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status"></div>
          <p class="text-muted mt-2 mb-0">{{ __('Memuat data...') }}</p>
        </div>
        <div id="coaGroupDetailError" class="alert alert-warning m-3 d-none">
          <i class="bx bx-error-circle me-1"></i> {{ __('Gagal memuat data. Silakan coba lagi.') }}
        </div>
        <div id="coaGroupDetailTableWrap" class="d-none">
          <table class="table table-hover table-sm table-bordered mb-0 align-middle rkap-font-075">
            <thead class="table-primary">
              <tr>
                <th class="ps-3 rkap-min-w-220">{{ __('COA') }}</th>
                <th class="rkap-min-w-220">{{ __('Kegiatan') }}</th>
                <th class="text-end text-nowrap rkap-min-w-140">{{ __('Anggaran') }}</th>
                <th class="text-end text-nowrap rkap-min-w-140">{{ __('Realisasi YTD') }}</th>
                <th class="text-end text-nowrap rkap-min-w-140">{{ __('Proyeksi') }}</th>
              </tr>
            </thead>
            <tbody id="coaGroupDetailTbody"></tbody>
            <tfoot id="coaGroupDetailTfoot" class="table-light fw-semibold"></tfoot>
          </table>
        </div>
        <p id="coaGroupDetailEmpty" class="text-center text-muted py-4 d-none">{{ __('Tidak ada data untuk ditampilkan.') }}</p>
      </div>
      <hr>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
@if ($activePeriod)
<script>
  window.switchPLViewMode = function(mode) {
    const cumBtn = document.getElementById('btnCumulativeView');
    const monBtn = document.getElementById('btnMonthlyView');
    const cumWrap = document.getElementById('cumulativeViewWrap');
    const monWrap = document.getElementById('monthlyViewWrap');

    if (mode === 'cumulative') {
      cumBtn.classList.add('active');
      monBtn.classList.remove('active');
      cumWrap.classList.remove('d-none');
      monWrap.classList.add('d-none');
    } else {
      cumBtn.classList.remove('active');
      monBtn.classList.add('active');
      cumWrap.classList.add('d-none');
      monWrap.classList.remove('d-none');
    }
  };

  window.switchMonthlyDataType = function(type) {
    const btnBudget = document.getElementById('btnMonthlyBudget');
    const btnReal = document.getElementById('btnMonthlyRealization');
    const btnProj = document.getElementById('btnMonthlyProjection');

    const tblBudget = document.getElementById('tableMonthlyBudget');
    const tblReal = document.getElementById('tableMonthlyRealization');
    const tblProj = document.getElementById('tableMonthlyProjection');

    btnBudget.classList.remove('active');
    btnReal.classList.remove('active');
    btnProj.classList.remove('active');

    tblBudget.classList.add('d-none');
    tblReal.classList.add('d-none');
    tblProj.classList.add('d-none');

    if (type === 'budget') {
      btnBudget.classList.add('active');
      tblBudget.classList.remove('d-none');
    } else if (type === 'realization') {
      btnReal.classList.add('active');
      tblReal.classList.remove('d-none');
    } else if (type === 'projection') {
      btnProj.classList.add('active');
      tblProj.classList.remove('d-none');
    }
  };

  document.addEventListener('DOMContentLoaded', function() {
    // COA Group Detail Modal
    const coaDetailModal = new bootstrap.Modal(document.getElementById('coaGroupDetailModal'));
    const detailTitle = document.getElementById('coaGroupDetailTitle');
    const detailLoading = document.getElementById('coaGroupDetailLoading');
    const detailError = document.getElementById('coaGroupDetailError');
    const detailWrap = document.getElementById('coaGroupDetailTableWrap');
    const detailTbody = document.getElementById('coaGroupDetailTbody');
    const detailTfoot = document.getElementById('coaGroupDetailTfoot');
    const detailEmpty = document.getElementById('coaGroupDetailEmpty');
    const btnExport = document.getElementById('btnExportCoaGroupDetail');

    let currentCgId = null;
    let currentPeriodId = null;

    btnExport.addEventListener('click', function() {
      if (currentCgId && currentPeriodId) {
        window.location.href = `/analytics/coa-group-detail?coa_group_id=${currentCgId}&period_id=${currentPeriodId}&export=excel`;
      }
    });

    function formatRp(val) {
      const num = parseFloat(val) || 0;
      return 'Rp ' + num.toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      });
    }

    document.querySelectorAll('.coa-group-link').forEach(function(link) {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        const cgId = this.dataset.coaGroupId;
        const cgName = this.dataset.coaGroupName;
        const periodId = this.dataset.periodId;
        
        currentCgId = cgId;
        currentPeriodId = periodId;

        detailTitle.textContent = cgName;
        detailLoading.classList.remove('d-none');
        detailError.classList.add('d-none');
        detailWrap.classList.add('d-none');
        detailEmpty.classList.add('d-none');
        btnExport.classList.add('d-none');
        detailTbody.innerHTML = '';
        detailTfoot.innerHTML = '';

        coaDetailModal.show();

        fetch(`/analytics/coa-group-detail?coa_group_id=${cgId}&period_id=${periodId}`, {
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            }
          })
          .then(function(r) {
            return r.ok ? r.json() : Promise.reject(r);
          })
          .then(function(json) {
            detailLoading.classList.add('d-none');
            const rows = json.data || [];

            if (rows.length === 0) {
              detailEmpty.classList.remove('d-none');
              return;
            }
            
            btnExport.classList.remove('d-none');

            let totalBudget = 0,
              totalReal = 0,
              totalProj = 0;
            let tbodyHtml = '';

            rows.forEach(function(row) {
              const b = parseFloat(row.budget) || 0;
              const r = parseFloat(row.realization) || 0;
              const p = parseFloat(row.projection) || 0;
              totalBudget += b;
              totalReal += r;
              totalProj += p;

              tbodyHtml += `
                        <tr>
                            <td class="ps-3">
                                <div class="font-monospace fw-semibold text-primary rkap-font-072">${row.coa_code}</div>
                                <div class="text-muted rkap-font-072">${row.coa_title || '-'}</div>
                            </td>
                            <td>
                                <div class="text-muted fw-semibold mb-1 rkap-font-068 rkap-ls-05">${row.directorate_code || '-'} - ${row.department_code || '-'} - ${row.bureau_code || '-'}</div>
                                <div class="font-monospace fw-semibold text-primary rkap-font-072">${row.program_code || '-'}</div>
                                <div class="text-muted rkap-font-072">${row.program_name || '-'}</div>
                            </td>
                            <td class="text-end font-monospace text-nowrap">${formatRp(b)}</td>
                            <td class="text-end font-monospace text-success text-nowrap">${formatRp(r)}</td>
                            <td class="text-end font-monospace text-warning text-nowrap">${formatRp(p)}</td>
                        </tr>`;
            });

            detailTbody.innerHTML = tbodyHtml;
            detailTfoot.innerHTML = `
                    <tr class="bg-opacity-75">
                        <td colspan="2" class="ps-3 fw-bold">{{ __('TOTAL') }}</td>
                        <td class="text-end font-monospace fw-bold text-nowrap">${formatRp(totalBudget)}</td>
                        <td class="text-end font-monospace text-success fw-bold text-nowrap">${formatRp(totalReal)}</td>
                        <td class="text-end font-monospace text-warning fw-bold text-nowrap">${formatRp(totalProj)}</td>
                    </tr>`;
            detailWrap.classList.remove('d-none');
          })
          .catch(function() {
            detailLoading.classList.add('d-none');
            detailError.classList.remove('d-none');
          });
      });
    });
  });
</script>
@endif
@endsection