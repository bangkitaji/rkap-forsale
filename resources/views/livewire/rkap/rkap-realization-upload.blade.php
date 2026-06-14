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

          {{-- Row for side-by-side layout --}}
          <div class="row">
            {{-- Left column: Selectors and Upload Form --}}
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
                    <strong class="text-info">Download Template</strong>
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('rkap-realization-template-download', ['period_id' => $periodId, 'month' => $month]) }}"
                       class="btn btn-sm btn-success d-inline-flex align-items-center gap-1">
                      <i class="bx bx-file"></i>
                      Excel (.xlsx) <span class="badge bg-white text-success ms-1" style="font-size:0.65rem;">Direkomendasikan</span>
                    </a>
                    <a href="{{ route('rkap-realization-template-download-csv', ['period_id' => $periodId, 'month' => $month]) }}"
                       class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                      <i class="bx bx-code-alt"></i>
                      CSV
                    </a>
                  </div>
                  <div class="mt-2 small text-muted" style="font-size: 0.8rem; line-height: 1.35;">
                    File template telah diisi otomatis dengan data RKAP riil dan diset ke bulan <strong>{{ $this->monthOptions[$month] ?? '' }}</strong> dengan amount `0`.
                  </div>
                  <div class="mt-1 small text-muted" style="font-size: 0.8rem; line-height: 1.35;">
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
                  <h6 class="alert-heading mb-2 text-danger"><i class="bx bx-error-circle me-1"></i>Validasi gagal</h6>
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
                  <h6 class="alert-heading mb-2 text-success"><i class="bx bx-check-circle me-1"></i>Import berhasil</h6>
                  <p class="mb-2 small">Realisasi RKAP untuk bulan <strong>{{ $importedMonthName }}</strong> berhasil diunggah.</p>
                  <ul class="mb-0 ps-3 small">
                    <li>Baris baru: <strong>{{ $importSummary['created'] ?? 0 }}</strong></li>
                    <li>Baris diperbarui: <strong>{{ $importSummary['updated'] ?? 0 }}</strong></li>
                    <li>Total diproses: <strong>{{ $importSummary['total'] ?? 0 }}</strong></li>
                  </ul>
                </div>
              @endif
            </div>

            {{-- Right column: Format Guide --}}
            <div class="col-lg-6 ps-lg-4">
              <div class="border rounded p-3 bg-lighter" style="border-style: dashed !important;">
                <p class="mb-2 fw-semibold d-flex align-items-center gap-1">
                  <i class="bx bx-info-circle text-primary fs-5"></i>
                  <span>Format Kolom File Import:</span>
                </p>
                <div class="table-responsive" style="max-height: 335px; overflow-y: auto;">
                  <table class="table table-sm table-bordered mb-0" style="font-size: 0.78rem;">
                    <thead class="table-dark sticky-top">
                      <tr>
                        <th>Kolom</th>
                        <th class="text-center">Wajib</th>
                        <th>Keterangan</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr><td><code>budget_item_id</code></td><td class="text-center"><span class="badge bg-danger">Ya</span></td><td>ID budget item (harus di periode terpilih)</td></tr>
                      <tr><td><code>bureaus_name</code></td><td class="text-center"><span class="badge bg-secondary">Tidak</span></td><td>Nama biro (diabaikan)</td></tr>
                      <tr><td><code>workplan_code</code></td><td class="text-center"><span class="badge bg-secondary">Tidak</span></td><td>Kode program kerja (diabaikan)</td></tr>
                      <tr><td><code>workplan_name</code></td><td class="text-center"><span class="badge bg-secondary">Tidak</span></td><td>Nama program kerja (diabaikan)</td></tr>
                      <tr><td><code>activity_code</code></td><td class="text-center"><span class="badge bg-secondary">Tidak</span></td><td>Kode kegiatan (diabaikan)</td></tr>
                      <tr><td><code>activity_name</code></td><td class="text-center"><span class="badge bg-secondary">Tidak</span></td><td>Nama kegiatan (diabaikan)</td></tr>
                      <tr><td><code>coa_code</code></td><td class="text-center"><span class="badge bg-secondary">Tidak</span></td><td>Kode COA SAP (diabaikan)</td></tr>
                      <tr><td><code>coa_desc</code></td><td class="text-center"><span class="badge bg-secondary">Tidak</span></td><td>Deskripsi COA (diabaikan)</td></tr>
                      <tr><td><code>budget_item_desc</code></td><td class="text-center"><span class="badge bg-secondary">Tidak</span></td><td>Deskripsi budget item / remarks (diabaikan)</td></tr>
                      <tr><td><code>amount_of_rkap</code></td><td class="text-center"><span class="badge bg-secondary">Tidak</span></td><td>Total anggaran RKAP (diabaikan)</td></tr>
                      <tr><td><code>sum_of_uploaded_realization</code></td><td class="text-center"><span class="badge bg-secondary">Tidak</span></td><td>Total realisasi terunggah sebelumnya (diabaikan)</td></tr>
                      <tr><td><code>notes</code></td><td class="text-center"><span class="badge bg-secondary">Opsional</span></td><td>Catatan (diabaikan, hanya untuk referensi)</td></tr>
                      <tr><td><code>month</code></td><td class="text-center"><span class="badge bg-danger">Ya</span></td><td>Bulan angka 1–12</td></tr>
                      <tr><td><code>amount</code></td><td class="text-center"><span class="badge bg-danger">Ya</span></td><td>Jumlah realisasi bulan ini (angka ≥ 0)</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

      {{-- Realization List Card --}}
      <div class="card mt-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h5 class="mb-0">Daftar Realisasi Terunggah</h5>
          @if($periodId)
            <div class="d-flex align-items-center gap-2 flex-wrap">
              {{-- Month filter for list --}}
              <div style="min-width: 160px;">
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
              <div class="input-group input-group-merge input-group-sm" style="width: 250px;">
                <span class="input-group-text"><i class="bx bx-search"></i></span>
                <input type="text" class="form-control" placeholder="Cari COA atau Biro..." wire:model.live.debounce.300ms="search">
              </div>
            </div>
          @endif
        </div>
        <div class="card-body">
          @if(!$periodId)
            <div class="py-5 text-center text-muted">
              <i class="bx bx-pointer bx-lg d-block mb-3 text-secondary"></i>
              <h6 class="fw-semibold">Silakan pilih Periode RKAP di atas</h6>
              <p class="small mb-0">Pilih periode untuk memuat daftar data realisasi terunggah.</p>
            </div>
          @elseif($realizations->isEmpty())
            <div class="py-5 text-center text-muted">
              <i class="bx bx-info-circle bx-lg d-block mb-3 text-warning"></i>
              <h6 class="fw-semibold">Tidak ada data realisasi terunggah</h6>
              <p class="small mb-0">Tidak ditemukan data realisasi untuk filter terpilih pada periode ini.</p>
            </div>
          @else
            <div class="table-responsive text-nowrap">
              <table class="table table-hover table-striped align-middle">
                <thead>
                  <tr>
                    <th>Biro</th>
                    <th>Akun Belanja / COA</th>
                    <th>Detail Belanja</th>
                    <th class="text-center">Bulan</th>
                    <th class="text-end">Jumlah Realisasi (Rp)</th>
                    <th>Pengunggah</th>
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
                          <div class="small text-muted" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">{{ $bureau->name }}</div>
                        @else
                          -
                        @endif
                      </td>
                      <td>
                        @if($bi)
                          <strong class="text-dark">{{ $bi->account_code }}</strong>
                          <div class="small text-muted" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis;">{{ $bi->description }}</div>
                        @else
                          -
                        @endif
                      </td>
                      <td>
                        <span class="small text-wrap" style="display: block; max-width: 250px;">{{ $bi?->remarks ?: '-' }}</span>
                      </td>
                      <td class="text-center">
                        <span class="badge bg-label-info">{{ $real->month_name }}</span>
                      </td>
                      <td class="text-end fw-bold text-success">
                        Rp {{ number_format($real->amount, 0, ',', '.') }}
                      </td>
                      <td class="small">
                        <strong class="text-dark">{{ $real->uploader?->name ?: '-' }}</strong>
                        <div class="text-muted" style="font-size: 0.7rem;">{{ $real->uploaded_at?->format('d/m/Y H:i') }}</div>
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
