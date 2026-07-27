<div class="container-xxl flex-grow-1 container-p-y">
  <div class="row">
    <div class="col-md-6 col-12">
      <div class="card mb-4">
        <h5 class="card-header">Pengaturan Closing Periode</h5>
        <div class="card-body">
          <p class="text-muted mb-4">
            Pengaturan ini digunakan untuk menentukan tanggal tutup buku penginputan realisasi di setiap bulannya secara otomatis (misal: jika diset ke tanggal 10, maka input realisasi bulan Juni dibatasi s.d. tanggal 10 Juli).
          </p>

          @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible" role="alert">
              {{ session('message') }}
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          @endif

          <form wire:submit.prevent="save">
            <div class="mb-4">
              <label class="form-label fw-semibold" for="closingDayInput">Tanggal Closing Setiap Bulan <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">Tanggal</span>
                <input id="closingDayInput" type="number" class="form-control @error('closingDay') is-invalid @enderror" 
                  wire:model="closingDay" min="1" max="31" />
                <span class="input-group-text">Bulan Berikutnya</span>
              </div>
              @error('closingDay')
                <div class="text-danger small mt-1">{{ $message }}</div>
              @enderror
            </div>

            <hr class="my-4">

            <h5 class="mb-3">Pengaturan Akses Pengisian Usulan RKAP</h5>
            <div class="mb-4">
              <label class="form-label fw-semibold">Status Pengisian / Edit Usulan RKAP <span class="text-danger">*</span></label>
              <div class="d-flex gap-4 mt-1">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="submissionStatus" id="subStatusOpen" value="open" wire:model="submissionStatus">
                  <label class="form-check-label fw-semibold text-success d-flex align-items-center gap-1" for="subStatusOpen">
                    <i class="bx bx-lock-open-alt"></i> Open (Pengisian Usulan Dibuka)
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="submissionStatus" id="subStatusClosed" value="closed" wire:model="submissionStatus">
                  <label class="form-check-label fw-semibold text-danger d-flex align-items-center gap-1" for="subStatusClosed">
                    <i class="bx bx-lock-alt"></i> Close (Pengisian Usulan Ditutup)
                  </label>
                </div>
              </div>
              @error('submissionStatus')
                <div class="text-danger small mt-1">{{ $message }}</div>
              @enderror
              <div class="form-text text-muted mt-2">
                Jika diset ke <strong>Close</strong>, pengguna tidak dapat membuat, mengedit, mengajukan, menyetujui, atau menolak usulan RKAP, serta tidak dapat melakukan transfer budget.
              </div>
            </div>

            <hr class="my-4">

            <h5 class="mb-3">Pengaturan Akses Proyeksi</h5>
            <div class="mb-4">
              <label class="form-label fw-semibold">Status Penginputan / Edit Proyeksi <span class="text-danger">*</span></label>
              <div class="d-flex gap-4 mt-1">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="projectionStatus" id="projStatusOpen" value="open" wire:model="projectionStatus">
                  <label class="form-check-label fw-semibold text-success d-flex align-items-center gap-1" for="projStatusOpen">
                    <i class="bx bx-lock-open-alt"></i> Open (Penginputan Proyeksi Dibuka)
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="projectionStatus" id="projStatusClosed" value="closed" wire:model="projectionStatus">
                  <label class="form-check-label fw-semibold text-danger d-flex align-items-center gap-1" for="projStatusClosed">
                    <i class="bx bx-lock-alt"></i> Close (Penginputan Proyeksi Ditutup)
                  </label>
                </div>
              </div>
              @error('projectionStatus')
                <div class="text-danger small mt-1">{{ $message }}</div>
              @enderror
              <div class="form-text text-muted mt-2">
                Jika diset ke <strong>Close</strong>, pengguna tidak dapat menambah, mengedit, atau mengunggah data proyeksi secara massal.
              </div>
            </div>

            <hr class="my-4">

            <h5 class="mb-3">Pengaturan Validasi Proyeksi</h5>
            <div class="mb-4">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="allowProjectionExceedBudgetInput" wire:model="allowProjectionExceedBudget">
                <label class="form-check-label fw-semibold" for="allowProjectionExceedBudgetInput">
                  Izinkan total akumulasi proyeksi melebihi anggaran RKAP
                </label>
              </div>
              <div class="form-text text-muted">
                Jika diaktifkan, pengguna dapat mengisi dan menyimpan proyeksi bulanan maupun tahunan dengan total nilai yang melebihi pagu anggaran RKAP yang disetujui.
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="bx bx-save me-1"></i> {{ __('Simpan Pengaturan') }}
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-12">
      <div class="card mb-4">
        <h5 class="card-header">Simulasi Batas Waktu Penginputan (Tahun {{ $currentYear }})</h5>
        <div class="table-responsive text-nowrap">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Bulan Realisasi</th>
                <th>Batas Pengisian</th>
                <th class="text-center">{{ __('Status') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($monthData as $data)
                <tr>
                  <td>
                    <span class="fw-bold">{{ $data['name'] }}</span>
                  </td>
                  <td>
                    <span>{{ $data['closing_date'] }}</span>
                  </td>
                  <td class="text-center">
                    <span class="badge {{ $data['statusClass'] }}">{{ $data['statusLabel'] }}</span>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
