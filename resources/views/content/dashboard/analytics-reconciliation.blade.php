@extends('layouts/contentNavbarLayout')

@section('title', __('Laporan - Rekonsiliasi Kas'))

@section('content')
<div class="py-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
  <div>
    <h4 class="mb-1"><span class="text-muted fw-light">{{ __('RKAP') }} /</span> {{ __('Laporan Rekonsiliasi Kas') }}</h4>
    @if ($activePeriod)
    <p class="text-muted mb-0">{{ __('Menampilkan Laporan Rekonsiliasi Kas (P&L vs Difference Groups) untuk periode:') }} <strong>{{ $activePeriod->title }}</strong>
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
    <form action="{{ route('analytics-reconciliation') }}" method="GET" id="periodForm" class="m-0">
      <select name="period_id" id="periodSelect"
        class="form-select form-select-sm border-0 fw-semibold text-primary cursor-pointer focus-ring-none rkap-font-09 rkap-select-custom"
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
@php
$totalDiffBudget = collect($reconciliationItems)->sum('budget');
$totalDiffReal = collect($reconciliationItems)->sum('realization');
$totalDiffProj = collect($reconciliationItems)->sum('projection');

$netProfitBudget = $plSummary['net_profit']['budget'] ?? 0.0;
$netProfitReal = $plSummary['net_profit']['realization'] ?? 0.0;
$netProfitProj = $plSummary['net_profit']['projection'] ?? 0.0;
@endphp

<div class="row g-4 mb-4">
  <!-- Card 1: Net Profit -->
  <div class="col-sm-6 col-xl-4">
    <div class="card h-100 shadow-none border">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold text-muted">{{ __('Laba Bersih (P&L)') }}</span>
          <span class="badge bg-label-success rounded p-2"><i class="bx bx-dollar fs-4"></i></span>
        </div>
        <h4 class="mb-1 fw-bold">Rp {{ number_format($netProfitBudget, 0, ',', '.') }}</h4>
        <small class="text-success fw-medium">Real: Rp {{ number_format($netProfitReal, 0, ',', '.') }}</small>
        <p class="mb-0 text-muted small mt-2">{{ __('Berdasarkan Laporan Laba Rugi Akrual') }}</p>
      </div>
    </div>
  </div>
  <!-- Card 2: {{ __('Total Pos Rekonsiliasi') }} -->
  <div class="col-sm-6 col-xl-4">
    <div class="card h-100 shadow-none border">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold text-muted">{{ __('Total Pos Rekonsiliasi') }}</span>
          <span class="badge bg-label-info rounded p-2"><i class="bx bx-git-commit fs-4"></i></span>
        </div>
        <h4 class="mb-1 fw-bold">Rp {{ number_format($totalDiffBudget, 0, ',', '.') }}</h4>
        <small class="text-info fw-medium">Real: Rp {{ number_format($totalDiffReal, 0, ',', '.') }}</small>
        <p class="mb-0 text-muted small mt-2">{{ __('Total Selisih dari Difference Groups') }}</p>
      </div>
    </div>
  </div>
  <!-- Card 3: Perkiraan Arus Kas -->
  <div class="col-sm-6 col-xl-4">
    <div class="card h-100 shadow-none border">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold text-muted">{{ __('Perkiraan Kas Bersih') }}</span>
          <span class="badge bg-label-warning rounded p-2"><i class="bx bx-wallet fs-4"></i></span>
        </div>
        <h4 class="mb-1 fw-bold">Rp {{ number_format($netProfitBudget + $totalDiffBudget, 0, ',', '.') }}</h4>
        <small class="text-warning fw-medium">Real: Rp {{ number_format($netProfitReal + $totalDiffReal, 0, ',', '.') }}</small>
        <p class="mb-0 text-muted small mt-2">{{ __('Estimasi Kas (Laba Bersih + Selisih Pos)') }}</p>
      </div>
    </div>
  </div>
</div>

