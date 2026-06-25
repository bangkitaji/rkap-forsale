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
    .btn-check:checked+.btn-outline-primary {
      color: #fff !important;
      background-color: #696cff !important;
      border-color: #696cff !important;
    }
  </style>
  <div class="py-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
      <h4 class="mb-1"><span class="text-muted fw-light">RKAP /</span> Analytics</h4>
      @if ($activePeriod)
        <p class="text-muted mb-0">Menampilkan visualisasi data untuk periode: <strong>{{ $activePeriod->title }}</strong>
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
        <form action="{{ route('dashboard-analytics') }}" method="GET" id="periodForm" class="m-0">
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
              <span class="badge bg-label-{{ $isOver ? 'danger' : 'info' }} rounded p-2"><i
                  class="bx bx-pie-chart-alt fs-4"></i></span>
            </div>
            <h4 class="mb-1 fw-bold text-{{ $isOver ? 'danger' : 'info' }}">Rp
              {{ number_format(abs($variance), 0, ',', '.') }}
            </h4>
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
            <small class="text-muted">Perbandingan Anggaran, Realisasi YTD, dan Proyeksi Akhir Tahun (Tahun Lalu, Tahun
              Berjalan, dan Tahun Depan)</small>
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
      <div class="col-12">
        <div class="card h-100">
          <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <h5 class="card-title mb-0">Penyerapan Anggaran</h5>
              <small class="text-muted">Komparasi Penyerapan per Unit Kerja</small>
            </div>
            <div class="btn-group" role="group" aria-label="Comparative data options">
              @if (auth()->user()->isKepalaDepartemen())
                <input type="radio" class="btn-check" name="btnComparativeGroup" id="groupDepartment" checked
                  autocomplete="off">
                <label class="btn btn-outline-primary btn-sm px-3" for="groupDepartment">Departemen</label>

                <input type="radio" class="btn-check" name="btnComparativeGroup" id="groupBureau"
                  autocomplete="off">
                <label class="btn btn-outline-primary btn-sm px-3" for="groupBureau">Biro</label>
              @else
                <input type="radio" class="btn-check" name="btnComparativeGroup" id="groupDirectorate" checked
                  autocomplete="off">
                <label class="btn btn-outline-primary btn-sm px-3" for="groupDirectorate">Direktorat</label>

                <input type="radio" class="btn-check" name="btnComparativeGroup" id="groupDepartment"
                  autocomplete="off">
                <label class="btn btn-outline-primary btn-sm px-3" for="groupDepartment">Departemen</label>
              @endif
            </div>
          </div>
          <div class="card-body pt-3">
            <div id="divisionChart" style="min-height: 350px;"></div>
          </div>
        </div>
      </div>

    </div>


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
  @if ($activePeriod)
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // 1. Burn-Up Chart Setup
        const burnUpChartOptions = {
          series: [{
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
            toolbar: {
              show: false
            }
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
            axisBorder: {
              show: false
            },
            axisTicks: {
              show: false
            }
          },
          yaxis: {
            labels: {
              formatter: function(value) {
                if (value >= 1e9) return 'Rp ' + (value / 1e9).toFixed(1) + ' M';
                if (value >= 1e6) return 'Rp ' + (value / 1e6).toFixed(0) + ' Jt';
                return 'Rp ' + value.toLocaleString('id-ID');
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
                  formatter: function(val) {
                    return val + '%';
                  }
                },
                total: {
                  show: true,
                  label: 'Realisasi YTD',
                  formatter: function(w) {
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
            labels: {
              useSeriesColors: true
            }
          }
        };
        setTimeout(() => {
          const utilizationGauge = new ApexCharts(document.querySelector("#utilizationGauge"),
            utilizationGaugeOptions);
          utilizationGauge.render();
        }, 100);

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
            series: [{
                name: 'Anggaran',
                data: budgets
              },
              {
                name: 'Realisasi YTD',
                data: realizations
              },
              {
                name: 'Proyeksi Akhir Tahun',
                data: projections
              }
            ],
            chart: {
              type: 'bar',
              height: calcChartHeight(data.length),
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
            colors: ['#1a1f5e', '#960b10ff', '#ffab00'],
            xaxis: {
              categories: labels,
              labels: {
                formatter: function(value) {
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
        }

        // Create initial chart with appropriate data based on role
        const isKadept = @json(auth()->user()->isKepalaDepartemen());
        let divisionChart = null;
        setTimeout(() => {
          divisionChart = new ApexCharts(
            document.querySelector("#divisionChart"),
            buildDivisionChartOptions(isKadept ? departmentData : directorateData)
          );
          divisionChart.render();
        }, 200);

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
          if (divisionChart) {
            divisionChart.destroy();
          }
          divisionChart = new ApexCharts(
            document.querySelector("#divisionChart"),
            buildDivisionChartOptions(data)
          );
          divisionChart.render();
        }

        if (isKadept) {
          document.getElementById('groupDepartment').addEventListener('change', function() {
            if (this.checked) switchDivisionChart('department');
          });
          document.getElementById('groupBureau').addEventListener('change', function() {
            if (this.checked) switchDivisionChart('bureau');
          });
        } else {
          document.getElementById('groupDirectorate').addEventListener('change', function() {
            if (this.checked) switchDivisionChart('directorate');
          });
          document.getElementById('groupDepartment').addEventListener('change', function() {
            if (this.checked) switchDivisionChart('department');
          });
        }

        // 4. Annual Comparison Chart Setup
        const annualComparisonChartOptions = {
          series: [{
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
            toolbar: {
              show: false
            }
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
          colors: ['#1a1f5e', '#960b10ff', '#ffab00'],
          xaxis: {
            categories: @json(array_column($comparisonData, 'label')),
          },
          yaxis: {
            labels: {
              formatter: function(value) {
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
        setTimeout(() => {
          const annualComparisonChart = new ApexCharts(document.querySelector("#annualComparisonChart"),
            annualComparisonChartOptions);
          annualComparisonChart.render();
        }, 300);

        // Note: COA Expense Category Allocation donut chart replaced by Profit & Loss Summary card widget.


      });
    </script>
  @endif
@endsection
