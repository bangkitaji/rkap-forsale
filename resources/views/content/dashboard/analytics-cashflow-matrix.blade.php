@extends('layouts/contentNavbarLayout')

@section('title', 'Laporan - Matrix Cash Flow')

@section('content')
  @php
    if (!function_exists('formatCfValue')) {
      function formatCfValue($val) {
        if ($val === null || $val == 0) {
          return '-';
        }
        if ($val < 0) {
          return '(' . number_format(abs($val), 0, ',', '.') . ')';
        }
        return number_format($val, 0, ',', '.');
      }
    }

    if (!function_exists('formatVersionHeader')) {
      function formatVersionHeader($version) {
        if ($version->name === 'Actual') {
          return $version->year;
        }
        if ($version->name === 'Audited') {
          return $version->year . ' ' . $version->name;
        }
        return $version->name . ' ' . $version->year;
      }
    }
  @endphp

  <style>
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
    
    .table-pn-report tbody tr.table-dark td:first-child {
      background-color: #233446 !important;
      color: #fff;
    }
  </style>

  <div class="py-3 mb-4">
    <h4 class="mb-1"><span class="text-muted fw-light">RKAP /</span> Laporan Matrix Cash Flow</h4>
    <p class="text-muted mb-0">Menampilkan Perbandingan Laporan Cash Flow Multi-Versi (Lintas Tahun dan Versi Anggaran)</p>
  </div>

  <div class="row">
    {{-- Cash Flow Matrix Table --}}
    <div class="col-12">
      <div class="card h-100">
        <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h5 class="card-title mb-0">Matriks Laporan Arus Kas (Multi-Version Cash Flow)</h5>
            <small class="text-muted">Ikhtisar data historis, realisasi, dan prognosa arus kas secara komparatif</small>
          </div>
        </div>
        <div class="table-responsive text-nowrap">
          <table class="table table-hover table-striped-columns mb-0 align-middle table-pn-report">
            <thead>
              <tr class="table-light">
                <th style="min-width: 320px;">KODE & GOLONGAN CASH FLOW</th>
                @foreach ($versions as $version)
                  <th class="text-end" style="min-width: 180px;">{{ formatVersionHeader($version) }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @foreach ($categories->where('category_id', '<', 4) as $category)
                <!-- Category Header -->
                <tr class="table-light fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                  <td colspan="{{ 1 + count($versions) }}">
                    @if ($category->category_id == 1)
                      <i class="bx bx-trending-up me-2 text-success"></i>
                    @elseif ($category->category_id == 2)
                      <i class="bx bx-analyse me-2 text-info"></i>
                    @elseif ($category->category_id == 3)
                      <i class="bx bx-trending-down me-2 text-danger"></i>
                    @endif
                    {{ $category->name }}
                  </td>
                </tr>

                <!-- Line Items -->
                @foreach ($category->lineItems as $item)
                  @php
                    $isOutflow = !str_starts_with($item->item_code, 'CF0A') && $item->item_code !== 'CF0B10' && !str_starts_with($item->item_code, 'CF0E') && $item->item_code !== 'CF0F8';
                    if (str_starts_with($item->item_code, 'CF0E11')) {
                      $isOutflow = true; // Penarikan Dana Dibatasi Pendanaan is outflow/negative
                    }
                  @endphp
                  <tr>
                    <td class="ps-4">
                      <div class="d-flex flex-column">
                        <small class="font-monospace fw-semibold text-secondary" style="font-size: 0.75rem;">{{ $item->item_code }}</small>
                        <span class="fw-medium text-dark">{{ $item->description }}</span>
                      </div>
                    </td>
                    @foreach ($versions as $version)
                      @php
                        $val = $matrix[$item->item_code][$version->version_id] ?? 0.0;
                      @endphp
                      <td class="text-end font-monospace @if($val < 0) text-danger @elseif($val > 0 && !$isOutflow) text-success @endif">
                        {{ formatCfValue($val) }}
                      </td>
                    @endforeach
                  </tr>
                @endforeach

                <!-- Category Subtotal -->
                <tr class="fw-semibold bg-lighter">
                  <td class="ps-3 text-secondary">
                    @if ($category->category_id == 1)
                      Kas Bersih Aktivitas Operasi
                    @elseif ($category->category_id == 2)
                      Kas Bersih Aktivitas Investasi
                    @elseif ($category->category_id == 3)
                      Kas Bersih Aktivitas Pendanaan
                    @endif
                  </td>
                  @foreach ($versions as $version)
                    @php
                      $subVal = $categorySubtotals[$category->category_id][$version->version_id] ?? 0.0;
                    @endphp
                    <td class="text-end font-monospace fw-bold @if($subVal < 0) text-danger @elseif($subVal > 0) text-success @endif">
                      {{ formatCfValue($subVal) }}
                    </td>
                  @endforeach
                </tr>
              @endforeach

              <!-- Overall Totals -->
              <tr class="table-light fw-bold" style="border-top: 2px solid #a1acb8;">
                <td colspan="{{ 1 + count($versions) }}" class="text-uppercase py-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Ringkasan Akhir Kas</td>
              </tr>

              <!-- Perubahan Kas Bersih -->
              <tr class="table-primary fw-bold">
                <td class="text-primary">
                  <i class="bx bx-bar-chart-alt me-2"></i>Perubahan Kas Bersih
                </td>
                @foreach ($versions as $version)
                  @php
                    $netVal = $netCashFlows[$version->version_id] ?? 0.0;
                  @endphp
                  <td class="text-end font-monospace text-primary fw-bold">
                    {{ formatCfValue($netVal) }}
                  </td>
                @endforeach
              </tr>

              <!-- Selisih Kurs -->
              <tr>
                <td class="ps-4 text-dark">
                  <div class="d-flex flex-column">
                    <small class="font-monospace fw-semibold text-secondary" style="font-size: 0.75rem;">CF0D1</small>
                    <span>Selisih Kurs</span>
                  </div>
                </td>
                @foreach ($versions as $version)
                  @php
                    $kursVal = $matrix['CF0D1'][$version->version_id] ?? 0.0;
                  @endphp
                  <td class="text-end font-monospace @if($kursVal < 0) text-danger @elseif($kursVal > 0) text-success @endif">
                    {{ formatCfValue($kursVal) }}
                  </td>
                @endforeach
              </tr>

              <!-- Saldo Awal -->
              <tr>
                <td class="ps-4 text-dark">
                  <div class="d-flex flex-column">
                    <small class="font-monospace fw-semibold text-secondary" style="font-size: 0.75rem;">CF_BEGINNING</small>
                    <span>Saldo Awal</span>
                  </div>
                </td>
                @foreach ($versions as $version)
                  @php
                    $startVal = $matrix['CF_BEGINNING'][$version->version_id] ?? 0.0;
                  @endphp
                  <td class="text-end font-monospace text-dark">
                    {{ formatCfValue($startVal) }}
                  </td>
                @endforeach
              </tr>

              <!-- Saldo Akhir -->
              <tr class="table-dark fw-bold" style="border-top: 2px solid #233446;">
                <td>
                  <i class="bx bx-wallet me-2"></i>Saldo Akhir
                </td>
                @foreach ($versions as $version)
                  @php
                    $endVal = $endingBalances[$version->version_id] ?? 0.0;
                  @endphp
                  <td class="text-end font-monospace fw-bold">
                    {{ formatCfValue($endVal) }}
                  </td>
                @endforeach
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