<!-- Chart Row -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card h-100">
      <div class="card-header border-bottom py-3">
        <h5 class="card-title mb-0">{{ __('Komparasi Anggaran per Pos Rekonsiliasi') }}</h5>
        <small class="text-muted">{{ __('Perbandingan Anggaran, Realisasi YTD, dan Proyeksi Akhir Tahun per Difference Group') }}</small>
      </div>
      <div class="card-body pt-3">
        @if(count($reconciliationItems) > 0)
        <div id="reconciliationChart" class="rkap-mh-380"></div>
        @else
        <div class="text-center py-5 text-muted">
          <i class="bx bx-info-circle fs-3 d-block mb-2"></i>
          {{ __('Tidak ada data Difference Group yang terpetakan untuk periode ini.') }}
        </div>
        @endif
      </div>
    </div>
  </div>
</div>

<!-- Table Row -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header border-bottom py-3">
        <h5 class="card-title mb-0">{{ __('Detail Pos Rekonsiliasi Kas') }}</h5>
        <small class="text-muted">{{ __('Daftar Penyesuaian Arus Kas Berdasarkan Difference Group') }}</small>
      </div>
      <div class="table-responsive text-nowrap">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>{{ __('Kode') }}</th>
              <th>{{ __('Pos Rekonsiliasi (Difference Group)') }}</th>
              <th class="text-end">{{ __('Anggaran') }}</th>
              <th class="text-end">{{ __('Realisasi YTD') }}</th>
              <th class="text-end">{{ __('Proyeksi Akhir Tahun') }}</th>
              <th class="text-center">{{ __('Aksi') }}</th>
            </tr>
          </thead>
          <tbody class="table-border-bottom-0">
            @forelse ($reconciliationItems as $item)
            <tr>
              <td><span class="badge bg-label-secondary">{{ $item['code'] }}</span></td>
              <td class="fw-semibold">{{ $item['name'] }}</td>
              <td class="text-end fw-bold">Rp {{ number_format($item['budget'], 0, ',', '.') }}</td>
              <td class="text-end text-success">Rp {{ number_format($item['realization'], 0, ',', '.') }}</td>
              <td class="text-end text-warning">Rp {{ number_format($item['projection'], 0, ',', '.') }}</td>
              <td class="text-center">
                <button type="button" class="btn btn-xs btn-outline-primary btn-detail"
                  data-id="{{ $item['id'] }}" data-name="{{ $item['name'] }}">
                  <i class="bx bx-search-alt me-1"></i> {{ __('Detail') }}
                </button>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="6" class="text-center py-4 text-muted">{{ __('Tidak ada data pos rekonsiliasi yang terpetakan untuk periode ini.') }}</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Drill-down Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header border-bottom py-3">
        <div>
          <h5 class="modal-title mb-0" id="modalTitle">{{ __('Detail Item Pos Rekonsiliasi') }}</h5>
          <small class="text-muted" id="modalSubTitle">{{ __('Memuat rincian COA...') }}</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div id="modalLoading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">{{ __('Loading...') }}</span>
          </div>
          <p class="text-muted mt-2 small">{{ __('Mengambil rincian anggaran...') }}</p>
        </div>
        <div class="table-responsive text-nowrap d-none" id="modalTableContainer">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Akun COA') }}</th>
                <th>{{ __('Program & Organisasi') }}</th>
                <th class="text-end">{{ __('Anggaran') }}</th>
                <th class="text-end">{{ __('Realisasi YTD') }}</th>
                <th class="text-end">{{ __('Proyeksi') }}</th>
              </tr>
            </thead>
            <tbody id="modalTableBody">
              <!-- AJAX lines here -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer border-top py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
      </div>
    </div>
  </div>
</div>

@endif
@endsection

