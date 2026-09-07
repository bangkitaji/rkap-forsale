@extends('layouts/contentNavbarLayout')

@section('title', __('Laporan - Capex'))

@section('vendor-style')
@vite('resources/assets/vendor/libs/apex-charts/apex-charts.scss')
<style>
  .search-input-group {
    max-width: 350px;
  }
  .table-responsive {
    max-height: 500px;
  }
  .card-hover-effect {
    transition: all 0.3s ease;
  }
  .card-hover-effect:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
  }
</style>
@endsection

@section('vendor-script')
@vite('resources/assets/vendor/libs/apex-charts/apexcharts.js')
@endsection

@section('content')
<div class="py-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
  <div>
    <h4 class="mb-1"><span class="text-muted fw-light">{{ __('RKAP') }} /</span> {{ __('Laporan Capex') }}</h4>
    @if ($activePeriod)
    <p class="text-muted mb-0">{{ __('Menampilkan Laporan Belanja Modal (Capex) untuk periode:') }} <strong>{{ $activePeriod->title }}</strong>
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
    <form action="{{ route('analytics-capex') }}" method="GET" id="periodForm" class="m-0">
      <select name="period_id" id="periodSelect"
        class="form-select form-select-sm border-0 fw-semibold text-primary cursor-pointer focus-ring-none rkap-font-09"
        onchange="this.form.submit()">
        @if ($activePeriod && !$finalizedPeriods->contains('id', $activePeriod->id))
        <option value="" disabled selected>
          -- {{ __('Pilih Periode') }} ({{ __('Saat ini') }}: {{ $activePeriod->title }}) --
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
<!-- KPI Summary Cards -->
<div class="row g-4 mb-4">
  <!-- Card 1: Total Budget -->
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 shadow-none border card-hover-effect">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold text-muted">{{ __('Anggaran Capex') }}</span>
          <span class="badge bg-label-primary rounded p-2"><i class="bx bx-wallet fs-4"></i></span>
        </div>
        <h4 class="mb-1 fw-bold">Rp {{ number_format($stats['total_budget'], 0, ',', '.') }}</h4>
        <p class="mb-0 text-muted small">{{ __('Pagu Alokasi Capex') }}</p>
      </div>
    </div>
  </div>

  <!-- Card 2: Realisasi YTD -->
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 shadow-none border card-hover-effect">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold text-muted">{{ __('Realisasi Capex YTD') }}</span>
          <span class="badge bg-label-success rounded p-2"><i class="bx bx-check-circle fs-4"></i></span>
        </div>
        <h4 class="mb-1 fw-bold text-success">Rp {{ number_format($stats['total_realization'], 0, ',', '.') }}</h4>
        <p class="mb-0 text-muted small">{{ __('Penyerapan:') }} <strong>{{ $stats['absorption_rate'] }}%</strong></p>
      </div>
    </div>
  </div>

  <!-- Card 3: Proyeksi Akhir Tahun -->
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 shadow-none border card-hover-effect">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold text-muted">{{ __('Proyeksi Akhir Tahun') }}</span>
          <span class="badge bg-label-warning rounded p-2"><i class="bx bx-calculator fs-4"></i></span>
        </div>
        <h4 class="mb-1 fw-bold text-warning">Rp {{ number_format($stats['total_projection'], 0, ',', '.') }}</h4>
        <p class="mb-0 text-muted small">{{ __('Outlook Rate:') }} <strong>{{ $stats['outlook_rate'] }}%</strong></p>
      </div>
    </div>
  </div>

  <!-- Card 4: Sisa Alokasi / Deviasi -->
  @php
    $isOverBudget = $stats['variance'] < 0;
  @endphp
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 shadow-none border card-hover-effect">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold text-muted">{{ __('Sisa Pagu Capex') }}</span>
          <span class="badge bg-label-{{ $isOverBudget ? 'danger' : 'info' }} rounded p-2">
            <i class="bx bx-{{ $isOverBudget ? 'trending-up' : 'trending-down' }} fs-4"></i>
          </span>
        </div>
        <h4 class="mb-1 fw-bold text-{{ $isOverBudget ? 'danger' : 'info' }}">
          Rp {{ number_format(abs($stats['variance']), 0, ',', '.') }}
        </h4>
        <p class="mb-0 text-muted small">{{ $isOverBudget ? __('Melebihi Anggaran') : __('Sisa Alokasi Pagu') }}</p>
      </div>
    </div>
  </div>
