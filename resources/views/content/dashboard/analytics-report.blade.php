@extends('layouts/contentNavbarLayout')

@section('title', 'Laporan - Laba Rugi')

@section('content')
  <style>
    .btn-check:checked+.btn-outline-primary {
      color: #fff !important;
      background-color: #696cff !important;
      border-color: #696cff !important;
    }

    /* Freeze pane style for P&L Table */
    .table-pn-report th:first-child,
    .table-pn-report td:first-child {
      position: sticky;
      left: 0;
      background-color: #fff;
      z-index: 2;
      border-right: 2px solid #e6e8eb;
    }

    .table-pn-report th:first-child {
      z-index: 3;
      background-color: #f5f5f9 !important;
    }

    /* Alternate row background styles */
    .table-pn-report tbody tr:nth-child(even) td:first-child {
      background-color: #fafafa;
    }

    .table-pn-report tbody tr:nth-child(odd) td:first-child {
      background-color: #ffffff;
    }

    .table-pn-report tbody tr.table-light td:first-child,
    .table-pn-report tbody tr.bg-lighter td:first-child {
      background-color: #f5f5f9 !important;
    }

    .table-pn-report tbody tr.table-primary td:first-child {
      background-color: #e7e7ff !important;
    }

    .table-pn-report tbody tr.table-info td:first-child {
      background-color: #d7f5fc !important;
    }

    .table-pn-report tbody tr.table-success td:first-child {
      background-color: #e8fadf !important;
    }
  </style>
  <div class="py-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
      <h4 class="mb-1"><span class="text-muted fw-light">RKAP /</span> Laporan Laba Rugi</h4>
      @if ($activePeriod)
        <p class="text-muted mb-0">Menampilkan Laporan Laba Rugi untuk periode: <strong>{{ $activePeriod->title }}</strong>
        </p>
      @else
        <div class="alert alert-warning mt-2 mb-0 py-2">
          <i class="bx bx-info-circle me-1"></i> Belum ada periode RKAP yang aktif.
        </div>
      @endif
    </div>

    @if ($finalizedPeriods->isNotEmpty())
      <div class="d-flex align-items-center gap-2 bg-white px-3 py-2 rounded shadow-sm border">
        <label for="periodSelect" class="text-muted fw-semibold mb-0 text-nowrap d-flex align-items-center gap-1"
          style="font-size: 0.9rem;">
          <i class="bx bx-calendar text-primary fs-4"></i>
          <span>Pilih Periode RKAP:</span>
        </label>
        <form action="{{ route('analytics-report') }}" method="GET" id="periodForm" class="m-0">
          <select name="period_id" id="periodSelect"
            class="form-select form-select-sm border-0 fw-semibold text-primary cursor-pointer focus-ring-none"
            onchange="this.form.submit()"
            style="font-size: 0.9rem; padding-right: 2.5rem; background-position: right 0.75rem center;">
            @if ($activePeriod && !$finalizedPeriods->contains('id', $activePeriod->id))
              <option value="" disabled selected>
                -- Pilih Periode Finalized (Saat ini: {{ $activePeriod->title }}) --
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
          <h5>Akses Dibatasi</h5>
          <p class="text-muted mb-0">Kepala Departemen tidak memiliki hak akses untuk melihat Laporan Laba Rugi.</p>
        </div>
      </div>
    @else
      <div class="row g-4">
        <!-- Profit & Loss Summary Card -->
        <div class="col-12">
          <div class="card">
            <div class="card-header border-bottom py-3">
              <h5 class="card-title mb-0">Ringkasan Laba Rugi (P&L Summary)</h5>
              <small class="text-muted">Ikhtisar Pendapatan & Beban Periode ini</small>
            </div>
            <div class="card-body pt-3">
              <div class="row g-4">
                <!-- Col 1: Pendapatan & Beban Langsung -->
                <div class="col-md-6 border-end">
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
                        <h6 class="mb-0 fw-bold">Rp {{ number_format($plSummary['revenue']['budget'] ?? 0, 0, ',', '.') }}
                        </h6>
                        <small class="text-success fw-medium">Real: Rp
                          {{ number_format($plSummary['revenue']['realization'] ?? 0, 0, ',', '.') }}</small>
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
                        <h6 class="mb-0 fw-bold">Rp
                          {{ number_format($plSummary['direct_cost']['budget'] ?? 0, 0, ',', '.') }}</h6>
                        <small class="text-info fw-medium">Real: Rp
                          {{ number_format($plSummary['direct_cost']['realization'] ?? 0, 0, ',', '.') }}</small>
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
                        <h6 class="mb-0 fw-bold text-primary">Rp
                          {{ number_format($plSummary['gross_profit']['budget'] ?? 0, 0, ',', '.') }}</h6>
                        <small class="text-primary fw-medium">Real: Rp
                          {{ number_format($plSummary['gross_profit']['realization'] ?? 0, 0, ',', '.') }}</small>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Col 2: Beban Tidak Langsung, Laba Usaha, Lain-lain & Laba Bersih -->
                <div class="col-md-6">
                  <div class="d-flex flex-column gap-3">
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
                        <h6 class="mb-0 fw-bold">Rp
                          {{ number_format($plSummary['indirect_cost']['budget'] ?? 0, 0, ',', '.') }}</h6>
                        <small class="text-warning fw-medium">Real: Rp
                          {{ number_format($plSummary['indirect_cost']['realization'] ?? 0, 0, ',', '.') }}</small>
                      </div>
                    </div>

                    <!-- Row 5: Laba Usaha -->
                    <div class="d-flex align-items-center justify-content-between bg-lighter p-2 rounded">
                      <div class="d-flex align-items-center">
                        <div class="badge bg-label-info p-2 rounded me-3"
                          style="background-color: rgba(3, 195, 236, 0.16) !important; color: #03c3ec !important;">
                          <i class="bx bx-line-chart fs-4"></i>
                        </div>
                        <div>
                          <h6 class="mb-0 fw-bold text-info" style="color: #03c3ec !important;">Laba (Rugi) Usaha</h6>
                          <small class="text-muted">Operating Profit</small>
                        </div>
                      </div>
                      <div class="text-end">
                        <h6 class="mb-0 fw-bold text-info" style="color: #03c3ec !important;">Rp
                          {{ number_format($plSummary['operating_profit']['budget'] ?? 0, 0, ',', '.') }}</h6>
                        <small class="text-info fw-medium" style="color: #03c3ec !important;">Real: Rp
                          {{ number_format($plSummary['operating_profit']['realization'] ?? 0, 0, ',', '.') }}</small>
                      </div>
                    </div>

                    <!-- Row 6: Other Income (exp) -->
                    <div class="d-flex align-items-center justify-content-between">
                      <div class="d-flex align-items-center">
                        <div class="badge bg-label-secondary p-2 rounded me-3">
                          <i class="bx bx-transfer fs-4"></i>
                        </div>
                        <div>
                          <h6 class="mb-0 fw-semibold">Lain-lain</h6>
                          <small class="text-muted">Other Income (exp)</small>
                        </div>
                      </div>
                      <div class="text-end">
                        <h6 class="mb-0 fw-bold">Rp
                          {{ number_format($plSummary['other_income_exp']['budget'] ?? 0, 0, ',', '.') }}</h6>
                        <small class="text-secondary fw-medium">Real: Rp
                          {{ number_format($plSummary['other_income_exp']['realization'] ?? 0, 0, ',', '.') }}</small>
                      </div>
                    </div>

                    <!-- Row 7: Laba Bersih -->
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
                        <h5 class="mb-0 fw-bold text-success">Rp
                          {{ number_format($plSummary['net_profit']['budget'] ?? 0, 0, ',', '.') }}</h5>
                        <small class="text-success fw-medium">Real: Rp
                          {{ number_format($plSummary['net_profit']['realization'] ?? 0, 0, ',', '.') }}</small>
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
            <div
              class="card-header border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <h5 class="card-title mb-0">Laporan Laba Rugi (Profit & Loss Summary)</h5>
                <small class="text-muted">Akumulasi anggaran, realisasi, dan proyeksi berdasarkan pemetaan kategori
                  P&L</small>
              </div>
            </div>
            <div class="table-responsive text-nowrap">
              <table class="table table-hover table-striped-columns mb-0 align-middle table-pn-report">
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

                        $isExpense =
                            $groupKey === 'Direct Cost' ||
                            $groupKey === 'Indirect Cost' ||
                            in_array($item['key'], ['7000', '7001', '7001A', '7002', '7002A', '7004', '7005']);
                        $itemVariance = $isExpense ? $itemBudget - $itemProj : $itemProj - $itemBudget;
                      @endphp
                      <tr>
                        <td class="ps-4">
                          <i class="bx bxs-circle text-{{ $item['color'] }} me-2"
                            style="font-size: 8px; vertical-align: middle;"></i>
                          <a href="#" class="coa-group-link text-decoration-none fw-medium"
                            data-coa-group-id="{{ $item['id'] }}" data-coa-group-name="{{ $item['label'] }}"
                            data-period-id="{{ $activePeriod->id }}">
                            {{ $item['label'] }}
                            <i class="bx bx-info-circle ms-1 text-muted" style="font-size: 11px;"></i>
                          </a>
                        </td>
                        <td class="text-end font-monospace">Rp {{ number_format($itemBudget, 0, ',', '.') }}</td>
                        <td class="text-end font-monospace">Rp {{ number_format($itemReal, 0, ',', '.') }}</td>
                        <td class="text-center font-monospace text-muted">
                          @if ($itemBudget > 0)
                            {{ number_format($itemRealPct, 1, ',', '.') }}%
                          @else
                            -
                          @endif
                        </td>
                        <td class="text-end font-monospace">Rp {{ number_format($itemProj, 0, ',', '.') }}</td>
                        <td class="text-center font-monospace text-muted">
                          @if ($itemBudget > 0)
                            {{ number_format($itemProjPct, 1, ',', '.') }}%
                          @else
                            -
                          @endif
                        </td>
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
                      $subRealPct = $subBudget > 0 ? ($subReal / $subBudget) * 100 : 0;
                      $subProjPct = $subBudget > 0 ? ($subProj / $subBudget) * 100 : 0;

                      $isGroupExpense = $groupKey === 'Direct Cost' || $groupKey === 'Indirect Cost';
                      $subVariance = $isGroupExpense ? $subBudget - $subProj : $subProj - $subBudget;
                    @endphp
                    <tr class="fw-semibold bg-lighter">
                      <td class="ps-3 text-secondary">
                        Subtotal {{ $group['label'] }}
                      </td>
                      <td class="text-end font-monospace">Rp {{ number_format($subBudget, 0, ',', '.') }}</td>
                      <td class="text-end font-monospace text-success">Rp {{ number_format($subReal, 0, ',', '.') }}
                      </td>
                      <td class="text-center font-monospace text-muted">
                        @if ($subBudget > 0)
                          {{ number_format($subRealPct, 1, ',', '.') }}%
                        @else
                          -
                        @endif
                      </td>
                      <td class="text-end font-monospace text-warning">Rp {{ number_format($subProj, 0, ',', '.') }}
                      </td>
                      <td class="text-center font-monospace text-muted">
                        @if ($subBudget > 0)
                          {{ number_format($subProjPct, 1, ',', '.') }}%
                        @else
                          -
                        @endif
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
                          @if ($gpBudget > 0)
                            {{ number_format($gpRealPct, 1, ',', '.') }}%
                          @else
                            -
                          @endif
                        </td>
                        <td class="text-end font-monospace">Rp {{ number_format($gpProj, 0, ',', '.') }}</td>
                        <td class="text-center font-monospace text-muted">
                          @if ($gpBudget > 0)
                            {{ number_format($gpProjPct, 1, ',', '.') }}%
                          @else
                            -
                          @endif
                        </td>
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
                          @if ($opBudget > 0)
                            {{ number_format($opRealPct, 1, ',', '.') }}%
                          @else
                            -
                          @endif
                        </td>
                        <td class="text-end font-monospace">Rp {{ number_format($opProj, 0, ',', '.') }}</td>
                        <td class="text-center font-monospace text-muted">
                          @if ($opBudget > 0)
                            {{ number_format($opProjPct, 1, ',', '.') }}%
                          @else
                            -
                          @endif
                        </td>
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
                    $npRealPct = $npBudget > 0 ? ($npReal / $npBudget) * 100 : 0;
                    $npProjPct = $npBudget > 0 ? ($npProj / $npBudget) * 100 : 0;
                    $npVariance = $npProj - $npBudget;
                  @endphp
                  <tr class="table-success fw-bold border-top border-2">
                    <td class="text-success" style="font-size: 1.1rem;">
                      <i class="bx bx-money me-2"></i>{{ $np['label'] }}
                    </td>
                    <td class="text-end font-monospace" style="font-size: 1.1rem;">Rp
                      {{ number_format($npBudget, 0, ',', '.') }}</td>
                    <td class="text-end font-monospace" style="font-size: 1.1rem;">Rp
                      {{ number_format($npReal, 0, ',', '.') }}</td>
                    <td class="text-center font-monospace text-muted" style="font-size: 1.1rem;">
                      @if ($npBudget > 0)
                        {{ number_format($npRealPct, 1, ',', '.') }}%
                      @else
                        -
                      @endif
                    </td>
                    <td class="text-end font-monospace" style="font-size: 1.1rem;">Rp
                      {{ number_format($npProj, 0, ',', '.') }}</td>
                    <td class="text-center font-monospace text-muted" style="font-size: 1.1rem;">
                      @if ($npBudget > 0)
                        {{ number_format($npProjPct, 1, ',', '.') }}%
                      @else
                        -
                      @endif
                    </td>
                    <td class="text-end font-monospace" style="font-size: 1.1rem;">
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
                </tbody>
              </table>
            </div>
          </div>
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

  {{-- Modal: COA Group Detail --}}
  <div class="modal fade" id="coaGroupDetailModal" tabindex="-1" aria-labelledby="coaGroupDetailModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header d-flex align-items-center text-white" style="background-color: #960b10ff !important;">
          <h5 class="modal-title d-flex align-items-center text-white mb-4" id="coaGroupDetailModalLabel"
            style="color: #ffffff !important;">
            <i class="bx bx-detail me-2 fs-4 text-white" style="color: #ffffff !important;"></i>
            <span id="coaGroupDetailTitle" class="text-white" style="color: #ffffff !important;">Detail COA</span>
          </h5>
          <button type="button" class="btn-close btn-close-white m-0" data-bs-dismiss="modal"
            aria-label="Tutup"></button>
        </div>
        <hr>
        <div class="modal-body p-0">
          <div id="coaGroupDetailLoading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2 mb-0">Memuat data...</p>
          </div>
          <div id="coaGroupDetailError" class="alert alert-warning m-3 d-none">
            <i class="bx bx-error-circle me-1"></i> Gagal memuat data. Silakan coba lagi.
          </div>
          <div id="coaGroupDetailTableWrap" class="d-none">
            <table class="table table-hover table-sm table-bordered mb-0 align-middle" style="font-size: 0.75rem;">
              <thead class="table-primary">
                <tr>
                  <th class="ps-3" style="min-width:200px;">COA</th>
                  <th style="min-width:220px;">Kegiatan</th>
                  <th class="text-end text-nowrap" style="min-width:140px;">Anggaran</th>
                  <th class="text-end text-nowrap" style="min-width:140px;">Realisasi YTD</th>
                  <th class="text-end text-nowrap" style="min-width:140px;">Proyeksi</th>
                </tr>
              </thead>
              <tbody id="coaGroupDetailTbody"></tbody>
              <tfoot id="coaGroupDetailTfoot" class="table-light fw-semibold"></tfoot>
            </table>
          </div>
          <p id="coaGroupDetailEmpty" class="text-center text-muted py-4 d-none">Tidak ada data untuk ditampilkan.</p>
        </div>
        <hr>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('page-script')
  @if ($activePeriod)
    <script>
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

            detailTitle.textContent = cgName;
            detailLoading.classList.remove('d-none');
            detailError.classList.add('d-none');
            detailWrap.classList.add('d-none');
            detailEmpty.classList.add('d-none');
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
                                <div class="font-monospace fw-semibold text-primary" style="font-size:0.72rem;">${row.coa_code}</div>
                                <div class="text-muted" style="font-size:0.72rem;">${row.coa_title || '-'}</div>
                            </td>
                            <td>
                                <div class="text-muted fw-semibold mb-1" style="font-size:0.68rem; letter-spacing: 0.5px;">${row.directorate_code || '-'} - ${row.department_code || '-'} - ${row.bureau_code || '-'}</div>
                                <div class="font-monospace fw-semibold text-primary" style="font-size:0.72rem;">${row.program_code || '-'}</div>
                                <div class="text-muted" style="font-size:0.72rem;">${row.program_name || '-'}</div>
                            </td>
                            <td class="text-end font-monospace text-nowrap">${formatRp(b)}</td>
                            <td class="text-end font-monospace text-success text-nowrap">${formatRp(r)}</td>
                            <td class="text-end font-monospace text-warning text-nowrap">${formatRp(p)}</td>
                        </tr>`;
                });

                detailTbody.innerHTML = tbodyHtml;
                detailTfoot.innerHTML = `
                    <tr class="bg-opacity-75">
                        <td colspan="2" class="ps-3 fw-bold">TOTAL</td>
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
