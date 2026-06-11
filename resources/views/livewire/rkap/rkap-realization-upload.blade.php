<div class="container-xxl flex-grow-1 container-p-y">
  <div class="row">
    <div class="col-12">
      <div class="card mb-4">
        <h5 class="card-header">Upload Realisasi RKAP</h5>
        <div class="card-body">
          <p class="text-muted mb-3">
            Upload file Excel (.xlsx, .xls) atau CSV untuk memperbarui data realisasi anggaran per item per bulan.
            Hanya <strong>Verifikator</strong> yang dapat melakukan upload ini.
          </p>

          {{-- Template download --}}
          <div class="alert alert-info mb-4" role="alert">
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="bx bx-download fs-5"></i>
              <strong>Download Template</strong>
            </div>
            @if ($periodId)
              <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('rkap-realization-template-download', ['period_id' => $periodId]) }}"
                   class="btn btn-sm btn-success d-inline-flex align-items-center gap-1">
                  <i class="bx bx-file"></i>
                  Excel (.xlsx) <span class="badge bg-white text-success ms-1" style="font-size:0.65rem;">Direkomendasikan</span>
                </a>
                <a href="{{ route('rkap-realization-template-download-csv', ['period_id' => $periodId]) }}"
                   class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                  <i class="bx bx-code-alt"></i>
                  CSV
                </a>
              </div>
              <div class="mt-2 small text-muted">
                File template telah diisi otomatis dengan data RKAP riil dari periode yang Anda pilih.
              </div>
            @else
              <div class="text-warning mb-2 small fw-semibold">
                <i class="bx bx-info-circle me-1"></i> Silakan pilih Periode RKAP terlebih dahulu untuk mengunduh template berisi data riil.
              </div>
              <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-sm btn-secondary d-inline-flex align-items-center gap-1" disabled>
                  <i class="bx bx-file"></i> Excel (.xlsx)
                </button>
                <button class="btn btn-sm btn-secondary d-inline-flex align-items-center gap-1" disabled>
                  <i class="bx bx-code-alt"></i> CSV
                </button>
              </div>
            @endif
            <div class="mt-2 small text-muted">
              Kolom wajib untuk diisi saat upload: <code>budget_item_id, month, amount</code> &mdash; Kolom opsional: <code>notes</code>. Kolom detail RKAP lainnya otomatis diabaikan saat import.
            </div>
          </div>

          {{-- Upload form --}}
          <form wire:submit.prevent="uploadAndImport" class="mb-4">

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

            <button type="submit" class="btn btn-primary"
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

          {{-- Validation errors --}}
          @if (!empty($errorsList))
            <div class="alert alert-danger">
              <h6 class="alert-heading mb-2"><i class="bx bx-error-circle me-1"></i>Validasi gagal</h6>
              <ul class="mb-0 ps-3">
                @foreach ($errorsList as $err)
                  <li>{{ $err }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          {{-- Success summary --}}
          @if ($imported)
            <div class="alert alert-success">
              <h6 class="alert-heading mb-2"><i class="bx bx-check-circle me-1"></i>Import berhasil</h6>
              <ul class="mb-0 ps-3">
                <li>Baris baru: <strong>{{ $importSummary['created'] ?? 0 }}</strong></li>
                <li>Baris diperbarui: <strong>{{ $importSummary['updated'] ?? 0 }}</strong></li>
                <li>Total diproses: <strong>{{ $importSummary['total'] ?? 0 }}</strong></li>
              </ul>
            </div>
          @endif

          {{-- Format guide --}}
          <div class="border rounded p-3 bg-lighter mt-3">
            <p class="mb-2 fw-semibold">Format kolom file:</p>
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-dark">
                <tr>
                  <th>Kolom</th>
                  <th>Wajib</th>
                  <th>Keterangan</th>
                </tr>
              </thead>
              <tbody>
                <tr><td><code>budget_item_id</code></td><td><span class="badge bg-danger">Ya</span></td><td>ID dari tabel rkap_budget_items (harus termasuk periode terpilih)</td></tr>
                <tr><td><code>bureaus_name</code></td><td><span class="badge bg-secondary">Tidak</span></td><td>Nama biro (diabaikan otomatis saat import)</td></tr>
                <tr><td><code>workplan_code</code></td><td><span class="badge bg-secondary">Tidak</span></td><td>Kode program kerja (diabaikan otomatis saat import)</td></tr>
                <tr><td><code>workplan_name</code></td><td><span class="badge bg-secondary">Tidak</span></td><td>Nama program kerja (diabaikan otomatis saat import)</td></tr>
                <tr><td><code>activity_code</code></td><td><span class="badge bg-secondary">Tidak</span></td><td>Kode kegiatan (diabaikan otomatis saat import)</td></tr>
                <tr><td><code>activity_name</code></td><td><span class="badge bg-secondary">Tidak</span></td><td>Nama kegiatan (diabaikan otomatis saat import)</td></tr>
                <tr><td><code>coa_code</code></td><td><span class="badge bg-secondary">Tidak</span></td><td>Kode COA SAP (diabaikan otomatis saat import)</td></tr>
                <tr><td><code>coa_desc</code></td><td><span class="badge bg-secondary">Tidak</span></td><td>Deskripsi COA (diabaikan otomatis saat import)</td></tr>
                <tr><td><code>budget_item_desc</code></td><td><span class="badge bg-secondary">Tidak</span></td><td>Deskripsi budget item / remarks (diabaikan otomatis saat import)</td></tr>
                <tr><td><code>amount_of_rkap</code></td><td><span class="badge bg-secondary">Tidak</span></td><td>Total anggaran RKAP (diabaikan otomatis saat import)</td></tr>
                <tr><td><code>sum_of_uploaded_realization</code></td><td><span class="badge bg-secondary">Tidak</span></td><td>Total realisasi terunggah sebelumnya (diabaikan otomatis saat import)</td></tr>
                <tr><td><code>notes</code></td><td><span class="badge bg-secondary">Opsional</span></td><td>Catatan (tidak disimpan ke database, hanya untuk referensi)</td></tr>
                <tr><td><code>month</code></td><td><span class="badge bg-danger">Ya</span></td><td>Bulan angka 1–12</td></tr>
                <tr><td><code>amount</code></td><td><span class="badge bg-danger">Ya</span></td><td>Jumlah realisasi bulan ini (angka ≥ 0)</td></tr>
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>