</div>

<!-- Visualization & ApexCharts -->
<div class="row g-4 mb-4">
  <!-- Bar Chart: Capex by COA Group -->
  <div class="col-lg-8 col-12">
    <div class="card h-100">
      <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <h5 class="card-title mb-0">{{ __('Komparasi Capex per Golongan COA') }}</h5>
          <small class="text-muted">{{ __('Perbandingan Anggaran, Realisasi YTD, dan Proyeksi Akhir Tahun per Kelompok') }}</small>
        </div>
      </div>
      <div class="card-body pt-3">
        @if(count($coaGroupSummary) > 0)
        <div id="capexBarChart" style="min-height: 330px;"></div>
        @else
        <div class="text-center py-5 text-muted">
          <i class="bx bx-info-circle fs-3 d-block mb-2"></i>
          {{ __('Tidak ada data kelompok COA yang terpetakan untuk periode ini.') }}
        </div>
        @endif
      </div>
    </div>
  </div>

  <!-- Radial Bar Chart: Capex Absorption -->
  <div class="col-lg-4 col-12">
    <div class="card h-100">
      <div class="card-header border-bottom py-3">
        <h5 class="card-title mb-0">{{ __('Rasio Penyerapan Capex') }}</h5>
        <small class="text-muted">{{ __('Rasio Realisasi Terhadap Total Anggaran') }}</small>
      </div>
      <div class="card-body d-flex flex-column align-items-center justify-content-center pt-3">
        <div id="capexAbsorptionGauge"></div>
      </div>
    </div>
  </div>
</div>

