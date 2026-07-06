@extends('layouts/contentNavbarLayout')

@section('title', 'Laporan - Cash Flow')

@section('content')
<style>
  .btn-check:checked+.btn-outline-primary {
    color: #fff !important;
    background-color: #696cff !important;
    border-color: #696cff !important;
  }

  /* Freeze pane style for Cash Flow Table */
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
    <h4 class="mb-1"><span class="text-muted fw-light">RKAP /</span> Laporan Cash Flow</h4>
    @if ($activePeriod)
    <p class="text-muted mb-0">Menampilkan Laporan Cash Flow untuk periode: <strong>{{ $activePeriod->title }}</strong>
    </p>
    @else
    <div class="alert alert-warning mt-2 mb-0 py-2">
      <i class="bx bx-info-circle me-1"></i> Belum ada periode RKAP yang aktif.
    </div>
    @endif
  </div>

  @if ($finalizedPeriods->isNotEmpty())
  <div class="d-flex align-items-center gap-2 bg-white px-3 py-2 rounded shadow-sm border">
    <label for="periodSelect" class="text-muted fw-semibold mb-0 text-nowrap d-flex align-items-center gap-1 rkap-font-09">
      <i class="bx bx-calendar text-primary fs-4"></i>
      <span>Pilih Periode RKAP:</span>
    </label>
    <form action="{{ route('analytics-cashflow') }}" method="GET" id="periodForm" class="m-0">
      <select name="period_id" id="periodSelect"
        class="form-select form-select-sm border-0 fw-semibold text-primary cursor-pointer focus-ring-none rkap-font-09"
        onchange="this.form.submit()">
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
    <p class="text-muted mb-0">Kepala Departemen tidak memiliki hak akses untuk melihat Laporan Cash Flow.</p>
  </div>
