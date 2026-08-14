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
                @php
                  $userBureau = auth()->user()->bureau;
                  $groupedBureaus = $targetBureaus->groupBy(fn($b) => $b->department->name ?? 'Lainnya');
                @endphp
                @foreach($groupedBureaus as $deptName => $bureaus)
                  @php
                    $isSameDept = ($userBureau && $bureaus->first()->department_id === $userBureau->department_id);
                  @endphp
                  <optgroup label="{{ $deptName }} ({{ $isSameDept ? __('Departemen Anda') : __('Departemen Lain') }})">
                    @foreach($bureaus as $tb)
                    <option value="{{ $tb->id }}">
                      {{ $tb->code }} — {{ $tb->name }}
                    </option>
                    @endforeach
                  </optgroup>
                @endforeach
              </select>
              @error('targetBureauId')
              <div class="invalid-feedback small">{{ $message }}</div>
              @enderror

              @if($selectedTargetBureau && $userBureau)
                @php
                  $isCrossDept = ($selectedTargetBureau->department_id !== $userBureau->department_id);
                @endphp
                <div class="mt-2">
                  @if($isCrossDept)
                  <div class="alert alert-info py-2 px-3 mb-0 fs-7">
                    <div class="fw-bold mb-1"><i class="bx bx-git-branch me-1"></i>{{ __('Transfer Antar Departemen (Satu Direktorat)') }}</div>
                    <ol class="ps-3 mb-0 text-muted">
                      <li>{{ __('Approval Kepala Departemen Pengusul') }} ({{ $userBureau->department->name ?? 'Dept Pengusul' }})</li>
                      <li>{{ __('Approval Kepala Departemen Penerima') }} ({{ $selectedTargetBureau->department->name ?? 'Dept Penerima' }})</li>
                      <li>{{ __('Penerimaan & Approval Kepala Biro Tujuan') }} ({{ $selectedTargetBureau->name }})</li>
                    </ol>
                  </div>
                  @else
                  <div class="alert alert-success py-2 px-3 mb-0 fs-7">
                    <div class="fw-bold mb-1"><i class="bx bx-check-circle me-1"></i>{{ __('Transfer Antar Biro (Satu Departemen)') }}</div>
                    <small class="text-muted">{{ __('Approval langsung oleh Kepala Biro Penerima (:bureau).', ['bureau' => $selectedTargetBureau->name]) }}</small>
                  </div>
                  @endif
                </div>
              @endif
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
            <h5 class="card-title mb-0 fs-6 fw-bold"><i class="bx bx-list-check me-1 text-primary"></i>{{ __('Pilih Kegiatan & Nominal Budget') }}</h5>
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
                    <th>{{ __('Kegiatan & Budget Awal') }}</th>
                    <th style="width: 260px;">{{ __('Nominal Budget Ditransfer') }}</th>
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
                            $q->whereIn('status', \App\Enums\BudgetTransferStatus::pendingStatuses());
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
                          <span class="badge bg-label-secondary" wire:click="setFullBudget({{ $bi->id }}, {{ $bi->total_price }})" title="Klik untuk isi nominal penuh">
                            <i class="bx bx-wallet me-1"></i>Budget Awal: <strong>Rp {{ number_format($bi->total_price, 0, ',', '.') }}</strong>
                          </span>
                        </div>
                        @if($bi->remarks)
                        <small class="text-muted d-block mt-1">{{ $bi->remarks }}</small>
                        @endif
                        @if($isPending)
                        <span class="badge bg-label-warning mt-1 fs-7">{{ __('Sedang dalam proses transfer lain') }}</span>
                        @endif
                      </label>
                    </td>
                    <td style="width: 260px;" class="align-middle">
                      <div x-data="{
                          isFull: false,
                          displayValue: '',
                          fullAmount: {{ $bi->total_price }},
                          init() {
                              this.updateDisplay($wire.get('transferAmounts.{{ $bi->id }}'));
                              $watch('$wire.transferAmounts.{{ $bi->id }}', val => {
                                  this.updateDisplay(val);
                                  this.isFull = (val !== null && val !== undefined && parseFloat(val) === this.fullAmount);
                              });
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
                                  this.isFull = (num === this.fullAmount);
                              } else {
                                  $wire.set('transferAmounts.{{ $bi->id }}', '');
                                  this.displayValue = '';
                                  this.isFull = false;
                              }
                          },
                          toggleFull(e) {
                              if (e.target.checked) {
                                  $wire.set('selectedItems.{{ $bi->id }}', true);
                                  $wire.set('transferAmounts.{{ $bi->id }}', this.fullAmount);
                                  this.displayValue = this.fullAmount.toLocaleString('id-ID');
                                  this.isFull = true;
                              } else {
                                  $wire.set('transferAmounts.{{ $bi->id }}', false);
                                  this.isFull = false;
                              }
                          }
                      }" wire:key="input-container-{{ $bi->id }}">
                        <div class="input-group input-group-sm">
                          <span class="input-group-text fw-bold">Rp</span>
                          <input type="text"
                            class="form-control form-control-sm fw-semibold text-primary"
                            :value="displayValue"
                            @input="onInput($event)"
                            placeholder="Nominal Budget"
                            @disabled(!$isSelected || $isPending || $isWpLocked)>
                        </div>
                        <div class="form-check mt-1 mb-0">
                          <input class="form-check-input" type="checkbox"
                            id="full-check-{{ $bi->id }}"
                            :checked="isFull"
                            @change="toggleFull($event)"
                            @disabled($isPending || $isWpLocked)>
                          <label class="form-check-label small text-muted cursor-pointer fs-7" for="full-check-{{ $bi->id }}">
                            {{ __('Transfer 100% nominal') }}
                          </label>
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