<!-- Group Summary Table -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header border-bottom py-3">
        <h5 class="card-title mb-0">{{ __('Ringkasan Capex Berdasarkan Golongan Akun') }}</h5>
        <small class="text-muted">{{ __('Rekapitulasi total anggaran dan realisasi belanja modal berdasarkan golongan COA') }}</small>
      </div>
      <div class="table-responsive text-nowrap">
        <table class="table table-hover table-striped">
          <thead>
            <tr>
              <th>{{ __('Kode') }}</th>
              <th>{{ __('Golongan Akun (COA Group)') }}</th>
              <th class="text-end">{{ __('Anggaran (Budget)') }}</th>
              <th class="text-end">{{ __('Realisasi (YTD)') }}</th>
              <th class="text-end">{{ __('Proyeksi (Outlook)') }}</th>
              <th class="text-end">{{ __('Selisih / Sisa Pagu') }}</th>
              <th class="text-center">{{ __('Penyerapan') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($coaGroupSummary as $cg)
            @php
              $cgVariance = $cg['budget'] - $cg['projection'];
              $isCgOver = $cgVariance < 0;
            @endphp
            <tr>
              <td><span class="badge bg-label-secondary font-monospace">{{ $cg['code'] }}</span></td>
              <td class="fw-semibold">{{ $cg['name'] }}</td>
              <td class="text-end font-monospace">Rp {{ number_format($cg['budget'], 0, ',', '.') }}</td>
              <td class="text-end font-monospace text-success">Rp {{ number_format($cg['realization'], 0, ',', '.') }}</td>
              <td class="text-end font-monospace text-warning">Rp {{ number_format($cg['projection'], 0, ',', '.') }}</td>
              <td class="text-end font-monospace">
                @if ($cgVariance > 0)
                <span class="text-success"><i class="bx bx-chevron-down me-1"></i>Rp {{ number_format($cgVariance, 0, ',', '.') }}</span>
                @elseif ($cgVariance < 0)
                <span class="text-danger"><i class="bx bx-chevron-up me-1"></i>(Rp {{ number_format(abs($cgVariance), 0, ',', '.') }})</span>
                @else
                <span class="text-muted">-</span>
                @endif
              </td>
              <td class="text-center font-monospace fw-bold">{{ $cg['absorption_rate'] }}%</td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">
                <i class="bx bx-info-circle me-1"></i>{{ __('Tidak ada data ringkasan golongan COA untuk periode ini.') }}
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Detailed COA Table -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
          <h5 class="card-title mb-0">{{ __('Rincian Detail Akun COA Capex') }}</h5>
          <small class="text-muted">{{ __('Rincian dari 74 akun aset belanja modal (Capex)') }}</small>
        </div>
        <!-- Search & Filter Controls -->
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <div class="form-check form-switch mb-0">
            <input class="form-check-input cursor-pointer" type="checkbox" id="toggleActiveCoa">
            <label class="form-check-label cursor-pointer text-muted fw-semibold rkap-font-09" for="toggleActiveCoa">
              {{ __('Tampilkan Hanya yang Aktif') }}
            </label>
          </div>
          <div class="input-group input-group-merge search-input-group">
            <span class="input-group-text"><i class="bx bx-search fs-4 text-muted"></i></span>
            <input type="text" id="searchCoaInput" class="form-control form-control-sm" placeholder="{{ __('Cari kode atau nama akun...') }}">
          </div>
        </div>
      </div>
      <div class="table-responsive text-nowrap">
        <table class="table table-hover table-striped" id="detailedCoaTable">
          <thead>
            <tr>
              <th>{{ __('Akun COA') }}</th>
              <th>{{ __('Golongan (COA Group)') }}</th>
              <th class="text-end">{{ __('Anggaran') }}</th>
              <th class="text-end">{{ __('Realisasi YTD') }}</th>
              <th class="text-end">{{ __('Proyeksi') }}</th>
              <th class="text-end">{{ __('Selisih / Sisa') }}</th>
              <th class="text-center">{{ __('Penyerapan') }}</th>
            </tr>
          </thead>
          <tbody class="table-border-bottom-0">
            @forelse ($detailedCoas as $coa)
            @php
              $coaVariance = $coa['budget'] - $coa['projection'];
              $hasActivity = ($coa['budget'] > 0 || $coa['realization'] > 0 || $coa['projection'] > 0);
            @endphp
            <tr data-has-activity="{{ $hasActivity ? '1' : '0' }}">
              <td>
                <a href="javascript:void(0);" class="coa-detail-link text-decoration-none" data-coa-code="{{ $coa['code'] }}" data-coa-title="{{ $coa['title'] }}">
                  <span class="badge bg-label-primary font-monospace search-target mb-1 cursor-pointer">{{ $coa['code'] }}</span>
                  <div class="fw-semibold text-wrap rkap-mw-300 search-target text-primary">{{ $coa['title'] }}</div>
                </a>
              </td>
              <td class="text-muted small search-target">{{ $coa['group_name'] }}</td>
              <td class="text-end font-monospace">Rp {{ number_format($coa['budget'], 0, ',', '.') }}</td>
              <td class="text-end font-monospace text-success">Rp {{ number_format($coa['realization'], 0, ',', '.') }}</td>
              <td class="text-end font-monospace text-warning">Rp {{ number_format($coa['projection'], 0, ',', '.') }}</td>
              <td class="text-end font-monospace">
                @if ($coaVariance > 0)
                <span class="text-success">Rp {{ number_format($coaVariance, 0, ',', '.') }}</span>
                @elseif ($coaVariance < 0)
                <span class="text-danger">(Rp {{ number_format(abs($coaVariance), 0, ',', '.') }})</span>
                @else
                <span class="text-muted">-</span>
                @endif
              </td>
              <td class="text-center font-monospace fw-bold">{{ $coa['absorption_rate'] }}%</td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">
                <i class="bx bx-info-circle me-1"></i>{{ __('Tidak ada rincian akun COA untuk periode ini.') }}
              </td>
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
          <h5 class="modal-title mb-0" id="modalTitle">{{ __('Detail Kegiatan COA') }}</h5>
          <small class="text-muted" id="modalSubTitle">{{ __('Memuat rincian kegiatan...') }}</small>
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
                <th>{{ __('Kode & Nama Program') }}</th>
                <th>{{ __('Organisasi (Dir-Dept-Biro)') }}</th>
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

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // 1. Bar Chart Visualization using ApexCharts
    const groupSummary = @js($coaGroupSummary);
    if (groupSummary && groupSummary.length > 0) {
      const categories = groupSummary.map(item => item.name);
      const budgets = groupSummary.map(item => item.budget);
      const realizations = groupSummary.map(item => item.realization);
      const projections = groupSummary.map(item => item.projection);

      const barChartOptions = {
        series: [
          { name: "{{ __('Anggaran (Budget)') }}", data: budgets },
          { name: "{{ __('Realisasi YTD') }}", data: realizations },
          { name: "{{ __('Proyeksi (Outlook)') }}", data: projections }
        ],
        chart: {
          type: 'bar',
          height: 330,
          toolbar: { show: false }
        },
        plotOptions: {
          bar: {
            horizontal: false,
            columnWidth: '55%',
            borderRadius: 4,
            endingShape: 'rounded'
          }
        },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        colors: ['{{ \App\Helpers\BrandHelper::themeDark() }}', '{{ \App\Helpers\BrandHelper::themePrimary() }}', '#ffab00'],
        xaxis: {
          categories: categories,
          labels: {
            rotate: -15,
            rotateAlways: false,
            style: { fontSize: '11px' }
          }
        },
        yaxis: {
          labels: {
            formatter: function(val) {
              if (val >= 1e9) return 'Rp ' + (val / 1e9).toFixed(1) + ' M';
              if (val >= 1e6) return 'Rp ' + (val / 1e6).toFixed(0) + ' Jt';
              return 'Rp ' + val.toLocaleString('id-ID');
            }
          }
        },
        fill: { opacity: 1 },
        tooltip: {
          y: {
            formatter: function(val) {
              return 'Rp ' + val.toLocaleString('id-ID');
            }
          }
        },
        legend: {
          position: 'top',
          horizontalAlign: 'center',
          offsetY: -5
        }
      };

      const barChart = new ApexCharts(document.querySelector("#capexBarChart"), barChartOptions);
      barChart.render();
    }

    // 2. Radial Bar Chart Absorption Gauge
    const absorptionRate = @js($stats['absorption_rate'] ?? 0);
    const radialChartOptions = {
      series: [absorptionRate],
      chart: {
        type: 'radialBar',
        height: 310,
        sparkline: { enabled: true }
      },
      plotOptions: {
        radialBar: {
          startAngle: -135,
          endAngle: 135,
          hollow: { size: '70%' },
          track: {
            background: '#e7e7e7',
            strokeWidth: '97%',
            margin: 5
          },
          dataLabels: {
            name: {
              show: true,
              color: '#566a7f',
              offsetY: 20,
              fontSize: '14px',
              fontWeight: '600'
            },
            value: {
              offsetY: -20,
              fontSize: '26px',
              fontWeight: '700',
              color: '#566a7f',
              formatter: function(val) { return val + '%'; }
            }
          }
        }
      },
      fill: {
        type: 'gradient',
        gradient: {
          shade: 'dark',
          type: 'horizontal',
          shadeIntensity: 0.5,
          gradientToColors: ['#71dd37'],
          inverseColors: true,
          opacityFrom: 1,
          opacityTo: 1,
          stops: [0, 100]
        }
      },
      colors: ['{{ \App\Helpers\BrandHelper::themePrimary() }}'],
      stroke: { dashArray: 4 },
      labels: ["{{ __('Tingkat Penyerapan') }}"]
    };

    const radialChart = new ApexCharts(document.querySelector("#capexAbsorptionGauge"), radialChartOptions);
    radialChart.render();

    // 3. Search and Toggle Filtering logic
    const searchInput = document.getElementById('searchCoaInput');
    const toggleActive = document.getElementById('toggleActiveCoa');
    const tableBody = document.querySelector('#detailedCoaTable tbody');
    const tableRows = tableBody.querySelectorAll('tr');

    function filterTable() {
      const query = searchInput.value.toLowerCase().trim();
      const showOnlyActive = toggleActive.checked;

      let visibleCount = 0;

      tableRows.forEach(row => {
        if (row.cells.length === 1 && row.cells[0].colSpan === 7) {
          // Skip empty row placeholder
          return;
        }

        const isRowActive = row.getAttribute('data-has-activity') === '1';
        
        // Match Search query
        let matchQuery = false;
        const targets = row.querySelectorAll('.search-target');
        targets.forEach(t => {
          if (t.textContent.toLowerCase().includes(query)) {
            matchQuery = true;
          }
        });

        // Combined filter check
        if (matchQuery && (!showOnlyActive || isRowActive)) {
          row.classList.remove('d-none');
          visibleCount++;
        } else {
          row.classList.add('d-none');
        }
      });

      // Show placeholder if no rows visible
      const existingPlaceholder = document.getElementById('noResultsPlaceholder');
      if (existingPlaceholder) {
        existingPlaceholder.remove();
      }

      if (visibleCount === 0) {
        const tr = document.createElement('tr');
        tr.id = 'noResultsPlaceholder';
        tr.innerHTML = `<td colspan="7" class="text-center py-4 text-muted"><i class="bx bx-search me-1"></i>{{ __('Tidak ada akun COA yang cocok dengan pencarian / filter.') }}</td>`;
        tableBody.appendChild(tr);
      }
    }

    searchInput.addEventListener('input', filterTable);
    toggleActive.addEventListener('change', filterTable);

    // 4. AJAX Modal Detail Handler for single COA
    const coaLinks = document.querySelectorAll('.coa-detail-link');
    const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
    const modalTitle = document.getElementById('modalTitle');
    const modalSubTitle = document.getElementById('modalSubTitle');
    const modalLoading = document.getElementById('modalLoading');
    const modalTableContainer = document.getElementById('modalTableContainer');
    const modalTableBody = document.getElementById('modalTableBody');

    coaLinks.forEach(link => {
      link.addEventListener('click', function() {
        const coaCode = this.getAttribute('data-coa-code');
        const coaTitle = this.getAttribute('data-coa-title');
        const periodId = "{{ $activePeriod->id }}";

        // Set modal header titles
        modalTitle.textContent = coaCode + ' - ' + coaTitle;
        modalSubTitle.textContent = "{{ __('Mengambil daftar kegiatan...') }}";

        // Toggle views to loading state
        modalLoading.classList.remove('d-none');
        modalTableContainer.classList.add('d-none');
        modalTableBody.innerHTML = '';

        detailModal.show();

        // Fetch AJAX data
        fetch(`/analytics/coa-detail?coa_code=${coaCode}&period_id=${periodId}`)
          .then(res => res.json())
          .then(response => {
            modalLoading.classList.add('d-none');
            modalTableContainer.classList.remove('d-none');

            const data = response.data || [];
            modalSubTitle.textContent = `Ditemukan ${data.length} item rincian kegiatan`;

            if (data.length === 0) {
              modalTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">' + @js(__('Tidak ada rincian kegiatan yang disetujui.')) + '</td></tr>';
              return;
            }

            let html = '';
            data.forEach(row => {
              html += `
                    <tr>
                      <td>
                        <span class="text-primary fw-medium font-monospace small">${row.program_code}</span>
                        <div class="text-wrap small text-muted mt-1 rkap-mw-350">${row.program_name}</div>
                      </td>
                      <td>
                        <div class="small fw-semibold text-secondary">
                          ${row.directorate_code || '-'} - ${row.department_code || '-'} - ${row.bureau_code || '-'}
                        </div>
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
@endsection
@endif
@endsection
