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

  #summaryDeptPlCapexTable tbody tr.bg-primary td:first-child {
    z-index: 3;
    background-color: var(--bs-primary) !important;
    color: #ffffff !important;
  }

  .btn-drilldown {
    cursor: pointer;
    text-decoration: none;
  }
  .btn-drilldown:hover {
    text-decoration: underline !important;
    opacity: 0.85;
  }
</style>
@endsection

@section('content')
<div class="py-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
  <div>
    <h4 class="mb-1"><span class="text-muted fw-light">{{ __('Analytics') }} / {{ __('Summary Department') }} /</span> {{ __('Laba Rugi & Capex') }}</h4>
    <p class="text-muted mb-0">
      {{ __('Menampilkan Laporan Laba Rugi dan Belanja Modal (Capex) per Departemen dengan Perbandingan Lintas Periode.') }} 
    </p>
  </div>

  <div class="d-flex align-items-center gap-3 flex-wrap">
    <!-- Filter Direktorat -->
    <div class="d-flex align-items-center gap-2 bg-white px-3 py-2 rounded shadow-sm border">
      <label for="directorateSelect" class="text-muted fw-semibold mb-0 text-nowrap d-flex align-items-center gap-1 rkap-font-09">
        <i class="bx bx-building text-primary fs-4"></i>
        <span>{{ __('Direktorat:') }}</span>
      </label>
      <form action="{{ route('analytics-summary-dept-pl-capex') }}" method="GET" id="filterForm" class="m-0 d-flex gap-2">
        <input type="hidden" name="budget_period_id" value="{{ $budgetPeriod->id }}">
        <input type="hidden" name="realization_period_id" value="{{ $realizationPeriod->id }}">
        <input type="hidden" name="projection_period_id" value="{{ $projectionPeriod->id }}">
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
  </div>
</div>

