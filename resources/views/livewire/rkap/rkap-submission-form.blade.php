<div x-data="{ isDirty: false, isSubmitting: false, showToast: false, toastMessage: '', toastType: 'success' }" @input="isDirty = true" @change="isDirty = true"
  @form-saved.window="toastMessage = $event.detail.message || 'Draf RKAP berhasil disimpan.'; toastType = 'success'; showToast = true; isDirty = false; setTimeout(() => showToast = false, 5000)"
  @work-plan-duplicate-rejected.window="toastMessage = 'Program Kerja ini sudah dipilih pada kartu lain. Silakan pilih Program Kerja yang berbeda.'; toastType = 'warning'; showToast = true; setTimeout(() => showToast = false, 5000)"
  @activity-duplicate-rejected.window="toastMessage = 'Kegiatan ini sudah dipilih di baris lain dalam Program Kerja yang sama. Silakan pilih kegiatan yang berbeda.'; toastType = 'warning'; showToast = true; setTimeout(() => showToast = false, 5000)"
  @beforeunload.window="if(isDirty && !isSubmitting) { $event.returnValue = 'Ada perubahan yang belum disimpan.'; return 'Ada perubahan yang belum disimpan.'; }">
  <style>
    .bg-group-alt {
      background-color: #f8fafc !important;
    }

    .bg-group-alt td {
      background-color: inherit !important;
    }

    .table-group-header {
      font-weight: 600;
    }

    .activity-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      transition: all 0.2s ease-in-out;
    }

    .activity-card:hover {
      box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
      border-color: #cbd5e1;
    }

    /* Custom CSS Tooltip styling */
    .has-tooltip {
      position: relative;
      cursor: help;
    }
    .custom-tooltip-content {
      visibility: hidden;
      width: 250px;
      background-color: #2f3349;
      color: #ffffff;
      text-align: left;
      border-radius: 6px;
      padding: 10px;
      position: absolute;
      z-index: 1080;
      top: 110%; /* Position below the element */
      bottom: auto;
      left: 50%;
      transform: translateX(-50%);
      opacity: 0;
      transition: opacity 0.2s ease-in-out;
      box-shadow: 0 4px 12px rgba(0,0,0,0.25);
      font-size: 0.72rem;
      line-height: 1.4;
      pointer-events: none; /* Make sure it doesn't block mouse movements */
      font-weight: normal;
    }
    .custom-tooltip-content::after {
      content: "";
      position: absolute;
      bottom: 100%; /* At the top of the tooltip */
      top: auto;
      left: 50%;
      margin-left: -5px;
      border-width: 5px;
      border-style: solid;
      border-color: transparent transparent #2f3349 transparent;
    }
    .has-tooltip:hover .custom-tooltip-content {
      visibility: visible;
      opacity: 1;
    }
    .tooltip-align-right {
      right: 0 !important;
      left: auto !important;
      transform: none !important;
    }
    .tooltip-align-right::after {
      left: auto !important;
      right: 15px !important;
      margin-left: 0 !important;
    }
    .table-responsive, .card, .card-header {
      overflow: visible !important;
    }
  </style>
  <div class="py-3 mb-4">
    <div class="d-flex justify-content-between align-items-center">
      <h4 class="mb-0">
        <span class="text-muted fw-light">RKAP /
          <a href="{{ route('rkap-submissions') }}" class="text-muted text-decoration-none">Pengajuan</a> /
        </span>
        {{ $submissionId ? 'Edit RKAP' : 'Buat RKAP' }}
      </h4>
      <div class="text-muted small">
        <i class="bx bx-calendar me-1"></i> {{ $period->title ?? '' }}
      </div>
    </div>
  </div>

  {{-- Success/Notification Toast --}}
  <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
    <div x-show="showToast"
      x-transition:enter="transition ease-out duration-300"
      x-transition:enter-start="opacity-0 translate-y-2"
      x-transition:enter-end="opacity-100 translate-y-0"
      x-transition:leave="transition ease-in duration-200"
      x-transition:leave-start="opacity-100 translate-y-0"
      x-transition:leave-end="opacity-0 translate-y-2"
      class="bs-toast toast show text-white"
      :class="'bg-' + toastType"
      role="alert"
      aria-live="assertive"
      aria-atomic="true"
      style="display: none;">
      <div class="toast-header text-white" :class="'bg-' + toastType">
        <i class="bx me-2 text-white" :class="toastType === 'success' ? 'bx-check-circle' : 'bx-info-circle'"></i>
        <div class="me-auto fw-semibold">Berhasil</div>
        <button type="button" class="btn-close btn-close-white" @click="showToast = false" aria-label="Close"></button>
      </div>
      <div class="toast-body" x-text="toastMessage"></div>
    </div>
  </div>
  {{-- Error Toast --}}
  @if ($errors->any())
  <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;" wire:key="error-toast-container-{{ microtime(true) }}">
    <div x-data="{ show: true }"
      x-show="show"
      x-init="setTimeout(() => show = false, 7000)"
      x-transition:enter="transition ease-out duration-300"
      x-transition:enter-start="opacity-0 translate-y-2"
      x-transition:enter-end="opacity-100 translate-y-0"
      x-transition:leave="transition ease-in duration-200"
      x-transition:leave-start="opacity-100 translate-y-0"
      x-transition:leave-end="opacity-0 translate-y-2"
      class="bs-toast toast show bg-danger text-white"
      role="alert"
      aria-live="assertive"
      aria-atomic="true"
      style="display: none;">
      <div class="toast-header bg-danger text-white">
        <i class="bx bx-x-circle me-2 text-white"></i>
        <div class="me-auto fw-semibold">Gagal Menyimpan</div>
        <button type="button" class="btn-close btn-close-white" @click="show = false" aria-label="Close"></button>
      </div>
      <div class="toast-body">
        <ul class="mb-0 ps-3">
          @foreach ($errors->all() as $error)
          <li>{{ $error }}</li> @endforeach
        </ul>
      </div>
    </div>
  </div>
  @endif

  {{-- Grand Total Banner --}}
  <div class="card mb-4 bg-primary text-white">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <div class="small opacity-75">Total Anggaran Keseluruhan</div>
        <div class="fs-3 fw-bold">Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</div>
      </div>
      <i class="bx bx-money bx-lg opacity-50"></i>
    </div>
  </div>

  {{-- Notes --}}
  <div class="card mb-4">
    <div class="card-header"><strong>Catatan Umum</strong></div>
    <div class="card-body">
      <textarea class="form-control" wire:model.live="notes" rows="2"
        placeholder="Catatan atau keterangan umum untuk pengajuan ini..."></textarea>
    </div>
  </div>

  {{-- Work Plans Loop --}}
  @foreach ($workPlans as $wpIdx => $wp)
  @php
  $rowWorkPlanOptions = $this->getWorkPlanOptionsForIndex($wpIdx);
  $selectedWorkPlan = $workPlanOptions->firstWhere('id', $wp['work_plan_id']);
  @endphp

  <div class="card mb-4 border-start border-primary border-3" wire:key="wp-card-{{ $wpIdx }}">
    {{-- ===== Card Header: Program Kerja select ===== --}}
    <div class="card-header border-bottom">
      <div class="d-flex align-items-start gap-3">
        {{-- Program Kerja Dropdown --}}
        <div class="d-flex flex-column gap-1" style="width: 50%; min-width: 250px;">
          <div class="d-flex align-items-center gap-2">
            <i class="bx bx-list-ul text-primary flex-shrink-0"></i>
            <strong class="text-nowrap">Program Kerja {{ $wpIdx + 1 }}</strong>
          </div>

          {{-- Searchable Program Kerja select --}}
          <div x-data="{
              open: false,
              search: '{{ $selectedWorkPlan ? $selectedWorkPlan->code . ' — ' . $selectedWorkPlan->title : '' }}',
          }" class="position-relative"
            wire:key="wp-{{ $wpIdx }}-wp-select-{{ $wp['work_plan_id'] ?? 'null' }}">

            <div class="input-group">
              <input type="text"
                class="form-control @error('workPlans.' . $wpIdx . '.work_plan_id') is-invalid @enderror"
                placeholder="Cari program kerja..." x-model="search" @focus="open = true" @click.outside="open = false"
                @input="open = true" autocomplete="off" id="wp-search-{{ $wpIdx }}">
              @if ($wp['work_plan_id'])
              <button type="button" class="btn btn-outline-secondary"
                wire:click="$set('workPlans.{{ $wpIdx }}.work_plan_id', null)" @click="search = ''"
                title="Hapus pilihan">
                <i class="bx bx-x"></i>
              </button>
              @endif
            </div>
            @error('workPlans.' . $wpIdx . '.work_plan_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror

            {{-- Hidden select --}}
            <select wire:model.live="workPlans.{{ $wpIdx }}.work_plan_id" class="d-none"
              id="wp-select-{{ $wpIdx }}">
              <option value=""></option>
              @foreach ($rowWorkPlanOptions as $wpo)
              <option value="{{ $wpo->id }}">{{ $wpo->code }} — {{ $wpo->title }}</option>
              @endforeach
            </select>

            {{-- Dropdown options --}}
            <div x-show="open" x-cloak class="position-absolute bg-white border rounded shadow-sm w-100 mt-1"
              style="z-index: 1050; max-height: 220px; overflow-y: auto;">
              @forelse($rowWorkPlanOptions as $wpo)
              <div
                class="px-3 py-2 cursor-pointer dropdown-item small {{ $wp['work_plan_id'] == $wpo->id ? 'bg-primary text-white' : '' }}"
                x-show="'{{ strtolower($wpo->code . ' ' . $wpo->title) }}'.includes(search.toLowerCase())"
                @click="
                                        $wire.set('workPlans.{{ $wpIdx }}.work_plan_id', {{ $wpo->id }});
                                        search = '{{ $wpo->code }} — {{ $wpo->title }}';
                                        open = false;
                                    ">
                <span class="fw-semibold text-primary">{{ $wpo->code }}</span>
                <span class="ms-1">{{ $wpo->title }}</span>
              </div>
              @empty
              <div class="px-3 py-2 text-muted small">Tidak ada data program kerja.</div>
              @endforelse
            </div>
          </div>
        </div>

        {{-- Subtotal and Delete work plan card --}}
        <div class="flex-shrink-0 ms-auto d-flex flex-column justify-content-end align-items-end">
          @php
          $wpSubtotal = 0;
          foreach ($wp['activities'] ?? [] as $act) {
          foreach ($act['budget_items'] ?? [] as $bi) {
          $qty2 = (!empty($bi['unit_2'])) ? (float) ($bi['quantity_2'] ?? 1) : 1;
          $wpSubtotal += ($bi['quantity'] ?? 0) * $qty2 * ($bi['unit_price'] ?? 0);
          }
          }
          @endphp
          <span class="text-muted small fw-semibold mb-1">Subtotal Program</span>
          <span class="text-muted small fw-bold text-primary fs-6 has-tooltip">
            Rp {{ number_format($wpSubtotal, 0, ',', '.') }}
            <span class="custom-tooltip-content tooltip-align-right">
              @php
                $prevWpId = $wp['work_plan_id'] ?? null;
                $prevProgramData = ($prevWpId && isset($prevData['map']['programs'][$prevWpId])) ? $prevData['map']['programs'][$prevWpId] : null;
                $prevPeriod = $prevData['period'] ?? '-';
              @endphp
              @if ($prevProgramData)
                <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya ({{ $prevPeriod }})</div>
                <div class="row text-center">
                  <div class="col-6 border-end">
                    <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                    <div class="fw-bold text-white">Rp {{ number_format($prevProgramData['budget'], 0, ',', '.') }}</div>
                  </div>
                  <div class="col-6">
                    <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                    <div class="fw-bold text-white">Rp {{ number_format($prevProgramData['realization'], 0, ',', '.') }}</div>
                  </div>
                </div>
              @else
                <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
              @endif
            </span>
          </span>
          @if (count($workPlans) > 1)
          <button type="button" wire:click="removeWorkPlan({{ $wpIdx }})"
            class="btn btn-sm btn-outline-danger" title="Hapus program kerja">
            <i class="bx bx-trash me-1"></i> Hapus Program
          </button>
          @endif
        </div>
      </div>
    </div>

    {{-- ===== Card Body: Activities List ===== --}}
    <div class="card-body bg-light-gray p-3">
      @foreach ($wp['activities'] as $actIdx => $act)
      @php
      $selectedActivity = $act['activity_id'] ? \App\Models\Activity::find($act['activity_id']) : null;
      @endphp

      <div class="activity-card p-3 mb-3" wire:key="wp-{{ $wpIdx }}-act-card-{{ $actIdx }}">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-label-primary rounded-circle p-2"><i class="bx bx-task"></i></span>
            <h6 class="mb-0 fw-bold">Kegiatan {{ $actIdx + 1 }}</h6>
          </div>
          @if (count($wp['activities']) > 1)
          <button type="button" wire:click="removeActivity({{ $wpIdx }}, {{ $actIdx }})"
            class="btn btn-xs btn-outline-danger" title="Hapus Kegiatan">
            <i class="bx bx-trash me-1"></i> Hapus Kegiatan
          </button>
          @endif
        </div>

        <div class="row g-3 mb-3">
          {{-- Nama Kegiatan selection --}}
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Nama Kegiatan</label>
            @php
            $activities = $this->getActivitiesForIndex($wpIdx, $actIdx);
            @endphp
            <div x-data="{
                  open: false,
                  search: '{{ $selectedActivity ? $selectedActivity->code . ' — ' . $selectedActivity->title : '' }}',
              }" class="position-relative" @click.outside="open = false"
              wire:key="wp-{{ $wpIdx }}-act-select-{{ $wp['work_plan_id'] ?? 'null' }}-{{ $actIdx }}-{{ $act['activity_id'] ?? 'null' }}">

              <div class="input-group">
                <input type="text"
                  class="form-control form-control-sm @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.activity_id') is-invalid @enderror"
                  placeholder="Cari kegiatan..." x-model="search" @focus="open = true" @input="open = true"
                  autocomplete="off" id="act-search-{{ $wpIdx }}-{{ $actIdx }}">
                @if ($act['activity_id'])
                <button type="button" class="btn btn-sm btn-outline-secondary"
                  wire:click="$set('workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.activity_id', null)"
                  @click="search = ''" title="Hapus pilihan">
                  <i class="bx bx-x"></i>
                </button>
                @endif
              </div>
              @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.activity_id')
              <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror

              {{-- Hidden select --}}
              <select wire:model.live="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.activity_id"
                class="d-none" id="act-select-{{ $wpIdx }}-{{ $actIdx }}">
                <option value=""></option>
                @foreach ($activities as $a)
                <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->title }}</option>
                @endforeach
              </select>

              {{-- Dropdown options --}}
              <div x-show="open" x-cloak class="position-absolute bg-white border rounded shadow-sm w-100 mt-1"
                style="z-index: 1050; max-height: 220px; overflow-y: auto;">
                @forelse($activities as $a)
                <div
                  class="px-3 py-2 cursor-pointer dropdown-item small {{ $act['activity_id'] == $a->id ? 'bg-primary text-white' : '' }}"
                  x-show="'{{ strtolower($a->code . ' ' . $a->title) }}'.includes(search.toLowerCase())"
                  @click="
                                            $wire.set('workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.activity_id', {{ $a->id }});
                                            search = '{{ $a->code }} — {{ $a->title }}';
                                            open = false;
                                        ">
                  <div class="d-flex flex-column gap-1">
                    <span class="fw-semibold text-primary">{{ $a->code }}</span>
                    <span class="text-secondary" style="font-size: 0.85rem;">{{ $a->title }}</span>
                  </div>
                </div>
                @empty
                <div class="px-3 py-2 text-muted small">Tidak ada data kegiatan.</div>
                @endforelse
              </div>
            </div>
          </div>

          {{-- Subtotal Kegiatan --}}
          <div class="col-md-6 d-flex flex-column justify-content-end align-items-end">
            @php
            $actSubtotal = 0;
            foreach ($act['budget_items'] ?? [] as $bi) {
            $qty2 = (!empty($bi['unit_2'])) ? (float) ($bi['quantity_2'] ?? 1) : 1;
            $actSubtotal += ($bi['quantity'] ?? 0) * $qty2 * ($bi['unit_price'] ?? 0);
            }
            @endphp
            <span class="text-muted small fw-semibold mb-1">Subtotal Kegiatan</span>
            <span class="text-muted small fw-bold text-primary fs-6 has-tooltip">
              Rp {{ number_format($actSubtotal, 0, ',', '.') }}
              <span class="custom-tooltip-content tooltip-align-right">
                @php
                  $prevWpId = $wp['work_plan_id'] ?? null;
                  $prevActId = $act['activity_id'] ?? null;
                  $actKey = ($prevWpId && $prevActId) ? "{$prevWpId}-{$prevActId}" : null;
                  $prevActivityData = ($actKey && isset($prevData['map']['activities'][$actKey])) ? $prevData['map']['activities'][$actKey] : null;
                  $prevPeriod = $prevData['period'] ?? '-';
                @endphp
                @if ($prevActivityData)
                  <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya ({{ $prevPeriod }})</div>
                  <div class="row text-center">
                    <div class="col-6 border-end">
                      <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                      <div class="fw-bold text-white">Rp {{ number_format($prevActivityData['budget'], 0, ',', '.') }}</div>
                    </div>
                    <div class="col-6">
                      <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                      <div class="fw-bold text-white">Rp {{ number_format($prevActivityData['realization'], 0, ',', '.') }}</div>
                    </div>
                  </div>
                @else
                  <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
                @endif
              </span>
            </span>
          </div>
        </div>

        {{-- Activity Details Grid --}}
        <div class="row g-3 mb-3">
          <div class="col-md-12">
            <label class="form-label small fw-semibold">Deskripsi / Tujuan</label>
            <textarea class="form-control form-control-sm"
              wire:model.live="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.description" rows="2"
              placeholder="Deskripsi kegiatan..."></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Target Output</label>
            <input type="text" class="form-control form-control-sm"
              wire:model.live="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.output_target"
              placeholder="Misal: 1 sistem, 100 user">
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold">Satuan</label>
            <input type="text" class="form-control form-control-sm"
              wire:model.live.debounce.300="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.unit"
              placeholder="Paket, Unit, ...">
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold">Volume</label>
            <input type="number" class="form-control form-control-sm"
              wire:model.live="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.quantity"
              min="1">
          </div>
        </div>

        {{-- Budget Items section --}}
        @if (!empty($wp['work_plan_id']) && !empty($act['activity_id']))
        <div x-data="{
                dropdownOpen: false,
                modalKey: null,
                openModal(key) { this.modalKey = key; },
                closeModal() { this.modalKey = null; }
            }" @coa-dropdown-open.window="dropdownOpen = true"
          @coa-dropdown-close.window="dropdownOpen = false" @keydown.escape.window="closeModal()">

          <div class="table-responsive" :style="dropdownOpen ? 'overflow: visible;' : ''">
            <table class="table table-sm table-bordered align-middle mb-2">
              <thead class="table-primary text-white fw-semibold">
                <tr>
                  <th style="width:30%">Uraian & Detail Belanja <span class="text-warning">*</span></th>
                  <th style="width:12%">Satuan 1 & 2</th>
                  <th style="width:8%" class="text-center">Vol 1 & 2 <span class="text-warning">*</span></th>
                  <th style="width:140px" class="text-end">Harga Satuan (Rp) <span class="text-warning">*</span></th>
                  <th style="width:160px" class="text-end">Total (Rp)</th>
                  <th style="width:120px" class="text-center">Detail</th>
                  <th style="width:2%" class="text-center"><i class="bx bx-menu"></i></th>
                </tr>
              </thead>
              <tbody>
                @php
                $groups = [];
                $currentGroup = null;
                foreach ($act['budget_items'] as $biIdx => $bi) {
                $coaId = $bi['coa_id'] ?? null;
                if ($coaId !== null && $currentGroup !== null && $currentGroup['coa_id'] === $coaId) {
                $currentGroup['items'][] = ['index' => $biIdx, 'item' => $bi];
                } else {
                if ($currentGroup !== null) {
                $groups[] = $currentGroup;
                }
                $currentGroup = [
                'coa_id' => $coaId,
                'items' => [['index' => $biIdx, 'item' => $bi]],
                ];
                }
                }
                if ($currentGroup !== null) {
                $groups[] = $currentGroup;
                }
                @endphp

                @foreach ($groups as $gIdx => $group)
                @php
                $itemCount = count($group['items']);
                $firstIdx = $group['items'][0]['index'];
                $firstBi = $group['items'][0]['item'];
                $selectedCoa = $coaOptions->firstWhere('id', $firstBi['coa_id']);
                $filteredCoas = $this->getCoaOptionsForIndex($wpIdx);
                $filteredCoasOrdered = $filteredCoas;
                if ($firstBi['coa_id'] ?? null) {
                $filteredCoasOrdered = $filteredCoas
                ->sortBy(fn($c) => $c->id === $firstBi['coa_id'] ? 0 : 1)
                ->values();
                }
                $searchLabel = '';
                if ($selectedCoa) {
                $searchLabel = $selectedCoa->code . ' — ' . $selectedCoa->title;
                } elseif (!empty($firstBi['account_code']) || !empty($firstBi['description'])) {
                $searchLabel = trim(
                ($firstBi['account_code'] ?? '') .
                (!empty($firstBi['description']) ? ' — ' . ($firstBi['description'] ?? '') : ''),
                );
                }
                @endphp

                <tr wire:key="wp-{{ $wpIdx }}-act-{{ $actIdx }}-group-{{ $gIdx }}-coa"
                  class="{{ $gIdx % 2 == 1 ? 'bg-group-alt' : '' }}">
                  <td colspan="6"
                    wire:key="coa-cell-{{ $wpIdx }}-{{ $actIdx }}-g{{ $gIdx }}-{{ $firstBi['coa_id'] ?? 'none' }}-{{ md5($searchLabel) }}"
                    x-data="{
                              open: false,
                              search: @js($searchLabel),
                              currentLabel: @js($searchLabel),
                          }"
                    x-effect="if (!open && search !== currentLabel) search = currentLabel"
                    :style="open ? 'position: relative; z-index: 1060;' : ''"
                    @click.outside="open = false; $dispatch('coa-dropdown-close')" class="border-bottom-0">
                    @php
                    $groupSubtotal = collect($group['items'])->sum(function($info) {
                    $bi = $info['item'];
                    $qty2 = (!empty($bi['unit_2'])) ? (float) ($bi['quantity_2'] ?? 1) : 1;
                    return ($bi['quantity'] ?? 0) * $qty2 * ($bi['unit_price'] ?? 0);
                    });
                    $prevWpId = $wp['work_plan_id'] ?? null;
                    $prevCode = $selectedCoa?->code ?? ($firstBi['account_code'] ?? null);
                    $prevAmount =
                    $prevWpId && $prevCode && !empty($prevData['map'][$prevWpId][$prevCode])
                    ? $prevData['map'][$prevWpId][$prevCode]
                    : null;
                    $prevPeriod = $prevData['period'] ?? null;
                    @endphp
                    <div class="position-relative">
                      <div class="input-group input-group-sm">
                        <input type="text"
                          class="form-control form-control-sm @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $firstIdx . '.coa_id') is-invalid @enderror"
                          placeholder="Cari akun/belanja..." x-model="search"
                          @focus="open = true; $dispatch('coa-dropdown-open')"
                          @input="open = true; $dispatch('coa-dropdown-open')" autocomplete="off">
                        @if ($firstBi['coa_id'])
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                          wire:click="updateGroupCoa({{ $wpIdx }}, {{ $actIdx }}, {{ $firstIdx }}, null)"
                          @click="search = ''; currentLabel = ''; open = false; $dispatch('coa-dropdown-close'); isDirty = true;"
                          title="Hapus pilihan">
                          <i class="bx bx-x"></i>
                        </button>
                        @endif
                        @php
                          $prevActId = $act['activity_id'] ?? null;
                          $coaKey = ($prevWpId && $prevActId && $prevCode) ? "{$prevWpId}-{$prevActId}-{$prevCode}" : null;
                          $prevCoaData = ($coaKey && isset($prevData['map']['coas'][$coaKey])) ? $prevData['map']['coas'][$coaKey] : null;
                          $prevPeriod = $prevData['period'] ?? '-';
                        @endphp
                        <span class="input-group-text px-2 fw-semibold text-nowrap has-tooltip"
                          style="font-size:0.78rem; background:#f0f4ff; border-color:#c9d4f5; color:#2563eb;">
                          <i class="bx bx-sum me-1" style="font-size:0.85rem;"></i>
                          Rp {{ number_format($groupSubtotal, 0, ',', '.') }}
                          <span class="custom-tooltip-content">
                            @if ($prevCoaData)
                              <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya ({{ $prevPeriod }})</div>
                              <div class="row text-center">
                                <div class="col-6 border-end">
                                  <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                                  <div class="fw-bold text-white">Rp {{ number_format($prevCoaData['budget'], 0, ',', '.') }}</div>
                                </div>
                                <div class="col-6">
                                  <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                                  <div class="fw-bold text-white">Rp {{ number_format($prevCoaData['realization'], 0, ',', '.') }}</div>
                                </div>
                              </div>
                            @else
                              <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
                            @endif
                          </span>
                        </span>
                      </div>
                      @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $firstIdx .
                      '.coa_id')
                      <div class="invalid-feedback d-block">{{ $message }}</div>
                      @enderror
                      <select
                        wire:model.live="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $firstIdx }}.coa_id"
                        class="d-none">
                        <option value=""></option>
                        @foreach ($filteredCoasOrdered as $coa)
                        <option value="{{ $coa->id }}">{{ $coa->code }} — {{ $coa->title }}
                        </option>
                        @endforeach
                      </select>
                      <div x-show="open" x-cloak
                        class="position-absolute bg-white border rounded shadow-sm w-100 mt-1"
                        style="z-index: 1050; max-height: 220px; overflow-y: auto;">
                        @foreach ($filteredCoasOrdered as $coa)
                        <div
                          class="px-3 py-2 cursor-pointer dropdown-item small {{ ($firstBi['coa_id'] ?? null) == $coa->id ? 'bg-primary text-white' : '' }}"
                          x-show="'{{ strtolower($coa->code . ' ' . $coa->title) }}'.includes(search.toLowerCase())"
                          @click="
                                                            $wire.call('updateGroupCoa', {{ $wpIdx }}, {{ $actIdx }}, {{ $firstIdx }}, {{ $coa->id }});
                                                            currentLabel = '{{ $coa->code }} — {{ $coa->title }}';
                                                            search = currentLabel;
                                                            open = false;
                                                            $dispatch('coa-dropdown-close');
                                                            isDirty = true;
                                                        ">
                          <span class="fw-semibold text-primary">{{ $coa->code }}</span>
                          <span class="ms-1">{{ $coa->title }}</span>
                        </div>
                        @endforeach
                      </div>
                    </div>
                  </td>

                  <td rowspan="{{ 1 + $itemCount }}" class="text-center align-middle border-bottom-0">
                    @php
                    $totalItemsCount = count($act['budget_items']);
                    $indices = array_column($group['items'], 'index');
                    $indicesJson = json_encode($indices);
                    @endphp
                    <button type="button"
                      wire:click="removeGroup({{ $wpIdx }}, {{ $actIdx }}, {{ $indicesJson }})"
                      @click="isDirty = true" class="btn btn-sm btn-icon btn-text-danger rounded-pill"
                      title="Hapus grup akun belanja" @if ($totalItemsCount <=$itemCount) disabled @endif>
                      <i class="bx bx-minus-circle fs-4"></i>
                    </button>
                  </td>
                </tr>

                @foreach ($group['items'] as $itemIdx => $itemInfo)
                @php
                $biIdx = $itemInfo['index'];
                $bi = $itemInfo['item'];
                $qty2 = (!empty($bi['unit_2'])) ? (float) ($bi['quantity_2'] ?? 1) : 1;
                $biTotal = ($bi['quantity'] ?? 0) * $qty2 * ($bi['unit_price'] ?? 0);
                $monthlyAllocated = array_sum($bi['monthly_distribution'] ?? []);
                $monthlyRemainder = $biTotal - $monthlyAllocated;
                $selectedMonths = $bi['distribution_months'] ?? [];
                $cashOutAllocated = array_sum($bi['cash_out_distribution'] ?? []);
                $cashOutRemainder = $biTotal - $cashOutAllocated;
                $selectedCashOutMonths = $bi['cash_out_months'] ?? [];
                $realizationAllocated = array_sum($bi['realization_distribution'] ?? []);
                $selectedRealizationMonths = $bi['realization_months'] ?? [];
                $modalKey = 'wp' . $wpIdx . '-act' . $actIdx . '-bi' . $biIdx;
                @endphp
                <tr
                  wire:key="wp-{{ $wpIdx }}-act-{{ $actIdx }}-bi-{{ $biIdx }}-detail"
                  class="{{ $gIdx % 2 == 1 ? 'bg-group-alt' : '' }}">
                  <td class="border-top-0">
                    <input type="text" class="form-control form-control-sm"
                      wire:model.live="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.remarks"
                      placeholder="Detail Belanja / Ket...">
                  </td>
                  <td class="border-top-0" style="position: relative; min-width: 120px;">
                    {{-- Satuan 1 --}}
                    <div x-data="{
                                open: false,
                                search: '{{ $bi['unit'] ?? '' }}',
                            }" class="position-relative mb-2" @click.outside="open = false"
                      wire:key="wp-{{ $wpIdx }}-act-{{ $actIdx }}-bi-{{ $biIdx }}-unit-{{ $bi['unit'] ?? 'empty' }}">

                      <input type="text" class="form-control form-control-sm" placeholder="Satuan 1"
                        x-model="search" @focus="open = true; $dispatch('coa-dropdown-open');"
                        @input="open = true; $dispatch('coa-dropdown-open'); $wire.set('workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.unit', search);"
                        autocomplete="off">

                      <div x-show="open" x-cloak
                        class="position-absolute bg-white border rounded shadow-sm w-100 mt-1"
                        style="z-index: 1055; max-height: 180px; overflow-y: auto;">
                        @foreach ($this->satuanOptions as $satuanOpt)
                        <div
                          class="px-2 py-1_5 cursor-pointer dropdown-item small {{ ($bi['unit'] ?? '') == $satuanOpt->name ? 'bg-primary text-white' : '' }}"
                          x-show="'{{ strtolower($satuanOpt->name) }}'.includes(search.toLowerCase())"
                          @click="
                                                        $wire.set('workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.unit', '{{ $satuanOpt->name }}');
                                                        search = '{{ $satuanOpt->name }}';
                                                        open = false;
                                                        $dispatch('coa-dropdown-close');
                                                        isDirty = true;
                                                    ">
                          {{ $satuanOpt->name }}
                        </div>
                        @endforeach
                      </div>
                    </div>

                    {{-- Satuan 2 --}}
                    <div x-data="{
                                open: false,
                                search: '{{ $bi['unit_2'] ?? '' }}',
                            }" class="position-relative" @click.outside="open = false"
                      wire:key="wp-{{ $wpIdx }}-act-{{ $actIdx }}-bi-{{ $biIdx }}-unit2-{{ $bi['unit_2'] ?? 'empty' }}">

                      <input type="text" class="form-control form-control-sm" placeholder="Satuan 2 (opsional)"
                        x-model="search" @focus="open = true; $dispatch('coa-dropdown-open');"
                        @input="open = true; $dispatch('coa-dropdown-open'); $wire.set('workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.unit_2', search);"
                        autocomplete="off">

                      <div x-show="open" x-cloak
                        class="position-absolute bg-white border rounded shadow-sm w-100 mt-1"
                        style="z-index: 1055; max-height: 180px; overflow-y: auto;">
                        @foreach ($this->satuanOptions as $satuanOpt)
                        <div
                          class="px-2 py-1_5 cursor-pointer dropdown-item small {{ ($bi['unit_2'] ?? '') == $satuanOpt->name ? 'bg-primary text-white' : '' }}"
                          x-show="'{{ strtolower($satuanOpt->name) }}'.includes(search.toLowerCase())"
                          @click="
                                                        $wire.set('workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.unit_2', '{{ $satuanOpt->name }}');
                                                        search = '{{ $satuanOpt->name }}';
                                                        open = false;
                                                        $dispatch('coa-dropdown-close');
                                                        isDirty = true;
                                                    ">
                          {{ $satuanOpt->name }}
                        </div>
                        @endforeach
                      </div>
                    </div>
                  </td>
                  <td class="border-top-0" style="min-width: 80px;">
                    {{-- Vol 1 --}}
                    <input type="number"
                      class="form-control form-control-sm mb-2 @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $biIdx . '.quantity') is-invalid @enderror"
                      wire:model.live.debounce.500ms="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.quantity"
                      min="1" placeholder="Vol 1">

                    {{-- Vol 2 --}}
                    <input type="number"
                      class="form-control form-control-sm @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $biIdx . '.quantity_2') is-invalid @enderror"
                      wire:model.live.debounce.500ms="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.quantity_2"
                      min="1" placeholder="Vol 2">
                  </td>
                  <td class="border-top-0">
                    <input type="number"
                      class="form-control form-control-sm @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $biIdx . '.unit_price') is-invalid @enderror"
                      wire:model.live.debounce.500ms="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.unit_price"
                      min="0" step="1000"
                      oninput="this.value = this.value.replace(/^0+(?=\d)/, '')"
                      onblur="if (this.value === '' || this.value === null) { this.value = 0; this.dispatchEvent(new Event('input')); }">
                  </td>
                  <td class="text-end text-nowrap border-top-0">
                    <div class="fw-semibold text-primary">Rp {{ number_format($biTotal, 0, ',', '.') }}</div>
                  </td>
                  <td class="text-center text-nowrap border-top-0">
                    <div class="d-flex align-items-center justify-content-center gap-1">
                      <button type="button" class="btn btn-sm btn-icon btn-outline-primary"
                        title="More Details" @click="openModal('{{ $modalKey }}')"
                        @disabled($biTotal <=0)>
                        <i class="bx bx-detail"></i>
                      </button>

                      @if ($itemCount > 1)
                      <button type="button"
                        wire:click="removeBudgetItem({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }})"
                        @click="isDirty = true" class="btn btn-sm btn-icon btn-outline-danger"
                        title="Hapus detail rincian ini">
                        <i class="bx bx-trash"></i>
                      </button>
                      @endif

                      @if ($itemIdx === $itemCount - 1)
                      <button type="button"
                        wire:click="duplicateBudgetItem({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }})"
                        @click="isDirty = true" class="btn btn-sm btn-icon btn-outline-success"
                        title="Tambah detail rincian untuk akun ini">
                        <i class="bx bx-plus"></i>
                      </button>
                      @endif
                    </div>

                    @if (!empty($selectedMonths) || !empty($selectedCashOutMonths) || !empty($selectedRealizationMonths))
                    <div class="mt-1">
                      @if (!empty($selectedMonths))
                      @if (abs($monthlyRemainder) < 0.01)
                        <span class="badge bg-success rounded-pill" style="font-size:0.6rem;"><i
                          class="bx bx-check"></i> Dist</span>
                        @else
                        <span class="badge bg-warning rounded-pill" style="font-size:0.6rem;">Dist</span>
                        @endif
                        @endif
                        @if (!empty($selectedCashOutMonths))
                        @if (abs($cashOutRemainder) < 0.01)
                          <span class="badge bg-success rounded-pill" style="font-size:0.6rem;"><i
                            class="bx bx-check"></i> Kas</span>
                          @else
                          <span class="badge bg-warning rounded-pill" style="font-size:0.6rem;">Kas</span>
                          @endif
                          @endif
                          @if (!empty($selectedRealizationMonths))
                          @if ($realizationAllocated > 0)
                          <span class="badge bg-success rounded-pill" style="font-size:0.6rem;"><i
                              class="bx bx-trending-up"></i> Real</span>
                          @else
                          <span class="badge bg-secondary rounded-pill" style="font-size:0.6rem;">Real</span>
                          @endif
                          @endif
                    </div>
                    @endif
                  </td>
                </tr>
                @endforeach
                @endforeach
              </tbody>
            </table>
          </div>

          {{-- MODALS — outside the overflow container --}}
          @foreach ($act['budget_items'] as $biIdx => $bi)
          @php
          $qty2 = (!empty($bi['unit_2'])) ? (float) ($bi['quantity_2'] ?? 1) : 1;
          $biTotal = ($bi['quantity'] ?? 0) * $qty2 * ($bi['unit_price'] ?? 0);
          $monthlyAllocated = array_sum($bi['monthly_distribution'] ?? []);
          $monthlyRemainder = $biTotal - $monthlyAllocated;
          $selectedMonths = $bi['distribution_months'] ?? [];
          $cashOutAllocated = array_sum($bi['cash_out_distribution'] ?? []);
          $cashOutRemainder = $biTotal - $cashOutAllocated;
          $selectedCashOutMonths = $bi['cash_out_months'] ?? [];
          $realizationAllocated = array_sum($bi['realization_distribution'] ?? []);
          $realizationRemainder = $biTotal - $realizationAllocated;
          $selectedRealizationMonths = $bi['realization_months'] ?? [];
          $allMonths = array_unique(array_merge($selectedMonths, $selectedCashOutMonths, $selectedRealizationMonths));
          sort($allMonths);
          $modalKey = 'wp' . $wpIdx . '-act' . $actIdx . '-bi' . $biIdx;
          @endphp
          <div wire:key="modal-{{ $wpIdx }}-{{ $actIdx }}-{{ $biIdx }}"
            x-show="modalKey === '{{ $modalKey }}'" x-cloak
            class="position-fixed top-0 start-0 w-100 h-100 overflow-y-auto py-3 px-2"
            :class="modalKey === '{{ $modalKey }}' ? 'd-flex align-items-start justify-content-center' : 'd-none'"
            style="z-index: 1080; background: rgba(0,0,0,0.5);">
            <div x-show="modalKey === '{{ $modalKey }}'"
              x-transition:enter="transition ease-out duration-200"
              x-transition:enter-start="opacity-0 translate-y-4"
              x-transition:enter-end="opacity-100 translate-y-0"
              x-transition:leave="transition ease-in duration-150"
              x-transition:leave-start="opacity-100 translate-y-0"
              x-transition:leave-end="opacity-0 translate-y-4" class="bg-white rounded-3 shadow-lg"
              style="width: 740px; max-width: 96vw; max-height: calc(100vh - 3rem); display: flex; flex-direction: column;"
              @click.stop>
              {{-- Modal Header --}}
              <div
                class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom flex-shrink-0">
                <div class="d-flex align-items-center gap-2">
                  <i class="bx bx-detail text-primary fs-5"></i>
                  <h6 class="mb-0 fw-bold">Detail Pengajuan</h6>
                  <span class="badge bg-label-secondary rounded-pill small">Item {{ $biIdx + 1 }}</span>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill"
                  @click="closeModal()">
                  <i class="bx bx-x fs-5"></i>
                </button>
              </div>
              {{-- Modal Body --}}
              <div class="px-4 py-3" style="overflow-y: auto; max-height: calc(100vh - 12rem);">
                <div class="alert alert-primary d-flex justify-content-between align-items-center py-2 mb-4">
                  <span class="small fw-semibold">Total Item</span>
                  <span class="fw-bold fs-6">Rp {{ number_format($biTotal, 0, ',', '.') }}</span>
                </div>
                {{-- Distribusi Beban --}}
                <div class="mb-4">
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                      <i class="bx bx-calendar text-primary"></i>
                      <span class="fw-semibold text-primary small">Distribusi Bulanan</span>
                      @if (!empty($selectedMonths))
                      <span class="badge bg-label-primary rounded-pill">{{ count($selectedMonths) }}
                        bulan</span>
                      @endif
                    </div>
                    <div class="d-flex align-items-center gap-2">
                      @if ($biTotal > 0)
                      @if (abs($monthlyRemainder) < 0.01 && !empty($selectedMonths))
                        <span class="badge bg-success rounded-pill small"><i
                          class="bx bx-check me-1"></i>Lengkap</span>
                        @elseif($monthlyRemainder < 0)
                          <span class="badge bg-danger rounded-pill small">Lebih Rp
                          {{ number_format(abs($monthlyRemainder), 0, ',', '.') }}</span>
                          @elseif(!empty($selectedMonths))
                          <span class="badge bg-warning rounded-pill small">Sisa Rp
                            {{ number_format($monthlyRemainder, 0, ',', '.') }}</span>
                          @endif
                          @endif
                          <button type="button"
                            wire:click="distributeEvenly({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }})"
                            class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:0.72rem;"
                            @if ($biTotal <=0) disabled @endif>
                            <i class="bx bx-equalizer me-1"></i>Bagi Rata
                          </button>
                    </div>
                  </div>
                  <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label small text-muted mb-0">Pilih Bulan Distribusi Beban:</label>
                    <button type="button"
                      wire:click="selectAllMonths({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }})"
                      class="btn btn-xs btn-link p-0 text-decoration-none" style="font-size: 0.72rem;">
                      {{ count($selectedMonths) === 12 ? 'Deselect All' : 'Select All' }}
                    </button>
                  </div>
                  <div class="d-flex flex-wrap gap-1 mb-2">
                    @foreach ($monthLabels as $monthNum => $monthLabel)
                    <button type="button"
                      wire:click="toggleMonth({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }}, {{ $monthNum }})"
                      class="btn btn-sm {{ in_array($monthNum, $selectedMonths) ? 'btn-primary' : 'btn-outline-secondary' }}"
                      style="min-width: 52px; font-size: 0.75rem; padding: 0.2rem 0.4rem;">
                      {{ $monthLabel }}
                    </button>
                    @endforeach
                  </div>
                  @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $biIdx .
                  '.monthly')
                  <div class="alert alert-danger small py-2 mb-2"><i
                      class="bx bx-error-circle me-1"></i>{{ $message }}</div>
                  @enderror
                </div>
                {{-- Rencana Kas Keluar --}}
                <div class="mb-4">
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                      <i class="bx bx-wallet text-primary"></i>
                      <span class="fw-semibold text-primary small">Rencana Kas Keluar</span>
                      @if (!empty($selectedCashOutMonths))
                      <span class="badge bg-label-primary rounded-pill">{{ count($selectedCashOutMonths) }}
                        bulan</span>
                      @endif
                    </div>
                    <div class="d-flex align-items-center gap-2">
                      @if ($biTotal > 0)
                      @if (abs($cashOutRemainder) < 0.01 && !empty($selectedCashOutMonths))
                        <span class="badge bg-success rounded-pill small"><i
                          class="bx bx-check me-1"></i>Lengkap</span>
                        @elseif($cashOutRemainder < 0)
                          <span class="badge bg-danger rounded-pill small">Lebih Rp
                          {{ number_format(abs($cashOutRemainder), 0, ',', '.') }}</span>
                          @elseif(!empty($selectedCashOutMonths))
                          <span class="badge bg-warning rounded-pill small">Sisa Rp
                            {{ number_format($cashOutRemainder, 0, ',', '.') }}</span>
                          @endif
                          @endif
                          <button type="button"
                            wire:click="distributeCashOutEvenly({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }})"
                            class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:0.72rem;"
                            @if ($biTotal <=0) disabled @endif>
                            <i class="bx bx-equalizer me-1"></i>Bagi Rata
                          </button>
                    </div>
                  </div>
                  <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label small text-muted mb-0">Pilih Bulan Pembayaran:</label>
                    <button type="button"
                      wire:click="selectAllCashOutMonths({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }})"
                      class="btn btn-xs btn-link p-0 text-decoration-none" style="font-size: 0.72rem;">
                      {{ count($selectedCashOutMonths) === 12 ? 'Deselect All' : 'Select All' }}
                    </button>
                  </div>
                  <div class="d-flex flex-wrap gap-1 mb-2">
                    @foreach ($monthLabels as $monthNum => $monthLabel)
                    <button type="button"
                      wire:click="toggleCashOutMonth({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }}, {{ $monthNum }})"
                      class="btn btn-sm {{ in_array($monthNum, $selectedCashOutMonths) ? 'btn-primary' : 'btn-outline-secondary' }}"
                      style="min-width: 52px; font-size: 0.75rem; padding: 0.2rem 0.4rem;">
                      {{ $monthLabel }}
                    </button>
                    @endforeach
                  </div>
                  @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $biIdx .
                  '.cash_out')
                  <div class="alert alert-danger small py-2 mb-2"><i
                      class="bx bx-error-circle me-1"></i>{{ $message }}</div>
                  @enderror
                </div>
                {{-- Realisasi --}}
                <div class="mb-4">
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                      <i class="bx bx-trending-up text-success"></i>
                      <span class="fw-semibold text-success small">Realisasi</span>
                      @if (!empty($selectedRealizationMonths))
                      <span class="badge bg-label-success rounded-pill">{{ count($selectedRealizationMonths) }} bulan</span>
                      @endif
                    </div>
                    <div class="d-flex align-items-center gap-2">
                      @if ($biTotal > 0)
                      @if (abs($realizationRemainder) < 0.01 && !empty($selectedRealizationMonths))
                        <span class="badge bg-success rounded-pill small"><i class="bx bx-check me-1"></i>Lengkap</span>
                        @elseif($realizationRemainder < 0)
                          <span class="badge bg-danger rounded-pill small">Melebihi Rp {{ number_format(abs($realizationRemainder), 0, ',', '.') }}</span>
                          @elseif(!empty($selectedRealizationMonths))
                          <span class="badge bg-warning rounded-pill small">Sisa Rp {{ number_format($realizationRemainder, 0, ',', '.') }}</span>
                          @endif
                          @endif
                          <button type="button"
                            wire:click="distributeRealizationEvenly({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }})"
                            class="btn btn-xs btn-outline-success py-0 px-2" style="font-size:0.72rem;"
                            @if ($biTotal <=0) disabled @endif>
                            <i class="bx bx-equalizer me-1"></i>Bagi Rata
                          </button>
                    </div>
                  </div>
                  <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label small text-muted mb-0">Pilih Bulan Realisasi:</label>
                    <button type="button"
                      wire:click="selectAllRealizationMonths({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }})"
                      class="btn btn-xs btn-link p-0 text-decoration-none" style="font-size: 0.72rem;">
                      {{ count($selectedRealizationMonths) === 12 ? 'Deselect All' : 'Select All' }}
                    </button>
                  </div>
                  <div class="d-flex flex-wrap gap-1 mb-2">
                    @foreach ($monthLabels as $monthNum => $monthLabel)
                    <button type="button"
                      wire:click="toggleRealizationMonth({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }}, {{ $monthNum }})"
                      class="btn btn-sm {{ in_array($monthNum, $selectedRealizationMonths) ? 'btn-success' : 'btn-outline-secondary' }}"
                      style="min-width: 52px; font-size: 0.75rem; padding: 0.2rem 0.4rem;">
                      {{ $monthLabel }}
                    </button>
                    @endforeach
                  </div>
                </div>
                {{-- 4-column summary table --}}
                @if (!empty($allMonths))
                <div class="border rounded-2 table-responsive">
                  <table class="table table-sm table-bordered mb-0" style="min-width: 600px;">
                    <thead class="table-primary">
                      <tr>
                        <th class="text-center" style="width:90px;">Bulan</th>
                        <th class="text-end">Distribusi Beban (Rp)</th>
                        <th class="text-end">Rencana Kas Keluar (Rp)</th>
                        <th class="text-end">Realisasi (Rp)</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($monthLabels as $monthNum => $monthLabel)
                      @php
                      $isDistribMonth = in_array($monthNum, $selectedMonths);
                      $isCashOutMonth = in_array($monthNum, $selectedCashOutMonths);
                      $isRealizationMonth = in_array($monthNum, $selectedRealizationMonths);
                      @endphp
                      @if ($isDistribMonth || $isCashOutMonth || $isRealizationMonth)
                      <tr>
                        <td class="text-center fw-semibold small">{{ $monthLabel }}</td>
                        <td class="text-end">
                          @if ($isDistribMonth)
                          <div class="input-group input-group-sm justify-content-end">
                            <span class="input-group-text"
                              style="font-size:0.7rem;padding:0.15rem 0.4rem;">Rp</span>
                            <input type="number" class="form-control form-control-sm text-end"
                              wire:model.live="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.monthly_distribution.{{ $monthNum }}"
                              min="0" step="1000" placeholder="0"
                              style="font-size:0.8rem;max-width:180px;">
                          </div>
                          @else
                          <span class="text-muted small">—</span>
                          @endif
                        </td>
                        <td class="text-end">
                          @if ($isCashOutMonth)
                          <div class="input-group input-group-sm justify-content-end">
                            <span class="input-group-text"
                              style="font-size:0.7rem;padding:0.15rem 0.4rem;">Rp</span>
                            <input type="number" class="form-control form-control-sm text-end"
                              wire:model.live="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.cash_out_distribution.{{ $monthNum }}"
                              min="0" step="1000" placeholder="0"
                              style="font-size:0.8rem;max-width:180px;">
                          </div>
                          @else
                          <span class="text-muted small">—</span>
                          @endif
                        </td>
                        <td class="text-end">
                          @if ($isRealizationMonth)
                          <div class="input-group input-group-sm justify-content-end">
                            <span class="input-group-text"
                              style="font-size:0.7rem;padding:0.15rem 0.4rem;">Rp</span>
                            <input type="number" class="form-control form-control-sm text-end"
                              wire:model.live="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.realization_distribution.{{ $monthNum }}"
                              min="0" step="1000" placeholder="0"
                              style="font-size:0.8rem;max-width:180px;">
                          </div>
                          @else
                          <span class="text-muted small">—</span>
                          @endif
                        </td>
                      </tr>
                      @endif
                      @endforeach
                    </tbody>
                    <tfoot class="table-light">
                      <tr>
                        <th class="text-center small">Total</th>
                        <th class="text-end small {{ abs($monthlyRemainder) < 0.01 ? 'text-success' : ($monthlyRemainder < 0 ? 'text-danger' : 'text-warning') }}">
                          Rp {{ number_format($monthlyAllocated, 0, ',', '.') }}
                        </th>
                        <th class="text-end small {{ abs($cashOutRemainder) < 0.01 ? 'text-success' : ($cashOutRemainder < 0 ? 'text-danger' : 'text-warning') }}">
                          Rp {{ number_format($cashOutAllocated, 0, ',', '.') }}
                        </th>
                        <th class="text-end small {{ abs($realizationRemainder) < 0.01 && !empty($selectedRealizationMonths) ? 'text-success' : ($realizationRemainder < 0 ? 'text-danger' : 'text-warning') }}">
                          Rp {{ number_format($realizationAllocated, 0, ',', '.') }}
                        </th>
                      </tr>
                      <tr>
                        <th class="text-center small text-muted">Sisa</th>
                        <th class="text-end small {{ abs($monthlyRemainder) < 0.01 ? 'text-success' : ($monthlyRemainder < 0 ? 'text-danger' : 'text-warning') }}">
                          Rp {{ number_format($monthlyRemainder, 0, ',', '.') }}
                        </th>
                        <th class="text-end small {{ abs($cashOutRemainder) < 0.01 ? 'text-success' : ($cashOutRemainder < 0 ? 'text-danger' : 'text-warning') }}">
                          Rp {{ number_format($cashOutRemainder, 0, ',', '.') }}
                        </th>
                        <th class="text-end small {{ abs($realizationRemainder) < 0.01 && !empty($selectedRealizationMonths) ? 'text-success' : ($realizationRemainder < 0 ? 'text-danger' : 'text-warning') }}">
                          Rp {{ number_format($realizationRemainder, 0, ',', '.') }}
                        </th>
                      </tr>
                    </tfoot>
                  </table>
                </div>
                @else
                <div class="text-center text-muted small py-3 border rounded-2">
                  <i class="bx bx-info-circle me-1"></i>Belum ada bulan yang dipilih. Pilih bulan di atas untuk
                  memulai distribusi.
                </div>
                @endif
              </div>
              {{-- Modal Footer --}}
              <div class="px-4 py-3 border-top d-flex justify-content-end flex-shrink-0">
                <button type="button" class="btn btn-primary" @click="closeModal()">
                  <i class="bx bx-check me-1"></i>Selesai
                </button>
              </div>
            </div>
          </div>
          @endforeach

          <button type="button" wire:click="addBudgetItem({{ $wpIdx }}, {{ $actIdx }})"
            class="btn btn-sm btn-label-secondary mt-2">
            <i class="bx bx-plus me-1"></i> Tambah Item Belanja
          </button>

        </div>
        @else
        <div class="alert alert-info d-flex align-items-center mb-0 mt-3">
          <i class="bx bx-info-circle me-2 fs-4"></i>
          <div>
            Silakan pilih <strong>Program Kerja</strong> dan <strong>Nama Kegiatan</strong> terlebih dahulu untuk
            mengisi detail anggaran belanja.
          </div>
        </div>
        @endif
      </div>
      @endforeach

      {{-- Add Activity Button inside the Work Plan card --}}
      @if ($wp['work_plan_id'])
      @php $availableActivities = $this->getActivitiesForIndex($wpIdx); @endphp
      @if ($availableActivities->count() > 0)
      <div class="text-center my-2">
        <button type="button" wire:click="addActivity({{ $wpIdx }})"
          class="btn btn-sm btn-outline-primary">
          <i class="bx bx-plus me-1"></i> Tambah Kegiatan Lain dalam Program Ini
        </button>
      </div>
      @else
      <div class="text-center my-2">
        <span class="text-muted small">
          <i class="bx bx-info-circle me-1"></i>
          Semua kegiatan dalam Program Kerja ini sudah digunakan, tidak dapat menambah kegiatan lain.
        </span>
      </div>
      @endif
      @endif
    </div>
  </div>
  @endforeach

  <div class="d-flex gap-2 mb-4">
    <button type="button" wire:click="addWorkPlan()" class="btn btn-label-primary">
      <i class="bx bx-plus me-1"></i> Tambah Program Kerja
    </button>
  </div>

  {{-- Action Buttons --}}
  <div class="card">
    <div class="card-body d-flex justify-content-between align-items-center">
      <a href="{{ route('rkap-submissions') }}" class="btn btn-label-secondary">
        <i class="bx bx-arrow-back me-1"></i> Kembali
      </a>
      <div class="d-flex gap-2">
        <button wire:click="saveDraft()" @click="isSubmitting = true" wire:loading.attr="disabled"
          class="btn btn-label-primary">
          <span wire:loading.remove wire:target="saveDraft"><i class="bx bx-save me-1"></i> Simpan Draft</span>
          <span wire:loading wire:target="saveDraft"><span class="spinner-border spinner-border-sm me-1"></span>
            Menyimpan...</span>
        </button>
        <button wire:click="submitForReview()" @click="isSubmitting = true" wire:loading.attr="disabled"
          wire:confirm="Yakin mengajukan RKAP ini untuk review? Pastikan data sudah lengkap." class="btn btn-primary">
          <span wire:loading.remove wire:target="submitForReview"><i class="bx bx-send me-1"></i> Ajukan untuk
            Review</span>
          <span wire:loading wire:target="submitForReview"><span class="spinner-border spinner-border-sm me-1"></span>
            Mengajukan...</span>
        </button>
      </div>
    </div>
  </div>
</div>