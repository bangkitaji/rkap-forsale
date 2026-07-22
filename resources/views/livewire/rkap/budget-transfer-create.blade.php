<div class="container-xxl flex-grow-1 container-p-y">
  <div class="py-3 mb-4">
    <div class="d-flex align-items-center">
      <h4 class="mb-0">
        <span class="text-muted fw-light">RKAP / <a href="{{ route('rkap-budget-transfers') }}">{{ __('Transfer Budget') }}</a> /</span> {{ __('Ajukan Transfer Partial') }}
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
    <div class="row g-4">
      {{-- Form Configurations --}}
      <div class="col-lg-4">
        <div class="card shadow-sm sticky-top" style="top: 1rem; z-index: 10;">
          <div class="card-header border-bottom py-3">
            <h5 class="card-title mb-0 fs-6 fw-bold"><i class="bx bx-cog me-1 text-primary"></i>{{ __('Konfigurasi Transfer') }}</h5>
          </div>
          <div class="card-body pt-3">
            <div class="mb-3">
              <label class="form-label fw-semibold small">{{ __('Periode RKAP') }}</label>
              <select class="form-select form-select-sm @error('periodId') is-invalid @enderror" wire:model.live="periodId">
                <option value="">-- {{ __('Pilih Periode') }} --</option>
                @foreach($periods as $p)
                <option value="{{ $p->id }}">{{ $p->title }}</option>
                @endforeach
              </select>
              @error('periodId')
              <div class="invalid-feedback small">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold small">{{ __('Biro Tujuan') }}</label>
              <select class="form-select form-select-sm @error('targetBureauId') is-invalid @enderror" wire:model.live="targetBureauId">
                <option value="">-- {{ __('Pilih Biro Tujuan') }} --</option>
                @foreach($targetBureaus as $tb)
                <option value="{{ $tb->id }}">{{ $tb->code }} — {{ $tb->name }}</option>
                @endforeach
              </select>
              @error('targetBureauId')
              <div class="invalid-feedback small">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold small">{{ __('Catatan / Keterangan') }}</label>
              <textarea class="form-control form-control-sm" rows="3" placeholder="{{ __('Alasan transfer budget...') }}" wire:model="notes"></textarea>
              @error('notes')
              <div class="invalid-feedback small">{{ $message }}</div>
              @enderror
            </div>

            <div class="d-flex justify-content-between border-top pt-3">
              <a href="{{ route('rkap-budget-transfers') }}" class="btn btn-sm btn-outline-secondary">{{ __('Batal') }}</a>
              <button type="submit" class="btn btn-sm btn-primary">{{ __('Submit Pengajuan') }}</button>
            </div>
          </div>
        </div>
      </div>

      {{-- Work Plans & Budget Items list --}}
      <div class="col-lg-8">
        <div class="card shadow-sm">
          <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fs-6 fw-bold"><i class="bx bx-list-check me-1 text-primary"></i>{{ __('Pilih Kegiatan & Nominal Transfer') }}</h5>
            @if($periodId && $workPlans->isNotEmpty())
            @php
              $selectedCount = count(array_filter($selectedItems));
            @endphp
            <span class="badge bg-primary fs-7">{{ $selectedCount }} {{ __('Kegiatan Dipilih') }}</span>
            @endif
          </div>
          <div class="card-body p-0">
            @if(!$periodId)
            <div class="text-center py-5 text-muted">
              <i class="bx bx-calendar fs-1 mb-2"></i>
              <p class="mb-0">{{ __('Silakan pilih Periode RKAP terlebih dahulu untuk memuat daftar program kerja.') }}</p>
            </div>
            @elseif($workPlans->isEmpty())
            <div class="text-center py-5 text-muted">
              <i class="bx bx-info-circle fs-1 mb-2"></i>
              <p class="mb-0">{{ __('Tidak ditemukan program kerja yang disetujui (Approved) untuk periode terpilih.') }}</p>
            </div>
            @else
            <div class="table-responsive" style="max-height: calc(100vh - 230px); overflow-y: auto;">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light sticky-top shadow-xs" style="z-index: 2;">
                  <tr>
                    <th class="w-px-40 text-center"></th>
                    <th>{{ __('Program Kerja / Kegiatan & Budget Awal') }}</th>
                    <th style="width: 280px;">{{ __('Nominal Transfer Usulan') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($workPlans as $wp)
                  @php
                    $isWpLocked = $wp->isLockedForTransfer();
                  @endphp
                  <tr class="table-secondary fw-semibold">
                    <td colspan="3" class="py-2 px-3">
                      <i class="bx bx-briefcase me-1 text-primary"></i>
                      {{ $wp->program_code }} — {{ $wp->program_name }}
                      @if($wp->description)
                        <span class="fw-normal text-muted ms-2">({{ $wp->description }})</span>
                      @endif
                    </td>
                  </tr>

                  @foreach($wp->budgetItems as $bi)
                  @php
                    $isSelected = !empty($selectedItems[$bi->id]);
                    $isPending = \App\Models\BudgetTransferItem::where('rkap_budget_item_id', $bi->id)
                        ->whereHas('transfer', function ($q) {
                            $q->where('status', \App\Enums\BudgetTransferStatus::Pending->value);
                        })->exists();
                  @endphp
                  <tr class="{{ $isPending ? 'table-light text-muted' : '' }}">
                    <td class="text-center">
                      <div class="form-check d-flex justify-content-center">
                        <input class="form-check-input" type="checkbox"
                          wire:model.live="selectedItems.{{ $bi->id }}"
                          wire:click="toggleSelectItem({{ $bi->id }}, {{ $bi->total_price }})"
                          id="bi-check-{{ $bi->id }}"
                          @disabled($isPending || $isWpLocked)>
                      </div>
                    </td>
                    <td class="text-wrap py-2">
                      <label class="form-check-label d-block cursor-pointer" for="bi-check-{{ $bi->id }}">
                        <span class="badge bg-label-info mb-1"><i class="bx bx-hash me-1"></i>{{ $bi->account_code }}</span>
                        <strong class="text-dark d-block"><i class="bx bx-task me-1"></i>{{ $bi->description }}</strong>
                        <div class="mt-1 d-flex align-items-center gap-2">
                          <span class="badge bg-label-secondary"><i class="bx bx-wallet me-1"></i>Budget Awal: <strong>Rp {{ number_format($bi->total_price, 0, ',', '.') }}</strong></span>
                        </div>
                        @if($bi->remarks)
                        <small class="text-muted d-block mt-1">{{ $bi->remarks }}</small>
                        @endif
                        @if($isPending)
                        <span class="badge bg-label-warning mt-1 fs-7">{{ __('Sedang dalam proses transfer lain') }}</span>
                        @endif
                      </label>
                    </td>
                    <td style="width: 280px;" class="align-middle">
                      <div x-data="{
                          displayValue: '',
                          init() {
                              this.updateDisplay($wire.get('transferAmounts.{{ $bi->id }}'));
                              $watch('$wire.transferAmounts.{{ $bi->id }}', val => this.updateDisplay(val));
                          },
                          updateDisplay(val) {
                              if (val === null || val === undefined || val === '') {
                                  this.displayValue = '';
                                  return;
                              }
                              let clean = String(val).replace(/\D/g, '');
                              this.displayValue = clean ? parseInt(clean, 10).toLocaleString('id-ID') : '';
                          },
                          onInput(e) {
                              let clean = e.target.value.replace(/\D/g, '');
                              if (clean) {
                                  let num = parseInt(clean, 10);
                                  $wire.set('transferAmounts.{{ $bi->id }}', num);
                                  this.displayValue = num.toLocaleString('id-ID');
                              } else {
                                  $wire.set('transferAmounts.{{ $bi->id }}', '');
                                  this.displayValue = '';
                              }
                          }
                      }" wire:key="input-container-{{ $bi->id }}">
                        <div class="input-group">
                          <span class="input-group-text fw-bold">Rp</span>
                          <input type="text"
                            class="form-control fw-semibold text-primary"
                            :value="displayValue"
                            @input="onInput($event)"
                            placeholder="0"
                            @disabled(!$isSelected || $isPending || $isWpLocked)>
                        </div>
                      </div>
                    </td>
                  </tr>
                  @endforeach

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