@if ($budgetPeriod)
<div class="row">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
        <div>
          <h5 class="card-title mb-0">{{ __('Matriks Laba Rugi & Capex per Departemen') }}</h5>
          <small class="text-muted">{{ __('Perbandingan alokasi anggaran (B), realisasi (R), dan proyeksi (P) operasional serta belanja modal') }}</small>
        </div>
      </div>
      
      <!-- Comparison Settings Filter Bar -->
      <div class="card-body py-3 border-bottom bg-light">
        <form action="{{ route('analytics-summary-dept-pl-capex') }}" method="GET" id="settingsFilterForm" class="m-0">
          <input type="hidden" name="directorate_id" value="{{ $selectedDirectorateId }}">
          <div class="row g-3 align-items-center">
            <!-- Budget Period Select -->
            <div class="col-12 col-md-4">
              <div class="d-flex align-items-center gap-2 bg-white p-2 rounded border shadow-sm">
                <span class="badge bg-label-primary p-2"><i class="bx bx-calendar fs-4"></i></span>
                <div class="w-100">
                  <label for="budgetPeriodSelect" class="form-label mb-0 fw-semibold small text-muted text-uppercase" style="font-size: 0.75rem;">{{ __('Periode Anggaran (B)') }}</label>
                  <select name="budget_period_id" id="budgetPeriodSelect" class="form-select form-select-sm border-0 fw-semibold text-primary cursor-pointer focus-ring-none p-0 m-0" onchange="this.form.submit()">
                    @foreach ($finalizedPeriods as $p)
                    <option value="{{ $p->id }}" {{ $budgetPeriod->id == $p->id ? 'selected' : '' }}>
                      {{ $p->title }} ({{ $p->year }})
                    </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>
            <!-- Realization Period Select -->
            <div class="col-12 col-md-4">
              <div class="d-flex align-items-center gap-2 bg-white p-2 rounded border shadow-sm">
                <span class="badge bg-label-success p-2"><i class="bx bx-check-circle fs-4"></i></span>
                <div class="w-100">
                  <label for="realizationPeriodSelect" class="form-label mb-0 fw-semibold small text-muted text-uppercase" style="font-size: 0.75rem;">{{ __('Periode Realisasi (R)') }}</label>
                  <select name="realization_period_id" id="realizationPeriodSelect" class="form-select form-select-sm border-0 fw-semibold text-success cursor-pointer focus-ring-none p-0 m-0" onchange="this.form.submit()">
                    @foreach ($finalizedPeriods as $p)
                    <option value="{{ $p->id }}" {{ $realizationPeriod->id == $p->id ? 'selected' : '' }}>
                      {{ $p->title }} ({{ $p->year }})
                    </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>
            <!-- Projection Period Select -->
            <div class="col-12 col-md-4">
              <div class="d-flex align-items-center gap-2 bg-white p-2 rounded border shadow-sm">
                <span class="badge bg-label-info p-2"><i class="bx bx-line-chart fs-4"></i></span>
                <div class="w-100">
                  <label for="projectionPeriodSelect" class="form-label mb-0 fw-semibold small text-muted text-uppercase" style="font-size: 0.75rem;">{{ __('Periode Proyeksi (P)') }}</label>
                  <select name="projection_period_id" id="projectionPeriodSelect" class="form-select form-select-sm border-0 fw-semibold text-info cursor-pointer focus-ring-none p-0 m-0" onchange="this.form.submit()">
                    @foreach ($finalizedPeriods as $p)
                    <option value="{{ $p->id }}" {{ $projectionPeriod->id == $p->id ? 'selected' : '' }}>
                      {{ $p->title }} ({{ $p->year }})
                    </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>

      <div class="table-responsive text-nowrap" style="max-height: 700px;">
        <table class="table table-bordered table-hover align-middle mb-0" id="summaryDeptPlCapexTable">
          <thead class="table-light sticky-top bg-light">
            <tr>
              <th rowspan="2" class="align-middle bg-light text-center rkap-mw-250">{{ __('GOLONGAN REPORT GROUP') }}</th>
              @foreach ($departments as $dept)
              <th colspan="3" class="text-center border-bottom text-uppercase fw-bold text-white" style="background-color: var(--bs-primary) !important;">
                {{ $dept->code }}<br>
                <small class="text-white text-none fw-normal" style="font-size: 0.75rem; opacity: 0.8;">{{ Str::limit($dept->name, 25) }}</small>
              </th>
              @endforeach
            </tr>
            <tr class="small text-center fw-bold">
              @foreach ($departments as $dept)
              <th class="text-end px-2 text-primary" style="min-width: 100px;">{{ __('B') }} ({{ $budgetPeriod->year }})</th>
              <th class="text-end px-2 text-success" style="min-width: 100px;">{{ __('R') }} ({{ $realizationPeriod->year }})</th>
              <th class="text-end px-2 text-info" style="min-width: 100px;">{{ __('P') }} ({{ $projectionPeriod->year }})</th>
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
              <td class="text-end small px-2">
                @if($cell['budget'] != 0)
                  <a href="javascript:void(0)" class="text-primary btn-drilldown" 
                     data-dept-id="{{ $dept->id }}" 
                     data-dept-name="{{ $dept->code }} - {{ $dept->name }}" 
                     data-group-id="{{ $group->id }}" 
                     data-period-id="{{ $budgetPeriod->id }}"
                     data-group-name="{{ $group->code }} - {{ $group->name }} (Budget)">
                    {{ number_format($cell['budget'], 0, ',', '.') }}
                  </a>
                @else
                  -
                @endif
              </td>
              <td class="text-end small px-2 text-success">
                @if($cell['realization'] != 0)
                  <a href="javascript:void(0)" class="text-success btn-drilldown" 
                     data-dept-id="{{ $dept->id }}" 
                     data-dept-name="{{ $dept->code }} - {{ $dept->name }}" 
                     data-group-id="{{ $group->id }}" 
                     data-period-id="{{ $realizationPeriod->id }}"
                     data-group-name="{{ $group->code }} - {{ $group->name }} (Realisasi)">
                    {{ number_format($cell['realization'], 0, ',', '.') }}
                  </a>
                @else
                  -
                @endif
              </td>
              <td class="text-end small px-2 text-info">
                @if($cell['projection'] != 0)
                  <a href="javascript:void(0)" class="text-info btn-drilldown" 
                     data-dept-id="{{ $dept->id }}" 
                     data-dept-name="{{ $dept->code }} - {{ $dept->name }}" 
                     data-group-id="{{ $group->id }}" 
                     data-period-id="{{ $projectionPeriod->id }}"
                     data-group-name="{{ $group->code }} - {{ $group->name }} (Proyeksi)">
                    {{ number_format($cell['projection'], 0, ',', '.') }}
                  </a>
                @else
                  -
                @endif
              </td>
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
              <td class="text-end small px-2 font-semibold">
                @if($cell['budget'] != 0)
                  <a href="javascript:void(0)" class="text-primary btn-drilldown" 
                     data-dept-id="{{ $dept->id }}" 
                     data-dept-name="{{ $dept->code }} - {{ $dept->name }}" 
                     data-group-id="capex" 
                     data-period-id="{{ $budgetPeriod->id }}"
                     data-group-name="{{ __('Total Belanja Modal (Capex) - Anggaran') }}">
                    {{ number_format($cell['budget'], 0, ',', '.') }}
                  </a>
                @else
                  -
                @endif
              </td>
              <td class="text-end small px-2 text-success font-semibold">
                @if($cell['realization'] != 0)
                  <a href="javascript:void(0)" class="text-success btn-drilldown" 
                     data-dept-id="{{ $dept->id }}" 
                     data-dept-name="{{ $dept->code }} - {{ $dept->name }}" 
                     data-group-id="capex" 
                     data-period-id="{{ $realizationPeriod->id }}"
                     data-group-name="{{ __('Total Belanja Modal (Capex) - Realisasi') }}">
                    {{ number_format($cell['realization'], 0, ',', '.') }}
                  </a>
                @else
                  -
                @endif
              </td>
              <td class="text-end small px-2 text-info font-semibold">
                @if($cell['projection'] != 0)
                  <a href="javascript:void(0)" class="text-info btn-drilldown" 
                     data-dept-id="{{ $dept->id }}" 
                     data-dept-name="{{ $dept->code }} - {{ $dept->name }}" 
                     data-group-id="capex" 
                     data-period-id="{{ $projectionPeriod->id }}"
                     data-group-name="{{ __('Total Belanja Modal (Capex) - Proyeksi') }}">
                    {{ number_format($cell['projection'], 0, ',', '.') }}
                  </a>
                @else
                  -
                @endif
              </td>
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

            <!-- Separator Empty Row -->
            <tr style="height: 15px; border: none;">
              <td colspan="{{ count($departments) * 3 + 1 }}" style="background-color: #f5f5f9; border: none; height: 15px;"></td>
            </tr>

            <!-- Grand Total Row: Laba Rugi + Capex -->
            <tr class="bg-primary text-white fw-bold">
              <td class="text-uppercase text-white ps-3 py-2"><i class="bx bx-calculator me-1"></i> {{ __('TOTAL KONSOLIDASI (LABA RUGI + CAPEX)') }}</td>
              @foreach ($departments as $dept)
              @php
                $dTot = $deptTotals[$dept->id] ?? ['budget' => 0, 'realization' => 0, 'projection' => 0];
                $cTot = $capexMatrix[$dept->id] ?? ['budget' => 0, 'realization' => 0, 'projection' => 0];
                $combBudget = $dTot['budget'] + $cTot['budget'];
                $combRealization = $dTot['realization'] + $cTot['realization'];
                $combProjection = $dTot['projection'] + $cTot['projection'];
              @endphp
              <td class="text-end px-2 text-white">{{ number_format($combBudget, 0, ',', '.') }}</td>
              <td class="text-end px-2 text-white">{{ number_format($combRealization, 0, ',', '.') }}</td>
              <td class="text-end px-2 text-white">{{ number_format($combProjection, 0, ',', '.') }}</td>
              @endforeach
            </tr>
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
          <h5 class="modal-title mb-0" id="modalTitle">{{ __('Detail Anggaran & Realisasi') }}</h5>
          <small class="text-muted" id="modalSubTitle">{{ __('Memuat rincian COA...') }}</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div id="modalLoading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
        <div class="table-responsive text-nowrap d-none" id="modalTableContainer">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="rkap-mw-200">{{ __('COA') }}</th>
                <th class="rkap-mw-300">{{ __('Program & Kegiatan') }}</th>
                <th class="text-end rkap-mw-120">{{ __('Anggaran (B)') }}</th>
                <th class="text-end rkap-mw-120">{{ __('Realisasi (R)') }}</th>
                <th class="text-end rkap-mw-120">{{ __('Proyeksi (P)') }}</th>
              </tr>
            </thead>
            <tbody id="modalTableBody">
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

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
    const modalTitle = document.getElementById('modalTitle');
    const modalSubTitle = document.getElementById('modalSubTitle');
    const modalLoading = document.getElementById('modalLoading');
    const modalTableContainer = document.getElementById('modalTableContainer');
    const modalTableBody = document.getElementById('modalTableBody');

    document.querySelectorAll('.btn-drilldown').forEach(btn => {
      btn.addEventListener('click', function() {
        const deptId = this.getAttribute('data-dept-id');
        const deptName = this.getAttribute('data-dept-name');
        const groupId = this.getAttribute('data-group-id');
        const groupName = this.getAttribute('data-group-name');
        const periodId = this.getAttribute('data-period-id');

        // Set titles
        modalTitle.textContent = groupName;
        modalSubTitle.textContent = `${deptName} | {{ __('Mengambil daftar budget items...') }}`;

        // Set loading state
        modalLoading.classList.remove('d-none');
        modalTableContainer.classList.add('d-none');
        modalTableBody.innerHTML = '';

        detailModal.show();

        // Fetch
        fetch(`/analytics/summary-dept/detail?period_id=${periodId}&department_id=${deptId}&report_group_id=${groupId}`)
          .then(res => res.json())
          .then(response => {
            modalLoading.classList.add('d-none');
            modalTableContainer.classList.remove('d-none');

            const data = response.data || [];
            modalSubTitle.textContent = `${deptName} | Ditemukan ${data.length} item anggaran`;

            if (data.length === 0) {
              modalTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">' + @js(__('Tidak ada rincian anggaran yang disetujui.')) + '</td></tr>';
              return;
            }

            let html = '';
            data.forEach(row => {
              html += `
                <tr>
                  <td>
                    <span class="badge bg-label-dark">${row.coa_code}</span>
                    <div class="fw-semibold text-wrap text-muted small mt-1" style="max-width: 250px;">${row.coa_title}</div>
                  </td>
                  <td>
                    <div class="small fw-semibold text-secondary mb-1">
                      ${row.directorate_code || '-'} - ${row.department_code || '-'} - ${row.bureau_code || '-'}
                    </div>
                    <span class="text-primary fw-medium font-monospace small">${row.program_code}</span>
                    <div class="text-wrap small text-muted mt-1" style="max-width: 350px;">${row.program_name}</div>
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
