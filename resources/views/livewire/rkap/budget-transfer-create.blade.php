<div class="container-xxl flex-grow-1 container-p-y">
  <div class="py-3 mb-4">
    <div class="d-flex align-items-center">
      <h4 class="mb-0">
        <span class="text-muted fw-light">RKAP / <a href="{{ route('rkap-budget-transfers') }}">{{ __('Transfer Budget') }}</a> /</span> {{ __('Ajukan Transfer') }}
      </h4>
    </div>
  </div>

  @if (session()->has('error'))
  <div class="alert alert-danger alert-dismissible" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
  @endif

  <form wire:submit.prevent="submit">
    <div class="row">
      {{-- Form Configurations --}}
      <div class="col-md-4">
        <div class="card mb-4">
          <div class="card-header border-bottom">
            <h5 class="card-title mb-0">{{ __('Konfigurasi Transfer') }}</h5>
          </div>
          <div class="card-body pt-4">
            <div class="mb-3">
              <label class="form-label fw-semibold">{{ __('Periode RKAP') }}</label>
              <select class="form-select @error('periodId') is-invalid @enderror" wire:model.live="periodId">
                <option value="">-- {{ __('Pilih Periode') }} --</option>
                @foreach($periods as $p)
                <option value="{{ $p->id }}">{{ $p->title }}</option>
                @endforeach
              </select>
              @error('periodId')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">{{ __('Biro Tujuan') }}</label>
              <select class="form-select @error('targetBureauId') is-invalid @enderror" wire:model.live="targetBureauId">
                <option value="">-- {{ __('Pilih Biro Tujuan') }} --</option>
                @foreach($targetBureaus as $tb)
                <option value="{{ $tb->id }}">{{ $tb->code }} — {{ $tb->name }}</option>
                @endforeach
              </select>
              @error('targetBureauId')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">{{ __('Catatan / Keterangan') }}</label>
              <textarea class="form-control" rows="3" placeholder="{{ __('Alasan transfer budget...') }}" wire:model="notes"></textarea>
              @error('notes')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="d-flex justify-content-between border-top pt-3">
              <a href="{{ route('rkap-budget-transfers') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
              <button type="submit" class="btn btn-primary">{{ __('Submit Pengajuan') }}</button>
            </div>
          </div>
        </div>
      </div>

      {{-- Work Plans list to choose --}}
      <div class="col-md-8">
        <div class="card mb-4">
          <div class="card-header border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">{{ __('Pilih Program Kerja & Kegiatan') }}</h5>
            @if($periodId && $workPlans->isNotEmpty())
            <span class="badge bg-primary">{{ count($selectedWorkPlans) }} {{ __('Dipilih') }}</span>
            @endif
          </div>
          <div class="card-body pt-4">
            @if(!$periodId)
            <div class="text-center py-5 text-muted">
              <i class="bx bx-calendar fs-1 mb-2"></i>
              <p>{{ __('Silakan pilih Periode RKAP terlebih dahulu untuk memuat daftar program kerja.') }}</p>
            </div>
            @elseif($workPlans->isEmpty())
            <div class="text-center py-5 text-muted">
              <i class="bx bx-info-circle fs-1 mb-2"></i>
              <p>{{ __('Tidak ditemukan program kerja yang disetujui (Approved) untuk periode terpilih.') }}</p>
            </div>
            @else
            <div class="table-responsive text-nowrap">
              <table class="table table-hover align-middle">
                <thead class="table-light">
                  <tr>
                    <th class="w-px-40"></th>
                    <th>{{ __('Program Kerja / Kegiatan') }}</th>
                    <th>{{ __('Total Anggaran') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($workPlans as $wp)
                  @php
                  $isLocked = $wp->isLockedForTransfer();
                  $wpTotal = $wp->budgetItems->sum('total_price');
                  @endphp
                  <tr class="{{ $isLocked ? 'table-light text-muted' : '' }}">
                    <td>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                          wire:model.live="selectedWorkPlans.{{ $wp->id }}"
                          id="wp-check-{{ $wp->id }}"
                          @disabled($isLocked)>
                      </div>
                    </td>
                    <td>
                      <label class="form-check-label d-block cursor-pointer" for="wp-check-{{ $wp->id }}">
                        @if($wp->workPlan)
                        <div class="small text-muted mb-1">
                          <i class="bx bx-briefcase me-1"></i><strong>{{ __('Program Kerja:') }}</strong> {{ $wp->workPlan->code }} — {{ $wp->workPlan->title }}
                        </div>
                        @endif
                        <strong class="text-dark"><i class="bx bx-task me-1"></i>{{ __('Kegiatan:') }} {{ $wp->program_code }} — {{ $wp->program_name }}</strong>
                        @if($wp->description)
                        <small class="text-muted d-block rkap-text-truncate">{{ $wp->description }}</small>
                        @endif
                        @if($isLocked)
                        <span class="badge bg-label-warning mt-1">{{ __('Sedang dalam proses transfer lain') }}</span>
                        @endif
                      </label>
                    </td>
                    <td><strong class="text-primary">Rp {{ number_format($wpTotal, 0, ',', '.') }}</strong></td>
                  </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            @endif
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
