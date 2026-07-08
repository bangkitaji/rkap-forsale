<div x-data="{ isDirty: false, isSubmitting: false, showToast: false, toastMessage: '', toastType: 'success' }" @input="isDirty = true" @change="isDirty = true"
  @form-saved.window="toastMessage = $event.detail.message || 'Draf RKAP berhasil disimpan.'; toastType = 'success'; showToast = true; isDirty = false; setTimeout(() => showToast = false, 5000)"
  @work-plan-duplicate-rejected.window="toastMessage = 'Program Kerja ini sudah dipilih pada kartu lain. Silakan pilih Program Kerja yang berbeda.'; toastType = 'warning'; showToast = true; setTimeout(() => showToast = false, 5000)"
  @activity-duplicate-rejected.window="toastMessage = 'Kegiatan ini sudah dipilih di baris lain dalam Program Kerja yang sama. Silakan pilih kegiatan yang berbeda.'; toastType = 'warning'; showToast = true; setTimeout(() => showToast = false, 5000)"
  @beforeunload.window="if(isDirty && !isSubmitting) { $event.returnValue = 'Ada perubahan yang belum disimpan.'; return 'Ada perubahan yang belum disimpan.'; }">
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
  <div class="toast-container position-fixed top-0 end-0 p-3 rkap-z-1090">
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
      x-cloak>
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
  <div class="toast-container position-fixed top-0 end-0 p-3 rkap-z-1090" wire:key="error-toast-container-{{ microtime(true) }}">
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
      x-cloak>
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

  {{-- Rejection / Revision Banner --}}
  @if ($submission && str_ends_with($submission->status, '_revision'))
  @php
  $latestRevision = $submission->approvals->firstWhere('action', 'revision_requested');
  $revisionReason = $latestRevision?->comments;
  $revisionByName = $latestRevision?->user?->name ?? 'Reviewer';
  $revisionAt = $latestRevision?->created_at?->format('d M Y, H:i');
  $rejectedCount = $submission->workPlans
  ->filter(fn($wp) => ($wp->approval_status ?? 'pending') === 'rejected')
  ->count();
  $approvedCount = $submission->workPlans
  ->filter(fn($wp) => ($wp->approval_status ?? 'pending') === 'approved')
  ->count();
  @endphp
  <div class="alert alert-danger border-danger mb-4 p-0 overflow-hidden" role="alert">
    <div class="d-flex align-items-center gap-3 px-4 py-3 bg-danger text-white">
      <i class="bx bx-x-circle fs-2 flex-shrink-0"></i>
      <div>
        <h5 class="mb-0 fw-bold">Pengajuan Dikembalikan untuk Perbaikan</h5>
        <div class="small opacity-90 mt-1">
          Dikembalikan oleh <strong>{{ $revisionByName }}</strong>
          @if ($revisionAt) pada {{ $revisionAt }} @endif
        </div>
      </div>
    </div>
    <div class="px-4 py-3">
      @if ($revisionReason)
      <div class="mb-3">
        <div class="fw-semibold text-danger mb-1"><i class="bx bx-comment-error me-1"></i>Alasan Pengembalian:</div>
        <p class="mb-0 text-dark rkap-preline">{{ $revisionReason }}</p>
      </div>
      @endif
      <div class="d-flex flex-wrap gap-3">
        @if ($rejectedCount > 0)
        <div class="d-flex align-items-center gap-2 bg-danger bg-opacity-10 rounded px-3 py-2 border-start border-warning border-3">
          <i class="bx bx-x-circle text-danger"></i>
          <span class="small"><strong class="text-danger">{{ $rejectedCount }} kegiatan</strong> perlu diperbaiki —
            cek catatan revisi di tiap kegiatan.</span>
        </div>
        @endif
        @if ($approvedCount > 0)
        <div class="d-flex align-items-center gap-2 bg-success bg-opacity-10 rounded px-3 py-2 border-start border-success border-3">
          <i class="bx bx-check-circle text-success"></i>
          <span class="small"><strong class="text-success">{{ $approvedCount }} kegiatan</strong> sudah disetujui dan
            tidak dapat diubah.</span>
        </div>
        @endif
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
      <textarea class="form-control" wire:model="notes" rows="2"
        placeholder="Catatan atau keterangan umum untuk pengajuan ini..."></textarea>
    </div>
  </div>

  {{-- Work Plans Loop --}}
  @foreach ($workPlans as $wpIdx => $wp)
  @php
  $rowWorkPlanOptions = $this->getWorkPlanOptionsForIndex($wpIdx);
  $selectedWorkPlan = $workPlanOptions->firstWhere('id', $wp['work_plan_id']);
  $hasApproved = collect($wp['activities'])->contains(fn($a) => ($a['approval_status'] ?? 'pending') === 'approved');
  @endphp

  <div class="card mb-4 border-start border-primary border-3" wire:key="wp-card-{{ $wpIdx }}">
    {{-- ===== Card Header: Program Kerja select ===== --}}
    <div class="card-header border-bottom">
      <div class="d-flex align-items-start gap-3">
        {{-- Program Kerja Dropdown --}}
        <div class="d-flex flex-column gap-1 rkap-min-width-panel">
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
                @input="open = true" autocomplete="off" id="wp-search-{{ $wpIdx }}"
                @disabled($hasApproved)>
              @if ($wp['work_plan_id'] && !$hasApproved)
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
            <div x-show="open" x-cloak class="position-absolute bg-white border rounded shadow-sm w-100 mt-1 rkap-dropdown-menu">
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
          $qty2 = !empty($bi['unit_2']) ? (float) ($bi['quantity_2'] ?? 1) : 1;
          $wpSubtotal += ((float) ($bi['quantity'] ?? 0)) * $qty2 * ((float) ($bi['unit_price'] ?? 0));
          }
          }
          @endphp
          <span class="text-muted small fw-semibold mb-1">Subtotal Program</span>
          <span class="text-muted small fw-bold text-primary fs-6 has-tooltip">
            Rp {{ number_format($wpSubtotal, 0, ',', '.') }}
            <span class="custom-tooltip-content tooltip-align-right">
              @php
              $prevWpId = $wp['work_plan_id'] ?? null;
              $prevProgramData =
              $prevWpId && isset($prevData['map']['programs'][$prevWpId])
              ? $prevData['map']['programs'][$prevWpId]
              : null;
              $prevPeriod = $prevData['period'] ?? '-';
              @endphp
              @if ($prevProgramData)
              <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya
                ({{ $prevPeriod }})</div>
              <div class="row text-center">
                <div class="col-4 border-end">
                  <div class="text-white-50 small rkap-tooltip-stat-label">Anggaran</div>
                  <div class="fw-bold text-white rkap-tooltip-stat-value">Rp
                    {{ number_format($prevProgramData['budget'], 0, ',', '.') }}
                  </div>
                </div>
                <div class="col-4 border-end">
                  <div class="text-white-50 small rkap-tooltip-stat-label">Realisasi</div>
                  <div class="fw-bold text-white text-success rkap-tooltip-stat-value">Rp
                    {{ number_format($prevProgramData['realization'], 0, ',', '.') }}
                  </div>
                </div>
                <div class="col-4">
                  <div class="text-white-50 small rkap-tooltip-stat-label">Proyeksi</div>
                  <div class="fw-bold text-white text-warning rkap-tooltip-stat-value">Rp
                    {{ number_format($prevProgramData['projection'] ?? 0, 0, ',', '.') }}
                  </div>
                </div>
              </div>
              @else
              <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
              @endif
            </span>
          </span>
          @if (count($workPlans) > 1 && !$hasApproved)
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
      $isApproved = ($act['approval_status'] ?? 'pending') === 'approved';
      $isRejected = ($act['approval_status'] ?? 'pending') === 'rejected';
      @endphp

      <div class="activity-card p-3 mb-3" wire:key="wp-{{ $wpIdx }}-act-card-{{ $actIdx }}">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-label-primary rounded-circle p-2"><i class="bx bx-task"></i></span>
            <h6 class="mb-0 fw-bold">Kegiatan {{ $actIdx + 1 }}</h6>
            @if ($isApproved)
            <span class="badge bg-label-success ms-2"><i class="bx bx-check-circle me-1"></i>Disetujui</span>
            @elseif ($isRejected)
            <span class="badge bg-label-danger ms-2"><i class="bx bx-x-circle me-1"></i>Revisi</span>
            @endif
          </div>
          @if (count($wp['activities']) > 1 && !$isApproved)
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
                  autocomplete="off" id="act-search-{{ $wpIdx }}-{{ $actIdx }}"
                  @disabled($isApproved)>
                @if ($act['activity_id'] && !$isApproved)
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
              <div x-show="open" x-cloak class="position-absolute bg-white border rounded shadow-sm w-100 mt-1 rkap-dropdown-menu">
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
                    <span class="text-secondary rkap-font-085">{{ $a->title }}</span>
                  </div>
                </div>
                @empty
                <div class="px-3 py-2 text-muted small">Tidak ada data kegiatan.</div>
                @endforelse
              </div>
            </div>

            {{-- ===== Checkbox: Periode Anggaran Lalu ===== --}}
            <div class="mt-2 d-flex align-items-center gap-2">
              <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox"
                  wire:model.live="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.is_past_period_payment"
                  id="ppp-{{ $wpIdx }}-{{ $actIdx }}"
                  @disabled($isApproved)>
                <label class="form-check-label small fw-semibold text-warning" for="ppp-{{ $wpIdx }}-{{ $actIdx }}">
                  <i class="bx bx-calendar-exclamation me-1"></i> Periode Anggaran Lalu
                </label>
              </div>
            </div>

            @if ($act['is_past_period_payment'] ?? false)
            <div class="mt-2" wire:key="ppp-section-{{ $wpIdx }}-{{ $actIdx }}">
              <select class="form-select form-select-sm @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.past_period_id') is-invalid @enderror"
                wire:model.live="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.past_period_id"
                @disabled($isApproved)>
                <option value="">-- Pilih Periode Anggaran Lalu --</option>
                @foreach($this->pastPeriods as $pp)
                <option value="{{ $pp->id }}">RKAP {{ $pp->year }} — {{ $pp->title }}</option>
                @endforeach
              </select>
              @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.past_period_id')
              <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror

              @php
              $pastPeriodTitle = null;
              if (!empty($act['past_period_id'])) {
              $selPp = $this->pastPeriods->firstWhere('id', $act['past_period_id']);
              $pastPeriodTitle = $selPp ? 'RKAP ' . $selPp->year . ' — ' . $selPp->title : null;
              }
              @endphp
              <div class="alert alert-warning d-flex align-items-center mt-2 py-2 px-3 rkap-alert-warning">
                <i class="bx bx-info-circle me-2 flex-shrink-0 rkap-font-1"></i>
                <span>
                  Ini adalah anggaran rencana <strong>pembayaran kewajiban</strong>
                  @if($pastPeriodTitle) untuk <strong>{{ $pastPeriodTitle }}</strong>@endif.
                  COA yang dapat dipilih hanya akun <strong>Kewajiban (Kepala 2)</strong>.
                </span>
              </div>
            </div>
            @endif

            {{-- Tombol upload & list file referensi --}}
            <div class="mt-2" wire:key="wp-{{ $wpIdx }}-act-files-{{ $actIdx }}">
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-xs btn-outline-primary"
                  wire:click="openUploadModal({{ $wpIdx }}, {{ $actIdx }})"
                  @disabled($isApproved)>
                  <i class="bx bx-upload me-1"></i> Upload File Referensi
                </button>
              </div>

              @if (!empty($act['uploaded_files']))
              <div class="d-flex flex-column gap-1 mt-2">
                @foreach ($act['uploaded_files'] as $fileIdx => $file)
                @php
                $isViewable = in_array(strtolower($file['file_type'] ?? ''), ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'svg']);
                @endphp
                <div class="d-flex align-items-center justify-content-between bg-light rounded px-2 py-1 rkap-file-list-item" wire:key="file-item-{{ $wpIdx }}-{{ $actIdx }}-{{ $fileIdx }}">
                  <div class="d-flex align-items-center gap-1 text-truncate rkap-file-name">
                    <i class="bx bx-file text-secondary flex-shrink-0"></i>
                    @if (isset($file['id']))
                    @if ($isViewable)
                    <a href="javascript:void(0)" class="text-truncate text-primary" data-bs-toggle="modal" data-bs-target="#viewFileModalForm-{{ $file['id'] }}">
                      {{ $file['original_name'] }}
                    </a>
                    @else
                    <a href="{{ route('rkap-files.download', $file['id']) }}" class="text-truncate text-primary" target="_blank">
                      {{ $file['original_name'] }}
                    </a>
                    @endif
                    @else
                    <span class="text-truncate text-muted" title="Belum disimpan">{{ $file['original_name'] }} (baru)</span>
                    @endif
                    <span class="text-muted flex-shrink-0 small">({{ number_format(($file['file_size'] ?? 0) / 1024, 1) }} KB)</span>
                  </div>

                  <div class="d-flex align-items-center gap-1 flex-shrink-0">
                    @if (!$isApproved)
                    <button type="button" class="btn btn-link text-danger p-0 m-0 border-0 rkap-no-text-decoration"
                      wire:click="deleteUploadedFile({{ $wpIdx }}, {{ $actIdx }}, {{ $fileIdx }})"
                      wire:confirm="Hapus file ini?">
                      <i class="bx bx-trash rkap-font-1"></i>
                    </button>
                    @endif
                  </div>
                </div>

                {{-- Modal inline view inside Form --}}
                @if (isset($file['id']) && $isViewable)
                <div class="modal fade" id="viewFileModalForm-{{ $file['id'] }}" tabindex="-1" aria-hidden="true" wire:key="view-file-modal-form-{{ $file['id'] }}">
                  <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content text-start">
                      <div class="modal-header">
                        <h5 class="modal-title"><i class="bx bx-file me-2 text-primary"></i>{{ $file['original_name'] }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body p-0 text-center bg-light">
                        @if (strtolower($file['file_type']) === 'pdf')
                        <iframe src="{{ route('rkap-files.view', $file['id']) }}" class="rkap-preview-frame"></iframe>
                        @else
                        <img src="{{ route('rkap-files.view', $file['id']) }}" class="img-fluid p-3 rkap-preview-image" />
                        @endif
                      </div>
                      <div class="modal-footer">
                        <a href="{{ route('rkap-files.download', $file['id']) }}" class="btn btn-primary btn-sm"><i class="bx bx-download me-1"></i> Download</a>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                      </div>
                    </div>
                  </div>
                </div>
                @endif
                @endforeach
              </div>
              @endif
            </div>
          </div>

          {{-- Subtotal Kegiatan --}}
          <div class="col-md-6 d-flex flex-column align-items-end">
            @php
            $actSubtotal = 0;
            foreach ($act['budget_items'] ?? [] as $bi) {
            $qty2 = !empty($bi['unit_2']) ? (float) ($bi['quantity_2'] ?? 1) : 1;
            $actSubtotal += ((float) ($bi['quantity'] ?? 0)) * $qty2 * ((float) ($bi['unit_price'] ?? 0));
            }
            @endphp
            <span class="text-muted small fw-semibold mb-1">Subtotal Kegiatan</span>
            <span class="text-muted small fw-bold text-primary fs-6 has-tooltip">
              Rp {{ number_format($actSubtotal, 0, ',', '.') }}
              <span class="custom-tooltip-content tooltip-align-right">
                @php
                $prevWpId = $wp['work_plan_id'] ?? null;
                $prevActId = $act['activity_id'] ?? null;
                $actKey = $prevWpId && $prevActId ? "{$prevWpId}-{$prevActId}" : null;
                $prevActivityData =
                $actKey && isset($prevData['map']['activities'][$actKey])
                ? $prevData['map']['activities'][$actKey]
                : null;
                $prevPeriod = $prevData['period'] ?? '-';
                @endphp
                @if ($prevActivityData)
                <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya
                  ({{ $prevPeriod }})
                </div>
                <div class="row text-center">
                  <div class="col-4 border-end">
                    <div class="text-white-50 small rkap-tooltip-stat-label">Anggaran</div>
                    <div class="fw-bold text-white rkap-tooltip-stat-value">Rp
                      {{ number_format($prevActivityData['budget'], 0, ',', '.') }}
                    </div>
                  </div>
                  <div class="col-4 border-end">
                    <div class="text-white-50 small rkap-tooltip-stat-label">Realisasi</div>
                    <div class="fw-bold text-white text-success rkap-tooltip-stat-value">Rp
                      {{ number_format($prevActivityData['realization'], 0, ',', '.') }}
                    </div>
                  </div>
                  <div class="col-4">
                    <div class="text-white-50 small rkap-tooltip-stat-label">Proyeksi</div>
                    <div class="fw-bold text-white text-warning rkap-tooltip-stat-value">Rp
                      {{ number_format($prevActivityData['projection'] ?? 0, 0, ',', '.') }}
                    </div>
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
              wire:model="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.description" rows="2"
              placeholder="Deskripsi kegiatan..." @disabled($isApproved)></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Target Output</label>
            <input type="text" class="form-control form-control-sm"
              wire:model="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.output_target"
              placeholder="Misal: 1 sistem, 100 user" @disabled($isApproved)>
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold">Volume</label>
            <input type="number" class="form-control form-control-sm"
              wire:model="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.quantity" min="1"
              @disabled($isApproved)>
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold">Satuan</label>
            <input type="text" class="form-control form-control-sm"
              wire:model="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.unit" list="satuan-options"
              placeholder="Paket, Unit, ..." autocomplete="off" @disabled($isApproved)>
          </div>
        </div>

        @if ($isRejected && !empty($act['revision_notes']))
        <div class="alert alert-danger d-flex align-items-start mb-3 p-3 animate__animated animate__fadeIn"
          role="alert">
          <span class="badge bg-danger text-white me-3 p-1 mt-0.5"><i class="bx bx-error-circle fs-5"></i></span>
          <div>
            <h6 class="alert-heading mb-1 fw-bold text-danger">Catatan Revisi dari Reviewer:</h6>
            <span class="text-dark">{{ $act['revision_notes'] }}</span>
          </div>
        </div>
        @endif

        {{-- Budget Items section --}}
        @if (!empty($wp['work_plan_id']) && !empty($act['activity_id']))
        <div x-data="{
                dropdownOpen: false,
                modalKey: null,
                openModal(key) { this.modalKey = key; },
                closeModal() { this.modalKey = null; }
            }" @coa-dropdown-open.window="dropdownOpen = true"
          @coa-dropdown-close.window="dropdownOpen = false" @keydown.escape.window="closeModal()">

          <div class="table-responsive" :class="dropdownOpen ? 'rkap-overflow-visible' : ''">
            <table class="table table-sm table-bordered align-middle mb-2">
              <thead class="table-primary text-white fw-semibold">
                <tr>
                  <th class="text-center align-middle rkap-w-30p">Uraian & Detail Belanja <span
                      class="text-warning">*</span></th>
                  <th class="text-center align-middle rkap-w-10p">Vol <span class="text-warning">*</span>
                  </th>
                  <th class="text-center align-middle rkap-w-8p">Satuan</th>
                  <th class="text-center align-middle rkap-w-180">Harga Satuan (Rp) <span
                      class="text-warning">*</span></th>
                  <th class="text-center align-middle rkap-w-160">Total (Rp)</th>
                  <th class="text-center align-middle rkap-w-120">Detail</th>
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
                $filteredCoas = $this->getCoaOptionsForIndex($wpIdx, $actIdx);
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
                    :class="open ? 'rkap-dropdown-open' : ''"
                    @click.outside="open = false; $dispatch('coa-dropdown-close')" class="border-bottom-0">
                    @php
                    $groupSubtotal = collect($group['items'])->sum(function ($info) {
                    $bi = $info['item'];
                    $qty2 = !empty($bi['unit_2']) ? (float) ($bi['quantity_2'] ?? 1) : 1;
                    return ((float) ($bi['quantity'] ?? 0)) * $qty2 * ((float) ($bi['unit_price'] ?? 0));
                    });
                    $prevWpId = $wp['work_plan_id'] ?? null;
                    $prevCode = $selectedCoa?->code ?? ($firstBi['account_code'] ?? null);
                    $prevAmount =
                    $prevWpId && $prevCode && !empty($prevData['map'][$prevWpId][$prevCode])
                    ? $prevData['map'][$prevWpId][$prevCode]
                    : null;
                    $prevPeriod = $prevData['period'] ?? null;
                    $totalItemsCount = count($act['budget_items']);
                    $indices = array_column($group['items'], 'index');
                    $indicesJson = json_encode($indices);
                    @endphp
                    <div class="position-relative">
                      <div class="input-group input-group-sm">
                        <input type="text"
                          class="form-control form-control-sm @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $firstIdx . '.coa_id') is-invalid @enderror"
                          placeholder="Cari akun/belanja..." x-model="search"
                          @focus="open = true; $dispatch('coa-dropdown-open')"
                          @input="open = true; $dispatch('coa-dropdown-open')" autocomplete="off"
                          @disabled($isApproved)>
                        @if ($firstBi['coa_id'] && !$isApproved)
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                          wire:click="updateGroupCoa({{ $wpIdx }}, {{ $actIdx }}, {{ $firstIdx }}, null)"
                          @click="search = ''; currentLabel = ''; open = false; $dispatch('coa-dropdown-close'); isDirty = true;"
                          title="Hapus pilihan">
                          <i class="bx bx-x"></i>
                        </button>
                        @endif
                        @if (!$isApproved && $totalItemsCount > $itemCount)
                        <button type="button"
                          wire:click="removeGroup({{ $wpIdx }}, {{ $actIdx }}, {{ $indicesJson }})"
                          @click="isDirty = true" class="btn btn-sm btn-outline-danger"
                          title="Hapus grup akun belanja">
                          <i class="bx bx-trash"></i>
                        </button>
                        @endif
                        @php
                        $prevActId = $act['activity_id'] ?? null;
                        $coaKey =
                        $prevWpId && $prevActId && $prevCode
                        ? "{$prevWpId}-{$prevActId}-{$prevCode}"
                        : null;
                        $prevCoaData =
                        $coaKey && isset($prevData['map']['coas'][$coaKey])
                        ? $prevData['map']['coas'][$coaKey]
                        : null;
                        $prevPeriod = $prevData['period'] ?? '-';
                        @endphp
                        <span class="input-group-text px-2 fw-semibold text-nowrap has-tooltip rkap-coa-sum-chip">
                          <i class="bx bx-sum me-1 rkap-coa-sum-icon"></i>
                          Rp {{ number_format($groupSubtotal, 0, ',', '.') }}
                          <span class="custom-tooltip-content tooltip-align-right">
                            @if ($prevCoaData)
                            <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP
                              Periode Sebelumnya ({{ $prevPeriod }})</div>
                            <div class="row text-center">
                              <div class="col-4 border-end">
                                <div class="text-white-50 small rkap-tooltip-stat-label">Anggaran</div>
                                <div class="fw-bold text-white rkap-tooltip-stat-value">Rp
                                  {{ number_format($prevCoaData['budget'], 0, ',', '.') }}
                                </div>
                              </div>
                              <div class="col-4 border-end">
                                <div class="text-white-50 small rkap-tooltip-stat-label">Realisasi</div>
                                <div class="fw-bold text-white text-success rkap-tooltip-stat-value">Rp
                                  {{ number_format($prevCoaData['realization'] ?? 0, 0, ',', '.') }}
                                </div>
                              </div>
                              <div class="col-4">
                                <div class="text-white-50 small rkap-tooltip-stat-label">Proyeksi</div>
                                <div class="fw-bold text-white text-warning rkap-tooltip-stat-value">Rp
                                  {{ number_format($prevCoaData['projection'] ?? 0, 0, ',', '.') }}
                                </div>
                              </div>
                            </div>
                            @else
                            <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya
                            </div>
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
                        class="position-absolute bg-white border rounded shadow-sm w-100 mt-1 rkap-dropdown-menu">
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
                </tr>

                @foreach ($group['items'] as $itemIdx => $itemInfo)
                @php
                $biIdx = $itemInfo['index'];
                $bi = $itemInfo['item'];
                $qty2 = !empty($bi['unit_2']) ? (float) ($bi['quantity_2'] ?? 1) : 1;
                $biTotal = ((float) ($bi['quantity'] ?? 0)) * $qty2 * ((float) ($bi['unit_price'] ?? 0));
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
                      wire:model="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.remarks"
                      placeholder="Detail Belanja / Ket..." @disabled($isApproved)>
                  </td>
                  <td class="border-top-0 rkap-min-w-120">
                    {{-- Vol 1 --}}
                    <input type="number"
                      class="form-control form-control-sm mb-2 @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $biIdx . '.quantity') is-invalid @enderror"
                      wire:model.live.debounce.500ms="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.quantity"
                      min="1" placeholder="Vol 1" @disabled($isApproved)>

                    {{-- Vol 2 --}}
                    <input type="number"
                      class="form-control form-control-sm @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $biIdx . '.quantity_2') is-invalid @enderror"
                      wire:model.live.debounce.500ms="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.quantity_2"
                      min="1" placeholder="Vol 2" @disabled($isApproved)>
                  </td>
                  <td class="border-top-0 rkap-min-w-120 rkap-position-relative">
                    {{-- Satuan 1 --}}
                    <input type="text"
                      class="form-control form-control-sm mb-2 @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $biIdx . '.unit') is-invalid @enderror"
                      wire:model="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.unit"
                      list="satuan-options" placeholder="Satuan 1" autocomplete="off"
                      @disabled($isApproved)>

                    {{-- Satuan 2 --}}
                    <input type="text"
                      class="form-control form-control-sm @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $biIdx . '.unit_2') is-invalid @enderror"
                      wire:model.live.debounce.500ms="workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.unit_2"
                      list="satuan-options" placeholder="Satuan 2 (opsional)" autocomplete="off"
                      @disabled($isApproved)>
                  </td>
                  <td class="border-top-0 rkap-min-w-180">
                    <div x-data="{
                                raw: {{ (int) ($bi['unit_price'] ?? 0) }},
                                display: '',
                                timer: null,
                                fmt(n) { return n > 0 ? new Intl.NumberFormat('id-ID').format(n) : '' },
                                sync() {
                                    clearTimeout(this.timer);
                                    this.timer = setTimeout(() => {
                                        $wire.set('workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.unit_price', this.raw);
                                    }, 500);
                                },
                                onInput(e) {
                                    let d = e.target.value.replace(/\D/g, '');
                                    this.raw = parseInt(d) || 0;
                                    this.display = this.fmt(this.raw);
                                    e.target.value = this.display;
                                    this.sync();
                                },
                                onBlur(e) {
                                    if (!this.raw) this.raw = 0;
                                    e.target.value = this.fmt(this.raw);
                                    clearTimeout(this.timer);
                                    $wire.set('workPlans.{{ $wpIdx }}.activities.{{ $actIdx }}.budget_items.{{ $biIdx }}.unit_price', this.raw);
                                }
                            }" x-init="display = fmt(raw)">
                      <div class="input-group input-group-sm">
                        <span class="input-group-text rkap-rp-chip">Rp</span>
                        <input type="text"
                          class="form-control form-control-sm text-end @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $biIdx . '.unit_price') is-invalid @enderror"
                          :value="display" @input="onInput($event)" @blur="onBlur($event)"
                          @focus="$event.target.select()" placeholder="0" inputmode="numeric"
                          @disabled($isApproved)>
                      </div>
                    </div>
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

                      @if ($itemCount > 1 && !$isApproved)
                      <button type="button"
                        wire:click="removeBudgetItem({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }})"
                        @click="isDirty = true" class="btn btn-sm btn-icon btn-outline-danger"
                        title="Hapus detail rincian ini">
                        <i class="bx bx-trash"></i>
                      </button>
                      @endif

                      @if ($itemIdx === $itemCount - 1 && !$isApproved)
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
                        <span class="badge bg-success rounded-pill rkap-font-06"><i
                          class="bx bx-check"></i> Dist</span>
                        @else
                        <span class="badge bg-warning rounded-pill rkap-font-06">Dist</span>
                        @endif
                        @endif
                        @if (!empty($selectedCashOutMonths))
                        @if (abs($cashOutRemainder) < 0.01)
                          <span class="badge bg-success rounded-pill rkap-font-06"><i
                            class="bx bx-check"></i> Kas</span>
                          @else
                          <span class="badge bg-warning rounded-pill rkap-font-06">Kas</span>
                          @endif
                          @endif
                          @if (!empty($selectedRealizationMonths))
                          @if ($realizationAllocated > 0)
                          <span class="badge bg-success rounded-pill rkap-font-06"><i
                              class="bx bx-trending-up"></i> Real</span>
                          @else
                          <span class="badge bg-secondary rounded-pill rkap-font-06">Real</span>
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
          $qty2 = !empty($bi['unit_2']) ? (float) ($bi['quantity_2'] ?? 1) : 1;
          $biTotal = ((float) ($bi['quantity'] ?? 0)) * $qty2 * ((float) ($bi['unit_price'] ?? 0));
          $monthlyAllocated = array_sum($bi['monthly_distribution'] ?? []);
          $monthlyRemainder = $biTotal - $monthlyAllocated;
          $selectedMonths = $bi['distribution_months'] ?? [];
          $cashOutAllocated = array_sum($bi['cash_out_distribution'] ?? []);
          $cashOutRemainder = $biTotal - $cashOutAllocated;
          $selectedCashOutMonths = $bi['cash_out_months'] ?? [];
          $realizationAllocated = array_sum($bi['realization_distribution'] ?? []);
          $realizationRemainder = $biTotal - $realizationAllocated;
          $selectedRealizationMonths = $bi['realization_months'] ?? [];
          $allMonths = array_unique(
          array_merge($selectedMonths, $selectedCashOutMonths, $selectedRealizationMonths),
          );
          sort($allMonths);
          $modalKey = 'wp' . $wpIdx . '-act' . $actIdx . '-bi' . $biIdx;
          @endphp
          <div wire:key="modal-{{ $wpIdx }}-{{ $actIdx }}-{{ $biIdx }}"
            x-show="modalKey === '{{ $modalKey }}'" x-cloak
            class="position-fixed top-0 start-0 w-100 h-100 overflow-y-auto py-3 px-2 rkap-modal-overlay"
            :class="modalKey === '{{ $modalKey }}' ? 'd-flex align-items-start justify-content-center' : 'd-none'">
            <div x-show="modalKey === '{{ $modalKey }}'"
              x-transition:enter="transition ease-out duration-200"
              x-transition:enter-start="opacity-0 translate-y-4"
              x-transition:enter-end="opacity-100 translate-y-0"
              x-transition:leave="transition ease-in duration-150"
              x-transition:leave-start="opacity-100 translate-y-0"
              x-transition:leave-end="opacity-0 translate-y-4"
              class="bg-white rounded-3 shadow-lg rkap-modal-shell"
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
              <div class="px-4 py-3 rkap-modal-body-scroll">
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
                    </div>
                  </div>
                  <div class="d-flex align-items-center mb-1">
                    <label class="form-label small text-muted mb-0">Pilih Bulan Distribusi Penganggaran:</label>
                  </div>
                  <div class="d-flex flex-wrap gap-1 mb-2">
                    @foreach ($monthLabels as $monthNum => $monthLabel)
                    <button type="button"
                      wire:click="toggleMonth({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }}, {{ $monthNum }})"
                      class="btn btn-sm {{ in_array($monthNum, $selectedMonths) ? 'btn-primary' : 'btn-outline-secondary' }} rkap-month-btn"
                      @disabled($isApproved)>
                      {{ $monthLabel }}
                    </button>
                    @endforeach
                  </div>
                  <div class="d-flex gap-2 mb-2">
                    <button type="button"
                      wire:click="selectAllMonths({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }})"
                      class="btn btn-xs {{ count($selectedMonths) === 12 ? 'btn-outline-danger' : 'btn-outline-secondary' }}"
                      @disabled($isApproved)>
                      <i
                        class="bx {{ count($selectedMonths) === 12 ? 'bx-x-circle' : 'bx-select-multiple' }} me-1"></i>
                      {{ count($selectedMonths) === 12 ? 'Batal Pilih Semua' : 'Pilih Semua Bulan' }}
                    </button>
                    <button type="button"
                      wire:click="distributeEvenly({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }})"
                      class="btn btn-xs btn-outline-primary" @disabled($biTotal <=0 || $isApproved)>
                      <i class="bx bx-equalizer me-1"></i> Bagi Rata Anggaran
                    </button>
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
                      <span class="fw-semibold text-primary small">Rencana Pendanaan</span>
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
                    </div>
                  </div>
                  <div class="d-flex align-items-center mb-1">
                    <label class="form-label small text-muted mb-0">Pilih Bulan Pendanaan:</label>
                  </div>
                  <div class="d-flex flex-wrap gap-1 mb-2">
                    @foreach ($monthLabels as $monthNum => $monthLabel)
                    <button type="button"
                      wire:click="toggleCashOutMonth({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }}, {{ $monthNum }})"
                      class="btn btn-sm {{ in_array($monthNum, $selectedCashOutMonths) ? 'btn-primary' : 'btn-outline-secondary' }} rkap-month-btn"
                      @disabled($isApproved)>
                      {{ $monthLabel }}
                    </button>
                    @endforeach
                  </div>
                  <div class="d-flex gap-2 mb-2">
                    <button type="button"
                      wire:click="selectAllCashOutMonths({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }})"
                      class="btn btn-xs {{ count($selectedCashOutMonths) === 12 ? 'btn-outline-danger' : 'btn-outline-secondary' }}"
                      @disabled($isApproved)>
                      <i
                        class="bx {{ count($selectedCashOutMonths) === 12 ? 'bx-x-circle' : 'bx-select-multiple' }} me-1"></i>
                      {{ count($selectedCashOutMonths) === 12 ? 'Batal Pilih Semua' : 'Pilih Semua Bulan' }}
                    </button>
                    <button type="button"
                      wire:click="distributeCashOutEvenly({{ $wpIdx }}, {{ $actIdx }}, {{ $biIdx }})"
                      class="btn btn-xs btn-outline-primary" @disabled($biTotal <=0 || $isApproved)>
                      <i class="bx bx-equalizer me-1"></i> Bagi Rata Anggaran
                    </button>
                  </div>
                  @error('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $biIdx .
                  '.cash_out')
                  <div class="alert alert-danger small py-2 mb-2"><i
                      class="bx bx-error-circle me-1"></i>{{ $message }}</div>
                  @enderror
                </div>
                {{-- 4-column summary table --}}
                @if (!empty($allMonths))
                <div class="border rounded-2 table-responsive">
                  <table class="table table-sm table-bordered mb-0 rkap-summary-table">
                    <thead class="table-primary">
                      <tr>
                        <th class="text-center rkap-w-80">Bulan</th>
                        <th class="text-end">
                          Distribusi Penganggaran (Rp)
                          <div class="small fw-normal text-muted rkap-summary-note">({{ $bi['coa_group_name'] ?: '-' }})</div>
                        </th>
                        <th class="text-end">
                          Rencana Pendanaan (Rp)
                          <div class="small fw-normal text-muted rkap-summary-note">({{ $bi['cashflow_group_name'] ?: '-' }})</div>
                        </th>
                        <th class="text-end">
                          Selisih (Rp)
                          <div class="small fw-normal text-muted rkap-summary-note">({{ $bi['difference_group_name'] ?: '-' }})</div>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($monthLabels as $monthNum => $monthLabel)
                      @php
                      $isDistribMonth = in_array($monthNum, $selectedMonths);
                      $isCashOutMonth = in_array($monthNum, $selectedCashOutMonths);
                      @endphp
                      @if ($isDistribMonth || $isCashOutMonth)
                      @php
                      $distValue = $isDistribMonth ? (float) ($bi['monthly_distribution'][$monthNum] ?? 0) : 0;
                      $cashOutValue = $isCashOutMonth ? (float) ($bi['cash_out_distribution'][$monthNum] ?? 0) : 0;
                      $selisih = $distValue - $cashOutValue;
                      @endphp
                      <tr>
                        <td class="text-center fw-semibold small align-middle">{{ $monthLabel }}</td>
                         <td class="text-end align-middle">
                          @if ($isDistribMonth)
                          <div x-data="{
                                raw: @entangle('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $biIdx . '.monthly_distribution.' . $monthNum).live,
                                display: '',
                                init() {
                                    this.display = this.format(this.raw);
                                    this.$watch('raw', v => {
                                        this.display = this.format(v);
                                    });
                                },
                                format(val) {
                                    if (val === null || val === undefined || val === '') return '';
                                    let num = Math.round(parseFloat(val));
                                    if (isNaN(num)) return '';
                                    return new Intl.NumberFormat('id-ID').format(num);
                                },
                                onInput(e) {
                                    let cursor = e.target.selectionStart;
                                    let originalLength = e.target.value.length;
                                    let clean = e.target.value.replace(/[^0-9]/g, '');
                                    this.raw = clean === '' ? null : parseFloat(clean);
                                    this.display = this.format(clean);
                                    this.$nextTick(() => {
                                        let newLength = this.display.length;
                                        let diff = newLength - originalLength;
                                        e.target.setSelectionRange(cursor + diff, cursor + diff);
                                    });
                                }
                              }"
                              wire:key="dist-input-{{ $wpIdx }}-{{ $actIdx }}-{{ $biIdx }}-{{ $monthNum }}"
                              class="input-group input-group-sm justify-content-end">
                            <span class="input-group-text rkap-rp-chip">Rp</span>
                            <input type="text" class="form-control form-control-sm text-end rkap-rp-input"
                              :value="display" @input="onInput($event)"
                              @focus="$event.target.select()" placeholder="0" inputmode="numeric"
                              @disabled($isApproved)>
                          </div>
                          @else
                          <span class="text-muted small">—</span>
                          @endif
                        </td>
                        <td class="text-end align-middle">
                          @if ($isCashOutMonth)
                          <div x-data="{
                                raw: @entangle('workPlans.' . $wpIdx . '.activities.' . $actIdx . '.budget_items.' . $biIdx . '.cash_out_distribution.' . $monthNum).live,
                                display: '',
                                init() {
                                    this.display = this.format(this.raw);
                                    this.$watch('raw', v => {
                                        this.display = this.format(v);
                                    });
                                },
                                format(val) {
                                    if (val === null || val === undefined || val === '') return '';
                                    let num = Math.round(parseFloat(val));
                                    if (isNaN(num)) return '';
                                    return new Intl.NumberFormat('id-ID').format(num);
                                },
                                onInput(e) {
                                    let cursor = e.target.selectionStart;
                                    let originalLength = e.target.value.length;
                                    let clean = e.target.value.replace(/[^0-9]/g, '');
                                    this.raw = clean === '' ? null : parseFloat(clean);
                                    this.display = this.format(clean);
                                    this.$nextTick(() => {
                                        let newLength = this.display.length;
                                        let diff = newLength - originalLength;
                                        e.target.setSelectionRange(cursor + diff, cursor + diff);
                                    });
                                }
                              }"
                              wire:key="cashout-input-{{ $wpIdx }}-{{ $actIdx }}-{{ $biIdx }}-{{ $monthNum }}"
                              class="input-group input-group-sm justify-content-end">
                            <span class="input-group-text rkap-rp-chip">Rp</span>
                            <input type="text" class="form-control form-control-sm text-end rkap-rp-input"
                              :value="display" @input="onInput($event)"
                              @focus="$event.target.select()" placeholder="0" inputmode="numeric"
                              @disabled($isApproved)>
                          </div>
                          @else
                          <span class="text-muted small">—</span>
                          @endif
                        </td>
                        <td class="text-end align-middle fw-semibold">
                          @if ($selisih < 0)
                            <span class="text-danger">-Rp {{ number_format(abs($selisih), 0, ',', '.') }}</span>
                            @elseif ($selisih > 0)
                            <span class="text-success">Rp {{ number_format($selisih, 0, ',', '.') }}</span>
                            @else
                            <span class="text-muted">Rp 0</span>
                            @endif
                        </td>
                      </tr>
                      @endif
                      @endforeach
                    </tbody>
                    <tfoot class="table-light">
                      @php
                      $totalSelisih = $monthlyAllocated - $cashOutAllocated;
                      $sisaSelisih = $monthlyRemainder - $cashOutRemainder;
                      @endphp
                      <tr>
                        <th class="text-center small">Total</th>
                        <th
                          class="text-end small {{ abs($monthlyRemainder) < 0.01 ? 'text-success' : ($monthlyRemainder < 0 ? 'text-danger' : 'text-warning') }}">
                          Rp {{ number_format($monthlyAllocated, 0, ',', '.') }}
                        </th>
                        <th
                          class="text-end small {{ abs($cashOutRemainder) < 0.01 ? 'text-success' : ($cashOutRemainder < 0 ? 'text-danger' : 'text-warning') }}">
                          Rp {{ number_format($cashOutAllocated, 0, ',', '.') }}
                        </th>
                        <th class="text-end small {{ $totalSelisih < 0 ? 'text-danger' : ($totalSelisih > 0 ? 'text-success' : 'text-muted') }}">
                          {{ $totalSelisih < 0 ? '-Rp ' . number_format(abs($totalSelisih), 0, ',', '.') : 'Rp ' . number_format($totalSelisih, 0, ',', '.') }}
                        </th>
                      </tr>
                      <tr>
                        <th class="text-center small text-muted">Sisa</th>
                        <th
                          class="text-end small {{ abs($monthlyRemainder) < 0.01 ? 'text-success' : ($monthlyRemainder < 0 ? 'text-danger' : 'text-warning') }}">
                          Rp {{ number_format($monthlyRemainder, 0, ',', '.') }}
                        </th>
                        <th
                          class="text-end small {{ abs($cashOutRemainder) < 0.01 ? 'text-success' : ($cashOutRemainder < 0 ? 'text-danger' : 'text-warning') }}">
                          Rp {{ number_format($cashOutRemainder, 0, ',', '.') }}
                        </th>
                        <th class="text-end small {{ $sisaSelisih < 0 ? 'text-danger' : ($sisaSelisih > 0 ? 'text-success' : 'text-muted') }}">
                          {{ $sisaSelisih < 0 ? '-Rp ' . number_format(abs($sisaSelisih), 0, ',', '.') : 'Rp ' . number_format($sisaSelisih, 0, ',', '.') }}
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

          @if (!$isApproved)
          <button type="button" wire:click="addBudgetItem({{ $wpIdx }}, {{ $actIdx }})"
            class="btn btn-sm btn-label-secondary mt-2">
            <i class="bx bx-plus me-1"></i> Tambah Item Belanja
          </button>
          @endif

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
      @if ($wp['work_plan_id'] && !$hasApproved)
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

  {{-- Shared datalist for Satuan selection --}}
  <datalist id="satuan-options">
    @foreach ($this->satuanOptions as $satuanOpt)
    <option value="{{ $satuanOpt->name }}"></option>
    @endforeach
  </datalist>

  {{-- Modal Upload File Referensi --}}
  <div class="modal fade" id="uploadFileModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bx bx-upload me-2 text-primary"></i>Upload File Referensi</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-start">
          <form wire:submit.prevent="handleFileUpload">
            <div class="mb-3">
              <label class="form-label fw-semibold">Pilih File</label>
              <input type="file" class="form-control @error('referenceFile') is-invalid @enderror" wire:model="referenceFile">
              @error('referenceFile')
              <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
              <div class="form-text text-muted small mt-1">
                Tipe file yang diperbolehkan: Word (doc, docx), Excel (xls, xlsx), PDF, Zip, dan Gambar. Maksimal ukuran 2 MB.
              </div>
              <div wire:loading wire:target="referenceFile" class="text-info mt-2 small">
                <i class="bx bx-loader-alt bx-spin me-1"></i> Mengunggah ke penyimpanan sementara...
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-primary" wire:click="handleFileUpload" wire:loading.attr="disabled" wire:target="referenceFile" @disabled(!$referenceFile)>
            Upload
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    window.addEventListener('open-upload-modal', event => {
      var myModal = new bootstrap.Modal(document.getElementById('uploadFileModal'));
      myModal.show();
    });
    window.addEventListener('close-upload-modal', event => {
      var myModalEl = document.getElementById('uploadFileModal');
      var modal = bootstrap.Modal.getInstance(myModalEl);
      if (modal) {
        modal.hide();
      }
    });
  </script>
</div>