</div>
@else
<div class="row g-4">
  <!-- Cash Flow Summary Card -->
  <div class="col-12">
    <div class="card">
      <div class="card-header border-bottom py-3">
        <h5 class="card-title mb-0">Ringkasan Cash Flow (Arus Kas)</h5>
        <small class="text-muted">Ikhtisar Penerimaan & Pengeluaran Kas Periode ini</small>
      </div>
      <div class="card-body pt-3">
        <div class="row g-4">
          <!-- Col 1: Penerimaan Kas -->
          <div class="col-md-4 border-end">
            <div class="d-flex align-items-center justify-content-between p-2">
              <div class="d-flex align-items-center">
                <div class="badge bg-label-success p-2 rounded me-3">
                  <i class="bx bx-trending-up fs-4"></i>
                </div>
                <div>
                  <h6 class="mb-0 fw-semibold">Penerimaan Kas (Inflow)</h6>
                  <small class="text-muted">Total Cash In</small>
                </div>
              </div>
              <div class="text-end">
                <h6 class="mb-0 fw-bold">Rp {{ number_format($cfSummary['inflow']['budget'] ?? 0, 0, ',', '.') }}</h6>
                <small class="text-success fw-medium">Real: Rp {{ number_format($cfSummary['inflow']['realization'] ?? 0, 0, ',', '.') }}</small>
              </div>
            </div>
          </div>

          <!-- Col 2: Pengeluaran Kas -->
          <div class="col-md-4 border-end">
            <div class="d-flex align-items-center justify-content-between p-2">
              <div class="d-flex align-items-center">
                <div class="badge bg-label-danger p-2 rounded me-3">
                  <i class="bx bx-trending-down fs-4"></i>
                </div>
                <div>
                  <h6 class="mb-0 fw-semibold">Pengeluaran Kas (Outflow)</h6>
                  <small class="text-muted">Total Cash Out</small>
                </div>
              </div>
              <div class="text-end">
                <h6 class="mb-0 fw-bold">Rp {{ number_format($cfSummary['outflow']['budget'] ?? 0, 0, ',', '.') }}</h6>
                <small class="text-danger fw-medium">Real: Rp {{ number_format($cfSummary['outflow']['realization'] ?? 0, 0, ',', '.') }}</small>
              </div>
            </div>
          </div>

          <!-- Col 3: Arus Kas Bersih -->
          <div class="col-md-4">
            @php
            $isNetPositive = ($cfSummary['net']['budget'] ?? 0.0) >= 0;
            @endphp
            <div class="d-flex align-items-center justify-content-between p-2">
              <div class="d-flex align-items-center">
                <div class="badge bg-label-{{ $isNetPositive ? 'primary' : 'warning' }} p-2 rounded me-3">
                  <i class="bx bx-wallet fs-4"></i>
                </div>
                <div>
                  <h6 class="mb-0 fw-bold text-{{ $isNetPositive ? 'primary' : 'warning' }}">Sisa Kas (Net Cash Flow)</h6>
                  <small class="text-muted">Net Cash Flow</small>
                </div>
              </div>
              <div class="text-end">
                <h6 class="mb-0 fw-bold text-{{ $isNetPositive ? 'primary' : 'warning' }}">Rp {{ number_format($cfSummary['net']['budget'] ?? 0, 0, ',', '.') }}</h6>
                <small class="text-{{ $isNetPositive ? 'primary' : 'warning' }} fw-medium">Real: Rp {{ number_format($cfSummary['net']['realization'] ?? 0, 0, ',', '.') }}</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Cash Flow Table --}}
  <div class="col-12">
    <div class="card h-100">
      <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <h5 class="card-title mb-0">Rincian Laporan Cash Flow (Arus Kas)</h5>
          <small class="text-muted">Akumulasi anggaran, realisasi, dan proyeksi berdasarkan pemetaan kategori Cash Flow</small>
        </div>
      </div>
      <div class="table-responsive text-nowrap">
        <table class="table table-hover table-striped-columns mb-0 align-middle table-pn-report">
          <thead>
            <tr class="table-light">
              <th>Kategori / Golongan Cash Flow</th>
              <th class="text-end">Anggaran (Budget)</th>
              <th class="text-end">Realisasi YTD</th>
              <th class="text-end">Proyeksi (Outlook)</th>
              <th class="text-end">Selisih (Variance)</th>
            </tr>
          </thead>
          <tbody>
            <!-- Group Header 1: Penerimaan Kas -->
            <tr class="table-light fw-bold text-uppercase rkap-ls-05">
              <td colspan="5">
                <i class="bx bx-plus-circle me-2 text-success"></i>Arus Kas Masuk (Penerimaan Kas)
              </td>
            </tr>

            <!-- Inflow Items -->
            @foreach ($inflowGroups as $item)
            @php
            $itemBudget = $item['budget'];
            $itemReal = $item['realization'];
            $itemProj = $item['projection'];
            $itemVariance = $itemProj - $itemBudget; // For inflow, projection higher than budget is positive variance
            @endphp
            <tr>
              <td class="ps-4">
                <i class="bx bxs-circle text-{{ $item['color'] }} me-2 rkap-font-05 rkap-v-align-middle"></i>
                <a href="#" class="cashflow-group-link text-decoration-none fw-medium"
                  data-cashflow-group-id="{{ $item['id'] }}" data-cashflow-group-name="{{ $item['name'] }}"
                  data-period-id="{{ $activePeriod->id }}">
                  {{ $item['name'] }} ({{ $item['code'] }})
                  <i class="bx bx-info-circle ms-1 text-muted rkap-font-068"></i>
                </a>
              </td>
              <td class="text-end font-monospace">Rp {{ number_format($itemBudget, 0, ',', '.') }}</td>
              <td class="text-end font-monospace">Rp {{ number_format($itemReal, 0, ',', '.') }}</td>
              <td class="text-end font-monospace">Rp {{ number_format($itemProj, 0, ',', '.') }}</td>
              <td class="text-end font-monospace">
                @if ($itemVariance > 0)
                <span class="text-success"><i class="bx bx-chevron-up me-1"></i>Rp {{ number_format($itemVariance, 0, ',', '.') }}</span>
                @elseif($itemVariance < 0)
                  <span class="text-danger"><i class="bx bx-chevron-down me-1"></i>(Rp {{ number_format(abs($itemVariance), 0, ',', '.') }})</span>
                  @else
                  <span class="text-muted">-</span>
                  @endif
              </td>
            </tr>
            @endforeach

            <!-- Inflow Subtotal Row -->
            @php
            $inflowBudget = $cfSummary['inflow']['budget'];
            $inflowReal = $cfSummary['inflow']['realization'];
            $inflowProj = $cfSummary['inflow']['projection'];
            $inflowVariance = $inflowProj - $inflowBudget;
            @endphp
            <tr class="fw-semibold bg-lighter">
              <td class="ps-3 text-secondary">
                Subtotal Arus Kas Masuk
              </td>
              <td class="text-end font-monospace">Rp {{ number_format($inflowBudget, 0, ',', '.') }}</td>
              <td class="text-end font-monospace text-success">Rp {{ number_format($inflowReal, 0, ',', '.') }}</td>
              <td class="text-end font-monospace text-warning">Rp {{ number_format($inflowProj, 0, ',', '.') }}</td>
              <td class="text-end font-monospace">
                @if ($inflowVariance > 0)
                <span class="text-success fw-semibold"><i class="bx bx-chevron-up me-1"></i>Rp {{ number_format($inflowVariance, 0, ',', '.') }}</span>
                @elseif($inflowVariance < 0)
                  <span class="text-danger fw-semibold"><i class="bx bx-chevron-down me-1"></i>(Rp {{ number_format(abs($inflowVariance), 0, ',', '.') }})</span>
                  @else
                  <span class="text-muted">-</span>
                  @endif
              </td>
            </tr>

            <!-- Group Header 2: Pengeluaran Kas -->
            <tr class="table-light fw-bold text-uppercase rkap-ls-05">
              <td colspan="5">
                <i class="bx bx-minus-circle me-2 text-danger"></i>Arus Kas Keluar (Pengeluaran Kas)
              </td>
            </tr>

            <!-- Outflow Items -->
            @foreach ($outflowGroups as $item)
            @php
            $itemBudget = $item['budget'];
            $itemReal = $item['realization'];
            $itemProj = $item['projection'];
            $itemVariance = $itemBudget - $itemProj; // For outflow, projection lower than budget is positive variance
            @endphp
            <tr>
              <td class="ps-4">
                <i class="bx bxs-circle text-{{ $item['color'] }} me-2 rkap-font-05 rkap-v-align-middle"></i>
                <a href="#" class="cashflow-group-link text-decoration-none fw-medium"
                  data-cashflow-group-id="{{ $item['id'] }}" data-cashflow-group-name="{{ $item['name'] }}"
                  data-period-id="{{ $activePeriod->id }}">
                  {{ $item['name'] }} ({{ $item['code'] }})
                  <i class="bx bx-info-circle ms-1 text-muted rkap-font-068"></i>
                </a>
              </td>
              <td class="text-end font-monospace">Rp {{ number_format($itemBudget, 0, ',', '.') }}</td>
              <td class="text-end font-monospace">Rp {{ number_format($itemReal, 0, ',', '.') }}</td>
              <td class="text-end font-monospace">Rp {{ number_format($itemProj, 0, ',', '.') }}</td>
              <td class="text-end font-monospace">
                @if ($itemVariance > 0)
                <span class="text-success"><i class="bx bx-chevron-up me-1"></i>Rp {{ number_format($itemVariance, 0, ',', '.') }}</span>
                @elseif($itemVariance < 0)
                  <span class="text-danger"><i class="bx bx-chevron-down me-1"></i>(Rp {{ number_format(abs($itemVariance), 0, ',', '.') }})</span>
                  @else
                  <span class="text-muted">-</span>
                  @endif
              </td>
            </tr>
            @endforeach

            <!-- Outflow Subtotal Row -->
            @php
            $outflowBudget = $cfSummary['outflow']['budget'];
            $outflowReal = $cfSummary['outflow']['realization'];
            $outflowProj = $cfSummary['outflow']['projection'];
            $outflowVariance = $outflowBudget - $outflowProj;
            @endphp
            <tr class="fw-semibold bg-lighter">
              <td class="ps-3 text-secondary">
                Subtotal Arus Kas Keluar
              </td>
              <td class="text-end font-monospace">Rp {{ number_format($outflowBudget, 0, ',', '.') }}</td>
              <td class="text-end font-monospace text-success">Rp {{ number_format($outflowReal, 0, ',', '.') }}</td>
              <td class="text-end font-monospace text-warning">Rp {{ number_format($outflowProj, 0, ',', '.') }}</td>
              <td class="text-end font-monospace">
                @if ($outflowVariance > 0)
                <span class="text-success fw-semibold"><i class="bx bx-chevron-up me-1"></i>Rp {{ number_format($outflowVariance, 0, ',', '.') }}</span>
                @elseif($outflowVariance < 0)
                  <span class="text-danger fw-semibold"><i class="bx bx-chevron-down me-1"></i>(Rp {{ number_format(abs($outflowVariance), 0, ',', '.') }})</span>
                  @else
                  <span class="text-muted">-</span>
                  @endif
              </td>
            </tr>

            <!-- Net Cash Flow Row -->
            @php
            $netBudget = $cfSummary['net']['budget'];
            $netReal = $cfSummary['net']['realization'];
            $netProj = $cfSummary['net']['projection'];
            $netVariance = $netProj - $netBudget;
            @endphp
            <tr class="table-primary fw-bold border-top border-2">
              <td class="text-primary rkap-font-11">
                <i class="bx bx-wallet me-2"></i>Arus Kas Bersih (Net Cash Flow)
              </td>
              <td class="text-end font-monospace rkap-font-11">Rp {{ number_format($netBudget, 0, ',', '.') }}</td>
              <td class="text-end font-monospace rkap-font-11">Rp {{ number_format($netReal, 0, ',', '.') }}</td>
              <td class="text-end font-monospace rkap-font-11">Rp {{ number_format($netProj, 0, ',', '.') }}</td>
              <td class="text-end font-monospace rkap-font-11">
                @if ($netVariance > 0)
                <span class="text-success"><i class="bx bx-chevron-up me-1"></i>Rp {{ number_format($netVariance, 0, ',', '.') }}</span>
                @elseif($netVariance < 0)
                  <span class="text-danger"><i class="bx bx-chevron-down me-1"></i>(Rp {{ number_format(abs($netVariance), 0, ',', '.') }})</span>
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

