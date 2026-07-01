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
  return '(' . number_format(abs($val), 0, ',' , '.' ) . ')' ;
  }
  return number_format($val, 0, ',' , '.' );
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
          <div class="d-flex align-items-center gap-2">
            <button type="button"
              id="btn-sync-rkap"
              class="btn btn-sm btn-primary d-flex align-items-center gap-1"
              data-bs-toggle="modal"
              data-bs-target="#modalSyncRkap"
              title="Isi otomatis data Arus Kas Aktivitas Operasi dari data usulan RKAP">
              <i class="bx bx-refresh fs-5"></i>
              <span>Sinkronisasi dari RKAP</span>
            </button>
          </div>
        </div>
        <div class="table-responsive text-nowrap">
          <table class="table table-hover table-striped-columns mb-0 align-middle table-pn-report">
            <thead>
              <tr class="table-light">
                <th style="min-width: 320px;">GOLONGAN CASH FLOW</th>
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
                    <span class="fw-medium text-dark">{{ $item->description }}</span>
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
                    <span>Selisih Kurs</span>
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
                    <span>Saldo Awal</span>
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

  {{-- ── Sync Confirmation Modal ─────────────────────────────────────────── --}}
  <div class="modal fade" id="modalSyncRkap" tabindex="-1" aria-labelledby="modalSyncRkapLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalSyncRkapLabel">
            <i class="bx bx-refresh me-2 text-primary"></i>Sinkronisasi Arus Kas Aktivitas Operasi
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted mb-3">
            Pilih periode RKAP sebagai sumber data. Sistem akan mengakumulasi nilai
            <strong>total_price</strong> dari semua usulan pada periode tersebut,
            dikelompokkan per cashflow group, dan mengisi kolom
            <strong class="text-primary">RKAP {{ now()->year }}</strong>
            pada matriks (jika data sudah ada akan diperbarui).
          </p>

          <div class="mb-3">
            <label for="sync-period-select" class="form-label fw-semibold">Periode RKAP Sumber Data</label>
            <select id="sync-period-select" class="form-select">
              @forelse ($rkapPeriods as $period)
              <option value="{{ $period->id }}">
                {{ $period->title ?? 'Periode ' . $period->year }}
                ({{ $period->year }})
                @if ($period->status === 'open') 🟢 @elseif($period->status === 'finalized') ✅ @else 🔒 @endif
              </option>
              @empty
              <option value="" disabled>Tidak ada periode RKAP tersedia</option>
              @endforelse
            </select>
            <div class="form-text">Semua status pengajuan (draft hingga approved) akan diikutsertakan.</div>
          </div>

          {{-- Status / hasil sinkronisasi --}}
          <div id="sync-result" class="d-none">
            <div id="sync-alert" class="alert mb-0" role="alert">
              <i id="sync-alert-icon" class="bx me-2"></i>
              <span id="sync-alert-msg"></span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="btn-modal-close">Batal</button>
          <button type="button" class="btn btn-primary d-flex align-items-center gap-1" id="btn-confirm-sync">
            <i class="bx bx-refresh" id="sync-spinner-icon"></i>
            <span id="sync-btn-label">Sinkronisasi Sekarang</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  @endsection

  @section('page-script')
  <script>
    (function() {
      'use strict';

      const btnConfirm = document.getElementById('btn-confirm-sync');
      const btnClose = document.getElementById('btn-modal-close');
      const periodSelect = document.getElementById('sync-period-select');
      const resultBox = document.getElementById('sync-result');
      const alertEl = document.getElementById('sync-alert');
      const alertIcon = document.getElementById('sync-alert-icon');
      const alertMsg = document.getElementById('sync-alert-msg');
      const spinnerIcon = document.getElementById('sync-spinner-icon');
      const btnLabel = document.getElementById('sync-btn-label');

      if (!btnConfirm) return;

      // Reset state whenever modal opens
      document.getElementById('modalSyncRkap').addEventListener('show.bs.modal', function() {
        resultBox.classList.add('d-none');
        alertEl.className = 'alert mb-0';
        alertIcon.className = 'bx me-2';
        alertMsg.textContent = '';
        setLoading(false);
        btnClose.textContent = 'Batal';
      });

      btnConfirm.addEventListener('click', function() {
        const periodId = periodSelect ? periodSelect.value : '';
        if (!periodId) {
          showResult('danger', 'bx-error-circle', 'Pilih periode RKAP terlebih dahulu.');
          return;
        }

        setLoading(true);
        resultBox.classList.add('d-none');

        fetch('{{ route("analytics-cashflow-matrix-sync") }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({
              period_id: periodId
            }),
          })
          .then(function(res) {
            return res.json();
          })
          .then(function(data) {
            setLoading(false);
            if (data.success) {
              showResult(
                'success',
                'bx-check-circle',
                data.message + ' Halaman akan dimuat ulang dalam 2 detik…'
              );
              btnClose.textContent = 'Tutup';
              btnConfirm.disabled = true;
              setTimeout(function() {
                window.location.reload();
              }, 2000);
            } else {
              showResult('danger', 'bx-error-circle', data.message || 'Sinkronisasi gagal.');
            }
          })
          .catch(function(err) {
            setLoading(false);
            showResult('danger', 'bx-error-circle', 'Terjadi kesalahan jaringan. Silakan coba lagi.');
            console.error('Sync error:', err);
          });
      });

      function setLoading(isLoading) {
        btnConfirm.disabled = isLoading;
        if (isLoading) {
          spinnerIcon.className = 'bx bx-loader-alt bx-spin';
          btnLabel.textContent = 'Memproses…';
        } else {
          spinnerIcon.className = 'bx bx-refresh';
          btnLabel.textContent = 'Sinkronisasi Sekarang';
        }
      }

      function showResult(type, iconClass, message) {
        resultBox.classList.remove('d-none');
        alertEl.className = 'alert alert-' + type + ' mb-0';
        alertIcon.className = 'bx ' + iconClass + ' me-2';
        alertMsg.textContent = message;
      }
    })();
  </script>
  @endsection