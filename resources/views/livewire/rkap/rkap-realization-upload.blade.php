<div class="container-xxl flex-grow-1 container-p-y">
  <div class="row">
    <div class="col-12">
      <div class="card mb-4">
        <h5 class="card-header">Upload Realisasi RKAP</h5>
        <div class="card-body">
          <p class="text-muted mb-3">
            Upload file Excel (.xlsx, .xls) atau CSV untuk memperbarui data realisasi anggaran per item per bulan.
            Hanya <strong>{{ __('Verifikator') }}</strong> yang dapat melakukan upload ini.
          </p>

          {{-- Row for side-by-side layout --}}
          <div class="row">
            {{-- Left column: Selectors and Upload Form --}}
            <div class="col-lg-6 border-lg-end pe-lg-4 mb-4 mb-lg-0">
              {{-- Period selector --}}
              <div class="mb-3">
                <label class="form-label fw-semibold" for="periodSelect">Periode RKAP (Tahun Berjalan) <span class="text-danger">*</span></label>
                <select id="periodSelect" class="form-select" wire:model.live="periodId"
                  wire:loading.attr="disabled" wire:target="uploadAndImport">
                  <option value="">— Pilih Periode RKAP ({{ date('Y') }}) —</option>
                  @foreach ($periodOptions as $period)
                  <option value="{{ $period->id }}">{{ $period->title ?? $period->year }} (Tahun Berjalan {{ $period->year }})</option>
                  @endforeach
                </select>
                <div class="form-text text-muted small mt-1">
                  <i class="bx bx-info-circle me-1"></i>Realisasi hanya dapat diinput untuk periode RKAP tahun berjalan (<strong>{{ date('Y') }}</strong>) dengan status <strong>Finalized</strong>.
                </div>
                @if($periodOptions->isEmpty())
                <div class="alert alert-warning mt-2 mb-0 py-2 small" role="alert">
                  <i class="bx bx-error-circle me-1"></i> Tidak ditemukan periode RKAP tahun berjalan ({{ date('Y') }}) yang berstatus <strong>Finalized</strong> dengan usulan yang telah disetujui.
                </div>
                @endif
                @error('periodId')
                <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
              </div>

              {{-- Month selector --}}
              @if ($periodId)
              <div class="mb-3">
                <label class="form-label fw-semibold" for="monthSelect">Bulan Realisasi <span class="text-danger">*</span></label>
                @if (empty($this->monthOptions))
                <div class="alert alert-warning mb-0" role="alert">
                  <i class="bx bx-info-circle me-1"></i> Semua bulan pada periode RKAP ini sudah memiliki realisasi terunggah.
                </div>
                @else
                <select id="monthSelect" class="form-select" wire:model.live="month"
                  wire:loading.attr="disabled" wire:target="uploadAndImport">
                  <option value="">— Pilih Bulan —</option>
                  @foreach ($this->monthOptions as $num => $name)
                  <option value="{{ $num }}">{{ $name }}</option>
                  @endforeach
                </select>
                @error('month')
                <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
                @endif
              </div>
              @endif

              @if ($periodId && $month)
              {{-- Template download --}}
              <div class="alert alert-info mb-4 shadow-none border-1" role="alert">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bx bx-download fs-5 text-info"></i>
                  <strong class="text-info">{{ __('Download Template') }}</strong>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <a href="{{ route('rkap-realization-template-download', ['period_id' => $periodId, 'month' => $month]) }}"
                    class="btn btn-sm btn-success d-inline-flex align-items-center gap-1">
                    <i class="bx bx-file"></i>
                    Excel (.xlsx) <span class="badge bg-white text-success ms-1 rkap-font-065">{{ __('Direkomendasikan') }}</span>
                  </a>
                  <a href="{{ route('rkap-realization-template-download-csv', ['period_id' => $periodId, 'month' => $month]) }}"
                    class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                    <i class="bx bx-code-alt"></i>
                    CSV
                  </a>
                </div>
                <div class="mt-2 small text-muted rkap-font-08 rkap-lh-12">
                  File template telah diisi otomatis dengan data RKAP riil dan diset ke bulan <strong>{{ $this->monthOptions[$month] ?? '' }}</strong> dengan amount `0`.
                </div>
                <div class="mt-1 small text-muted rkap-font-08 rkap-lh-12">
                  Kolom wajib untuk diisi: <code>budget_item_id, month, amount</code>. Kolom detail RKAP lainnya otomatis diabaikan saat import.
                </div>
              </div>

              {{-- Upload form --}}
              <form wire:submit.prevent="uploadAndImport" class="mb-4">
                {{-- File input --}}
                <div class="mb-3">
                  <label class="form-label fw-semibold" for="realizationFile">File Excel / CSV</label>
                  <input id="realizationFile" type="file" class="form-control" wire:model="file"
                    wire:loading.attr="disabled" wire:target="file, uploadAndImport"
                    accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" />
                  @error('file')
                  <div class="text-danger mt-1">{{ $message }}</div>
                  @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100"
                  wire:loading.attr="disabled" wire:target="file, uploadAndImport">
                  <span wire:loading.remove wire:target="file, uploadAndImport">
                    <i class="bx bx-upload me-1"></i> Upload &amp; Import
                  </span>
                  <span wire:loading wire:target="file, uploadAndImport">
                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    Processing...
                  </span>
                </button>
              </form>
              @endif

              {{-- Validation errors --}}
              @if (!empty($errorsList))
              <div class="alert alert-danger shadow-none border-1">
                <h6 class="alert-heading mb-2 text-danger"><i class="bx bx-error-circle me-1"></i>{{ __('Validasi gagal') }}</h6>
                <ul class="mb-0 ps-3 small">
                  @foreach ($errorsList as $err)
                  <li>{{ $err }}</li>
                  @endforeach
                </ul>
              </div>
              @endif

              {{-- Success summary --}}
              @if ($imported)
              <div class="alert alert-success shadow-none border-1">
                <h6 class="alert-heading mb-2 text-success"><i class="bx bx-check-circle me-1"></i>{{ __('Import berhasil') }}</h6>
                <p class="mb-2 small">Realisasi RKAP untuk bulan <strong>{{ $importedMonthName }}</strong> berhasil diunggah.</p>
                <ul class="mb-0 ps-3 small">
                  <li>Baris baru: <strong>{{ $importSummary['created'] ?? 0 }}</strong></li>
                  <li>Baris diperbarui: <strong>{{ $importSummary['updated'] ?? 0 }}</strong></li>
                  <li>Total diproses: <strong>{{ $importSummary['total'] ?? 0 }}</strong></li>
                </ul>
              </div>
              @endif

              @if (session()->has('error'))
              <div class="alert alert-danger shadow-none border-1">
                <h6 class="alert-heading mb-2 text-danger"><i class="bx bx-error-circle me-1"></i>{{ __('Kesalahan') }}</h6>
                <p class="mb-0 small">{{ session('error') }}</p>
              </div>
              @endif

              @if (session()->has('message'))
              <div class="alert alert-success shadow-none border-1">
                <h6 class="alert-heading mb-2 text-success"><i class="bx bx-check-circle me-1"></i>{{ __('Berhasil') }}</h6>
                <p class="mb-0 small">{{ session('message') }}</p>
              </div>
              @endif
            </div>

            {{-- Right column: Format Guide --}}
            <div class="col-lg-6 ps-lg-4">
              <div class="border rounded p-3 bg-lighter rkap-border-dashed-muted">
                <p class="mb-2 fw-semibold d-flex align-items-center gap-1">
                  <i class="bx bx-info-circle text-primary fs-5"></i>
                  <span>{{ __('Format Kolom File Import:') }}</span>
                </p>
                <div class="table-responsive rkap-timeline-scroll">
                  <table class="table table-sm table-bordered mb-0 rkap-font-078">
                    <thead class="table-dark sticky-top">
                      <tr>
                        <th>{{ __('Kolom') }}</th>
                        <th class="text-center">{{ __('Wajib') }}</th>
                        <th>{{ __('Keterangan') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td><code>budget_item_id</code></td>
                        <td class="text-center"><span class="badge bg-danger">Ya</span></td>
                        <td>ID budget item (harus di periode terpilih)</td>
                      </tr>
                      <tr>
                        <td><code>bureaus_name</code></td>
                        <td class="text-center"><span class="badge bg-secondary">{{ __('Tidak') }}</span></td>
                        <td>Nama biro (diabaikan)</td>
                      </tr>
                      <tr>
                        <td><code>workplan_code</code></td>
                        <td class="text-center"><span class="badge bg-secondary">{{ __('Tidak') }}</span></td>
                        <td>Kode program kerja (diabaikan)</td>
                      </tr>
                      <tr>
                        <td><code>workplan_name</code></td>
                        <td class="text-center"><span class="badge bg-secondary">{{ __('Tidak') }}</span></td>
                        <td>Nama program kerja (diabaikan)</td>
                      </tr>
                      <tr>
                        <td><code>activity_code</code></td>
                        <td class="text-center"><span class="badge bg-secondary">{{ __('Tidak') }}</span></td>
                        <td>Kode kegiatan (diabaikan)</td>
                      </tr>
                      <tr>
                        <td><code>activity_name</code></td>
                        <td class="text-center"><span class="badge bg-secondary">{{ __('Tidak') }}</span></td>
                        <td>Nama kegiatan (diabaikan)</td>
                      </tr>
                      <tr>
                        <td><code>coa_code</code></td>
                        <td class="text-center"><span class="badge bg-secondary">{{ __('Tidak') }}</span></td>
                        <td>Kode COA SAP (diabaikan)</td>
                      </tr>
                      <tr>
                        <td><code>coa_desc</code></td>
                        <td class="text-center"><span class="badge bg-secondary">{{ __('Tidak') }}</span></td>
                        <td>Deskripsi COA (diabaikan)</td>
                      </tr>
                      <tr>
                        <td><code>budget_item_desc</code></td>
                        <td class="text-center"><span class="badge bg-secondary">{{ __('Tidak') }}</span></td>
                        <td>Deskripsi budget item / remarks (diabaikan)</td>
                      </tr>
                      <tr>
                        <td><code>amount_of_rkap</code></td>
                        <td class="text-center"><span class="badge bg-secondary">{{ __('Tidak') }}</span></td>
                        <td>Total anggaran RKAP (diabaikan)</td>
                      </tr>
                      <tr>
                        <td><code>sum_of_uploaded_realization</code></td>
                        <td class="text-center"><span class="badge bg-secondary">{{ __('Tidak') }}</span></td>
                        <td>Total realisasi terunggah sebelumnya (diabaikan)</td>
                      </tr>
                      <tr>
                        <td><code>notes</code></td>
                        <td class="text-center"><span class="badge bg-secondary">{{ __('Opsional') }}</span></td>
                        <td>Catatan (diabaikan, hanya untuk referensi)</td>
                      </tr>
                      <tr>
                        <td><code>month</code></td>
                        <td class="text-center"><span class="badge bg-danger">Ya</span></td>
                        <td>Bulan angka 1–12</td>
                      </tr>
                      <tr>
                        <td><code>amount</code></td>
                        <td class="text-center"><span class="badge bg-danger">Ya</span></td>
                        <td>Jumlah realisasi bulan ini (boleh negatif untuk koreksi)</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

      {{-- =====================================================================
           MASS UPDATE CARD — Administrator
           Covers Januari s.d. bulan terakhir closing dalam satu file
      ====================================================================== --}}
      @if($this->isAdminUser)
      @php
        $lastClosedMonth   = $this->lastClosedMonth;
        $lastClosedMonthName = $lastClosedMonth ? match($lastClosedMonth) {
          1  => 'Januari',  2  => 'Februari', 3  => 'Maret',
          4  => 'April',    5  => 'Mei',       6  => 'Juni',
          7  => 'Juli',     8  => 'Agustus',   9  => 'September',
          10 => 'Oktober',  11 => 'November',  12 => 'Desember',
          default => ''
        } : null;
      @endphp
      <div class="card mt-4 border border-warning-subtle">
        <h5 class="card-header d-flex align-items-center gap-2 bg-warning-subtle">
          <i class="bx bx-refresh text-warning fs-5"></i>
          <span>Update Massal Realisasi</span>
          <span class="badge bg-warning text-dark ms-1 rkap-font-07">Administrator</span>
        </h5>
        <div class="card-body">
          <p class="text-muted mb-3 small">
            Fitur ini memungkinkan Administrator melakukan <strong>update massal</strong> data realisasi dari
            <strong>Januari</strong> s.d. <strong>bulan terakhir yang sudah closing</strong>
            dalam satu kali upload file Excel.
            Data realisasi yang sudah ada akan <span class="text-warning fw-semibold">ditimpa (overwrite)</span> sesuai nilai di file.
          </p>

          @if(! $periodId)
          <div class="alert alert-secondary shadow-none border-1 mb-0" role="alert">
            <i class="bx bx-info-circle me-1"></i>
            Silakan pilih <strong>Periode RKAP</strong> pada pilihan di atas terlebih dahulu untuk mengunduh template dan melakukan update massal.
          </div>
          @elseif(! $lastClosedMonth)
          <div class="alert alert-info shadow-none border-1 mb-0" role="alert">
            <i class="bx bx-info-circle me-1"></i>
            Belum ada bulan yang sudah <em>closing</em> untuk periode ini (tanggal closing: tanggal {{ \App\Models\Setting::get('rkap_closing_day', 10) }} setiap bulannya). Mass update akan tersedia setelah minimal satu bulan melewati tanggal closing.
          </div>
          @else
          {{-- Range info banner --}}
          <div class="alert alert-warning shadow-none border-1 d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bx bx-calendar-check fs-4 text-warning flex-shrink-0"></i>
            <div>
              <strong>Cakupan bulan:</strong> Januari s.d. <strong>{{ $lastClosedMonthName }}</strong>
              ({{ $lastClosedMonth }} bulan).<br>
              <span class="small text-muted">Template berisi semua budget item × semua bulan di rentang tersebut, diisi dengan nilai realisasi terkini.</span>
            </div>
          </div>

          <div class="row">
            {{-- Left column: template download + upload form --}}
            <div class="col-lg-6 border-lg-end pe-lg-4 mb-4 mb-lg-0">

              {{-- Template download --}}
              <div class="mb-4">
                <p class="fw-semibold mb-2 d-flex align-items-center gap-1">
                  <i class="bx bx-download text-primary fs-5"></i>
                  <span>{{ __('1. Download Template Mass Update') }}</span>
                </p>
                <a href="{{ route('rkap-realization-mass-update-template-download', ['period_id' => $periodId, 'last_closed_month' => $lastClosedMonth]) }}"
                  class="btn btn-warning d-inline-flex align-items-center gap-1">
                  <i class="bx bx-file"></i>
                  Download Template (.xlsx) — Jan s.d. {{ $lastClosedMonthName }}
                </a>
                <div class="mt-2 small text-muted">
                  File template sudah terisi dengan data realisasi terkini. Cukup edit kolom <code>amount</code> lalu upload kembali.
                </div>
              </div>

              {{-- Upload form --}}
              <p class="fw-semibold mb-2 d-flex align-items-center gap-1">
                <i class="bx bx-upload text-primary fs-5"></i>
                <span>{{ __('2. Upload File Mass Update') }}</span>
              </p>
              <form wire:submit.prevent="massUpdateUpload" class="mb-3">
                <div class="mb-3">
                  <label class="form-label fw-semibold" for="massUpdateFile">File Excel (.xlsx, .xls, .csv)</label>
                  <input id="massUpdateFile"
                    type="file"
                    class="form-control"
                    wire:model="massUpdateFile"
                    wire:loading.attr="disabled"
                    wire:target="massUpdateFile, massUpdateUpload"
                    accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" />
                  @error('massUpdateFile')
                  <div class="text-danger mt-1">{{ $message }}</div>
                  @enderror
                </div>

                <button type="submit"
                  id="btnMassUpdateSubmit"
                  class="btn btn-warning w-100"
                  wire:loading.attr="disabled"
                  wire:target="massUpdateFile, massUpdateUpload"
                  onclick="return confirm('Yakin ingin melakukan mass update realisasi Jan s.d. {{ $lastClosedMonthName }}? Data realisasi yang sudah ada akan ditimpa sesuai file yang diupload.')">
                  <span wire:loading.remove wire:target="massUpdateFile, massUpdateUpload">
                    <i class="bx bx-refresh me-1"></i> Mass Update & Import
                  </span>
                  <span wire:loading wire:target="massUpdateFile, massUpdateUpload">
                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    Processing...
                  </span>
                </button>
              </form>

              {{-- Mass update error list --}}
              @if (!empty($massUpdateErrorsList))
              <div class="alert alert-danger shadow-none border-1">
                <h6 class="alert-heading mb-2 text-danger"><i class="bx bx-error-circle me-1"></i>{{ __('Validasi gagal') }}</h6>
                <ul class="mb-0 ps-3 small">
                  @foreach ($massUpdateErrorsList as $err)
                  <li>{{ $err }}</li>
                  @endforeach
                </ul>
              </div>
              @endif

              {{-- Mass update success summary --}}
              @if ($massUpdateImported)
              <div class="alert alert-success shadow-none border-1">
                <h6 class="alert-heading mb-2 text-success"><i class="bx bx-check-circle me-1"></i>{{ __('Mass Update berhasil') }}</h6>
                <p class="mb-2 small">Realisasi RKAP berhasil di-update massal (Januari s.d. {{ $lastClosedMonthName }}).</p>
                <ul class="mb-0 ps-3 small">
                  <li>Baris baru: <strong>{{ $massUpdateImportSummary['created'] ?? 0 }}</strong></li>
                  <li>Baris diperbarui: <strong>{{ $massUpdateImportSummary['updated'] ?? 0 }}</strong></li>
                  <li>Total diproses: <strong>{{ $massUpdateImportSummary['total'] ?? 0 }}</strong></li>
                </ul>
              </div>
              @endif

              @if (session()->has('massUpdateError'))
              <div class="alert alert-danger shadow-none border-1">
                <h6 class="alert-heading mb-2 text-danger"><i class="bx bx-error-circle me-1"></i>{{ __('Kesalahan') }}</h6>
                <p class="mb-0 small">{{ session('massUpdateError') }}</p>
              </div>
              @endif

            </div>

            {{-- Right column: Info / Petunjuk --}}
            <div class="col-lg-6 ps-lg-4">
              <div class="border rounded p-3 bg-lighter rkap-border-dashed-muted">
                <p class="mb-2 fw-semibold d-flex align-items-center gap-1">
                  <i class="bx bx-info-circle text-warning fs-5"></i>
                  <span>{{ __('Petunjuk Mass Update:') }}</span>
                </p>
                <ol class="small text-muted ps-3 mb-3">
                  <li class="mb-1">Download template dengan tombol di kiri. Template sudah berisi <strong>semua budget item × semua bulan</strong> (Jan s.d. {{ $lastClosedMonthName }}) beserta nilai realisasi terkini.</li>
                  <li class="mb-1">Edit kolom <code>amount</code> sesuai nilai realisasi yang benar. <strong>Jangan ubah kolom lain</strong> (terutama <code>budget_item_id</code> dan <code>month</code>).</li>
                  <li class="mb-1">Upload file yang sudah diedit menggunakan form di kiri.</li>
                  <li class="mb-1">Sistem akan <span class="text-warning fw-semibold">menimpa (overwrite)</span> data realisasi yang sudah ada dan menambah yang belum ada.</li>
                </ol>
                <div class="alert alert-warning shadow-none border-1 mb-0 small">
                  <i class="bx bx-error me-1"></i>
                  <strong>Perhatian:</strong> Bulan di luar rentang Januari–{{ $lastClosedMonthName }} akan <strong>ditolak</strong> saat upload. Hanya bulan yang sudah melewati tanggal closing yang dapat di-update secara massal.
                </div>
              </div>
            </div>
          </div>
          @endif

        </div>
      </div>
      @endif

      {{-- Department Accumulation Summary Card --}}
      @if($periodId && $this->departmentAccumulations->isNotEmpty())
      <div class="card mt-4">
        <div class="card-header py-3">
          <h5 class="mb-0">{{ __('Ringkasan Akumulasi Realisasi per Departemen') }}</h5>
          <small class="text-muted">{{ __('Klik nama departemen untuk memfilter rincian realisasi di bawah ini') }}</small>
        </div>
        <div class="table-responsive text-nowrap">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Departemen') }}</th>
                <th class="text-end rkap-mw-250">Total Realisasi (Rp)</th>
              </tr>
            </thead>
            <tbody>
              @foreach($this->departmentAccumulations as $accum)
              <tr>
                <td>
                  <a href="javascript:void(0);" wire:click="$set('filterDepartmentId', {{ $accum->id }})" class="fw-bold text-primary text-decoration-none hover-underline d-inline-flex align-items-center gap-1">
                    <i class="bx bx-filter-alt"></i>
                    <span>{{ $accum->code }} — {{ $accum->name }}</span>
                  </a>
                </td>
                <td class="text-end fw-bold text-primary font-monospace">
                  Rp {{ number_format($accum->total_amount, 0, ',', '.') }}
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endif

      {{-- Realization List Card --}}
      <div class="card mt-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div class="d-flex align-items-center gap-2">
            <h5 class="mb-0">{{ __('Daftar Realisasi Terunggah') }}</h5>
            @if($filterDepartmentId)
            @php
            $filteredDept = \App\Models\Department::find($filterDepartmentId);
            @endphp
            @if($filteredDept)
            <span class="badge bg-label-primary d-flex align-items-center gap-1">
              Departemen: {{ $filteredDept->name }}
              <button type="button" class="btn-close text-primary rkap-font-05 rkap-p-015 rkap-shadow-none" wire:click="$set('filterDepartmentId', null)" aria-label="Close"></button>
            </span>
            @endif
            @endif
          </div>
          @if($periodId)
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" wire:click="exportExcel" wire:loading.attr="disabled" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1">
              <i class="bx bx-download"></i>
              <span wire:loading.remove wire:target="exportExcel">Download Excel Realisasi</span>
              <span wire:loading wire:target="exportExcel">Downloading...</span>
            </button>
            {{-- Month filter for list --}}
            <div class="rkap-min-w-160">
              <select class="form-select form-select-sm" wire:model.live="filterMonth">
                <option value="">— Semua Bulan —</option>
                <option value="1">Januari</option>
                <option value="2">Februari</option>
                <option value="3">Maret</option>
                <option value="4">April</option>
                <option value="5">Mei</option>
                <option value="6">Juni</option>
                <option value="7">Juli</option>
                <option value="8">Agustus</option>
                <option value="9">September</option>
                <option value="10">Oktober</option>
                <option value="11">November</option>
                <option value="12">Desember</option>
              </select>
            </div>

            {{-- Search box --}}
            <div class="input-group input-group-merge input-group-sm rkap-mw-250">
              <span class="input-group-text"><i class="bx bx-search"></i></span>
              <input type="text" class="form-control" placeholder="{{ __('Cari COA atau Biro...') }}" wire:model.live.debounce.300ms="search">
            </div>
          </div>
          @endif
        </div>
        <div class="card-body">
          @if(!$periodId)
          <div class="py-5 text-center text-muted">
            <i class="bx bx-pointer bx-lg d-block mb-3 text-secondary"></i>
            <h6 class="fw-semibold">{{ __('Silakan pilih Periode RKAP di atas') }}</h6>
            <p class="small mb-0">{{ __('Pilih periode untuk memuat daftar data realisasi terunggah.') }}</p>
          </div>
          @elseif($realizations->isEmpty())
          <div class="py-5 text-center text-muted">
            <i class="bx bx-info-circle bx-lg d-block mb-3 text-warning"></i>
            <h6 class="fw-semibold">{{ __('Tidak ada data realisasi terunggah') }}</h6>
            <p class="small mb-0">{{ __('Tidak ditemukan data realisasi untuk filter terpilih pada periode ini.') }}</p>
          </div>
          @else
          <div class="table-responsive text-nowrap">
            <table class="table table-hover table-striped align-middle">
              <thead>
                <tr>
                  <th>Biro</th>
                  <th>{{ __('Akun Belanja / COA') }}</th>
                  <th>{{ __('Detail Belanja') }}</th>
                  <th class="text-center">{{ __('Bulan') }}</th>
                  <th class="text-end">Jumlah Realisasi (Rp)</th>
                  <th>{{ __('Pengunggah') }}</th>
                  <th class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody class="table-border-bottom-0">
                @foreach($realizations as $real)
                @php
                $bi = $real->budgetItem;
                $bureau = $bi?->workPlan?->submission?->bureau;
                @endphp
                <tr>
                  <td>
                    @if($bureau)
                    <strong class="text-dark">{{ $bureau->code }}</strong>
                    <div class="small text-muted rkap-mw-200 rkap-ellipsis">{{ $bureau->name }}</div>
                    @else
                    -
                    @endif
                  </td>
                  <td>
                    @if($bi)
                    <strong class="text-dark">{{ $bi->account_code }}</strong>
                    <div class="small text-muted rkap-mw-250 rkap-ellipsis">{{ $bi->description }}</div>
                    @else
                    -
                    @endif
                  </td>
                  <td>
                    <span class="small text-wrap d-block rkap-mw-250">{{ $bi?->remarks ?: '-' }}</span>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-label-info">{{ $real->month_name }}</span>
                  </td>
                  <td class="text-end fw-bold text-success">
                    Rp {{ number_format($real->amount, 0, ',', '.') }}
                  </td>
                  <td class="small">
                    <strong class="text-dark">{{ $real->uploader?->name ?: '-' }}</strong>
                    <div class="text-muted rkap-font-07">{{ $real->uploaded_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</div>
                  </td>
                  <td class="text-center">
                    <button type="button"
                      class="btn btn-sm btn-icon btn-outline-danger"
                      onclick="confirm('Apakah Anda yakin ingin menghapus data realisasi ini?') || event.stopImmediatePropagation()"
                      wire:click="deleteRealization({{ $real->id }})"
                      title="Hapus Realisasi">
                      <i class="bx bx-trash"></i>
                    </button>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-2">
            <div class="small text-muted">
              Menampilkan {{ $realizations->firstItem() ?? 0 }} - {{ $realizations->lastItem() ?? 0 }} dari {{ $realizations->total() }} data realisasi.
            </div>
            <div>
              {{ $realizations->links() }}
            </div>
          </div>
          @endif
        </div>
      </div>

    </div>
  </div>
</div>