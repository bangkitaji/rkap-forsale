<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex justify-content-between align-items-center py-2 mb-4">
    <h4 class="mb-0"><span class="text-muted fw-light">RKAP / <a href="{{ route('rkap-projections') }}" class="text-muted">Proyeksi</a> /</span> Upload Massal Proyeksi</h4>
    <a href="{{ route('rkap-projections') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
      <i class="bx bx-arrow-back"></i> Kembali ke Proyeksi
    </a>
  </div>

  <div class="row">
    <div class="col-12">
      <div class="card mb-4 border-top border-primary border-3">
        <h5 class="card-header d-flex align-items-center gap-2">
          <i class="bx bx-calculator fs-4 text-primary"></i>
          <span>Upload Massal Proyeksi RKAP</span>
        </h5>
        <div class="card-body">
          <p class="text-muted mb-4">
            Upload file Excel (.xlsx, .xls) atau CSV untuk melakukan pembaruan proyeksi anggaran untuk seluruh bulan (Januari s.d. Desember) secara massal.
            Hanya <strong>Administrator</strong> dan <strong>Verifikator</strong> yang memiliki akses untuk melakukan upload ini.
          </p>

          <div class="row">
            {{-- Left column: Form and Outputs --}}
            <div class="col-lg-6 border-lg-end pe-lg-4 mb-4 mb-lg-0">
              {{-- Period selector --}}
              <div class="mb-3">
                <label class="form-label fw-semibold" for="periodSelect">Periode RKAP <span class="text-danger">*</span></label>
                <select id="periodSelect" class="form-select" wire:model.live="periodId"
                  wire:loading.attr="disabled" wire:target="uploadAndImport">
                  <option value="">— Pilih Periode —</option>
                  @foreach ($periodOptions as $period)
                    <option value="{{ $period->id }}">{{ $period->title ?? $period->year }}</option>
                  @endforeach
                </select>
                @error('periodId')
                  <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
              </div>

              @if ($periodId)
                {{-- Template download card --}}
                <div class="alert alert-primary mb-4 shadow-none border-1" role="alert" style="background-color: #f0f3ff; border-color: #cbd5e1;">
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bx bx-download fs-5 text-primary"></i>
                    <strong class="text-primary">Download Template Proyeksi</strong>
                  </div>
                  <p class="small text-dark mb-3" style="line-height: 1.4;">
                    Gunakan file template yang berisi data RKAP periode terpilih dengan seluruh item anggaran yang disetujui (Approved).
                    Kolom proyeksi bulan berjalan s.d. Desember dapat diisi, sedangkan bulan yang sudah lalu akan dikunci secara otomatis.
                  </p>
                  <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('rkap-projection-template-download', ['period_id' => $periodId]) }}"
                       class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
                      <i class="bx bx-file"></i>
                      Excel (.xlsx) <span class="badge bg-white text-primary ms-1" style="font-size:0.65rem;">Terisi Data Riil</span>
                    </a>
                  </div>
                  <div class="mt-3 small text-muted" style="font-size: 0.8rem; line-height: 1.4;">
                    <ul class="mb-0 ps-3">
                      <li>Kolom wajib diisi: <code>budget_item_id</code> dan <code>m1</code> s.d. <code>m12</code>.</li>
                      <li>Kolom nama biro, program, kegiatan, COA, dan total anggaran disediakan sebagai referensi visual dan otomatis diabaikan sistem.</li>
                    </ul>
                  </div>
                </div>

                {{-- Upload form --}}
                <form wire:submit.prevent="uploadAndImport" class="mb-4">
                  <div class="mb-3">
                    <label class="form-label fw-semibold" for="projectionFile">File Excel / CSV</label>
                    <input id="projectionFile" type="file" class="form-control" wire:model="file"
                      wire:loading.attr="disabled" wire:target="file, uploadAndImport"
                      accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" />
                    @error('file')
                      <div class="text-danger mt-1">{{ $message }}</div>
                    @enderror
                  </div>

                  <button type="submit" class="btn btn-primary w-100"
                    wire:loading.attr="disabled" wire:target="file, uploadAndImport">
                    <span wire:loading.remove wire:target="file, uploadAndImport">
                      <i class="bx bx-upload me-1"></i> Mulai Unggah &amp; Import
                    </span>
                    <span wire:loading wire:target="file, uploadAndImport">
                      <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                      Memproses file...
                    </span>
                  </button>
                </form>
              @endif

              {{-- Validation errors list --}}
              @if (!empty($errorsList))
                <div class="alert alert-danger shadow-none border-1">
                  <h6 class="alert-heading mb-2 text-danger fw-bold d-flex align-items-center gap-1">
                    <i class="bx bx-error-circle"></i> Validasi Unggahan Gagal
                  </h6>
                  <div class="border rounded bg-white p-2" style="max-height: 250px; overflow-y: auto;">
                    <ul class="mb-0 ps-3 text-danger small">
                      @foreach ($errorsList as $err)
                        <li class="py-1 border-bottom last-border-0">{{ $err }}</li>
                      @endforeach
                    </ul>
                  </div>
                </div>
              @endif

              {{-- Success message --}}
              @if ($imported)
                <div class="alert alert-success shadow-none border-1">
                  <h6 class="alert-heading mb-2 text-success fw-bold d-flex align-items-center gap-1">
                    <i class="bx bx-check-circle"></i> Import Proyeksi Berhasil!
                  </h6>
                  <p class="mb-2 small text-dark">Data proyeksi RKAP tahun berjalan berhasil diperbarui berdasarkan spreadsheet.</p>
                  <ul class="mb-0 ps-3 small text-success">
                    <li>Total item anggaran diperbarui: <strong>{{ $importSummary['updated'] ?? 0 }}</strong></li>
                  </ul>
                </div>
              @endif
            </div>

            {{-- Right column: Format instruction guide --}}
            <div class="col-lg-6 ps-lg-4">
              <div class="border rounded p-3 bg-light" style="border-style: dashed !important; border-color: #cbd5e1 !important;">
                <p class="mb-3 fw-semibold d-flex align-items-center gap-1 text-dark">
                  <i class="bx bx-info-circle text-primary fs-5"></i>
                  <span>Panduan Kolom File Import:</span>
                </p>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                  <table class="table table-sm table-bordered mb-0 bg-white" style="font-size: 0.78rem;">
                    <thead class="table-dark sticky-top">
                      <tr>
                        <th>Kolom</th>
                        <th class="text-center">Wajib</th>
                        <th>Keterangan</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td><code>budget_item_id</code></td>
                        <td class="text-center"><span class="badge bg-danger">Ya</span></td>
                        <td>ID unik dari item anggaran RKAP. Harus sesuai dengan sistem.</td>
                      </tr>
                      <tr>
                        <td><code>m1</code> s.d. <code>m12</code></td>
                        <td class="text-center"><span class="badge bg-danger">Ya</span></td>
                        <td>
                          Nilai proyeksi bulanan (m1 = Jan, m12 = Des).
                          <br><span class="text-danger small">* Harus berupa angka non-negatif (&ge; 0).</span>
                          <br><span class="text-warning small">* Tidak boleh melebihi anggaran bulanan.</span>
                          <br><span class="text-secondary small">* Jumlah akumulasi setahun tidak boleh melebihi total RKAP item.</span>
                          <br><span class="text-info small">* Bulan yang sudah terlewat terkunci &amp; tidak boleh dirubah angkanya.</span>
                        </td>
                      </tr>
                      <tr>
                        <td><code>bureaus_name</code> s.d. <code>amount_of_rkap</code></td>
                        <td class="text-center"><span class="badge bg-secondary">Tidak</span></td>
                        <td>Kolom referensi visual. Otomatis diabaikan oleh sistem saat import data dijalankan.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