@section('page-script')
@if ($activePeriod && count($reconciliationItems) > 0)
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // 1. ApexCharts setup
    const reconciliationData = @json($reconciliationItems);
    const categories = reconciliationData.map(item => item.name);
    const budgets = reconciliationData.map(item => item.budget);
    const realizations = reconciliationData.map(item => item.realization);
    const projections = reconciliationData.map(item => item.projection);

    const chartOptions = {
      series: [{
          name: @js(__('Anggaran')),
          data: budgets
        },
        {
          name: @js(__('Realisasi YTD')),
          data: realizations
        },
        {
          name: @js(__('Proyeksi Akhir Tahun')),
          data: projections
        }
      ],
      chart: {
        type: 'bar',
        height: Math.max(380, categories.length * 45),
        toolbar: {
          show: false
        }
      },
      plotOptions: {
        bar: {
          horizontal: true,
          barHeight: '70%',
          borderRadius: 3,
          dataLabels: {
            position: 'top'
          }
        }
      },
      dataLabels: {
        enabled: false
      },
      colors: ['#696cff', '#71dd37', '#ffab00'],
      xaxis: {
        categories: categories,
        labels: {
          formatter: function(value) {
            if (value >= 1e9) return 'Rp ' + (value / 1e9).toFixed(1) + ' M';
            if (value >= 1e6) return 'Rp ' + (value / 1e6).toFixed(0) + ' Jt';
            return 'Rp ' + value.toLocaleString('id-ID');
          }
        }
      },
      yaxis: {
        labels: {
          maxWidth: 220,
          style: {
            fontSize: '11px'
          }
        }
      },
      tooltip: {
        y: {
          formatter: function(val) {
            return 'Rp ' + val.toLocaleString('id-ID');
          }
        }
      },
      legend: {
        position: 'top',
        horizontalAlign: 'left'
      }
    };

    const chart = new ApexCharts(document.querySelector("#reconciliationChart"), chartOptions);
    chart.render();

    // 2. AJAX Modal Detail Handler
    const detailButtons = document.querySelectorAll('.btn-detail');
    const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
    const modalTitle = document.getElementById('modalTitle');
    const modalSubTitle = document.getElementById('modalSubTitle');
    const modalLoading = document.getElementById('modalLoading');
    const modalTableContainer = document.getElementById('modalTableContainer');
    const modalTableBody = document.getElementById('modalTableBody');

    detailButtons.forEach(btn => {
      btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const name = this.getAttribute('data-name');
        const periodId = "{{ $activePeriod->id }}";

        // Set modal header titles
        modalTitle.textContent = name;
        modalSubTitle.textContent = "Mengambil daftar COA untuk pos rekonsiliasi...";

        // Toggle views to loading state
        modalLoading.classList.remove('d-none');
        modalTableContainer.classList.add('d-none');
        modalTableBody.innerHTML = '';

        detailModal.show();

        // Fetch AJAX data
        fetch(`/analytics/difference-group-detail?difference_group_id=${id}&period_id=${periodId}`)
          .then(res => res.json())
          .then(response => {
            modalLoading.classList.add('d-none');
            modalTableContainer.classList.remove('d-none');

            const data = response.data || [];
            modalSubTitle.textContent = `Ditemukan ${data.length} item rincian COA`;

            if (data.length === 0) {
              modalTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">' + @js(__('Tidak ada rincian transaksi COA yang disetujui.')) + '</td></tr>';
              return;
            }

            let html = '';
            data.forEach(row => {
              html += `
                    <tr>
                      <td>
                        <span class="badge bg-label-dark">${row.coa_code}</span>
                        <div class="fw-semibold text-wrap text-muted small mt-1 rkap-mw-250">${row.coa_title}</div>
                      </td>
                      <td>
                        <div class="small fw-semibold text-secondary mb-1">
                          ${row.directorate_code || '-'} - ${row.department_code || '-'} - ${row.bureau_code || '-'}
                        </div>
                        <span class="text-primary fw-medium font-monospace small">${row.program_code}</span>
                        <div class="text-wrap small text-muted mt-1 rkap-mw-350">${row.program_name}</div>
                      </td>
                      <td class="text-end fw-semibold">Rp ${parseFloat(row.budget).toLocaleString('id-ID')}</td>
                      <td class="text-end text-success">Rp ${parseFloat(row.realization).toLocaleString('id-ID')}</td>
                      <td class="text-end text-warning">Rp ${parseFloat(row.projection).toLocaleString('id-ID')}</td>
                    </tr>
                  `;
            });
            modalTableBody.innerHTML = html;
          })
          .catch(err => {
            modalLoading.classList.add('d-none');
            modalTableContainer.classList.remove('d-none');
            modalSubTitle.textContent = @js(__('Gagal mengambil data.'));
            modalTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4"><i class="bx bx-error me-1"></i> ' + @js(__('Terjadi kesalahan saat memuat data. Silakan coba kembali.')) + '</td></tr>';
          });
      });
    });
  });
</script>
@endif
@endsection