{{-- Modal: Cash Flow Group Detail --}}
<div class="modal fade" id="cashflowGroupDetailModal" tabindex="-1" aria-labelledby="cashflowGroupDetailModalLabel"
  aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header d-flex align-items-center text-white rkap-bg-kcic-red">
        <h5 class="modal-title d-flex align-items-center text-white mb-4 rkap-text-white" id="cashflowGroupDetailModalLabel">
          <i class="bx bx-detail me-2 fs-4 text-white rkap-text-white"></i>
          <span id="cashflowGroupDetailTitle" class="text-white rkap-text-white">Detail Cash Flow</span>
        </h5>
        <button type="button" class="btn-close btn-close-white m-0" data-bs-dismiss="modal"
          aria-label="Tutup"></button>
      </div>
      <hr>
      <div class="modal-body p-0">
        <div id="cashflowGroupDetailLoading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status"></div>
          <p class="text-muted mt-2 mb-0">Memuat data...</p>
        </div>
        <div id="cashflowGroupDetailError" class="alert alert-warning m-3 d-none">
          <i class="bx bx-error-circle me-1"></i> Gagal memuat data. Silakan coba lagi.
        </div>
        <div id="cashflowGroupDetailTableWrap" class="d-none">
          <table class="table table-hover table-sm table-bordered mb-0 align-middle rkap-font-075">
            <thead class="table-primary">
              <tr>
                <th class="ps-3 rkap-min-w-220">COA</th>
                <th class="rkap-min-w-220">Kegiatan</th>
                <th class="text-end text-nowrap rkap-min-w-140">Anggaran</th>
                <th class="text-end text-nowrap rkap-min-w-140">Realisasi YTD</th>
                <th class="text-end text-nowrap rkap-min-w-140">Proyeksi</th>
              </tr>
            </thead>
            <tbody id="cashflowGroupDetailTbody"></tbody>
            <tfoot id="cashflowGroupDetailTfoot" class="table-light fw-semibold"></tfoot>
          </table>
        </div>
        <p id="cashflowGroupDetailEmpty" class="text-center text-muted py-4 d-none">Tidak ada data untuk ditampilkan.</p>
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
    // Cash Flow Group Detail Modal
    const cashflowDetailModal = new bootstrap.Modal(document.getElementById('cashflowGroupDetailModal'));
    const detailTitle = document.getElementById('cashflowGroupDetailTitle');
    const detailLoading = document.getElementById('cashflowGroupDetailLoading');
    const detailError = document.getElementById('cashflowGroupDetailError');
    const detailWrap = document.getElementById('cashflowGroupDetailTableWrap');
    const detailTbody = document.getElementById('cashflowGroupDetailTbody');
    const detailTfoot = document.getElementById('cashflowGroupDetailTfoot');
    const detailEmpty = document.getElementById('cashflowGroupDetailEmpty');

    function formatRp(val) {
      const num = parseFloat(val) || 0;
      return 'Rp ' + num.toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      });
    }

    document.querySelectorAll('.cashflow-group-link').forEach(function(link) {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        const cgId = this.dataset.cashflowGroupId;
        const cgName = this.dataset.cashflowGroupName;
        const periodId = this.dataset.periodId;

        detailTitle.textContent = cgName;
        detailLoading.classList.remove('d-none');
        detailError.classList.add('d-none');
        detailWrap.classList.add('d-none');
        detailEmpty.classList.add('d-none');
        detailTbody.innerHTML = '';
        detailTfoot.innerHTML = '';

        cashflowDetailModal.show();

        fetch(`/analytics/cashflow-group-detail?cashflow_group_id=${cgId}&period_id=${periodId}`, {
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