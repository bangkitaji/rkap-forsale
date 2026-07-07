<div x-data="{ isDirty: false }" @change="isDirty = true">

  {{-- Page Header --}}
  <div class="py-3 mb-4 d-flex justify-content-between align-items-center">
    <h4 class="mb-0">
      <span class="text-muted fw-light">Master Data /</span> Activity ↔ COA Mapping
    </h4>
    <button wire:click="openUploadModal()" class="btn btn-outline-info btn-sm">
      <i class="bx bx-upload me-1"></i> Import Excel
    </button>
  </div>

  {{-- Flash Messages --}}
  @if (session()->has('message'))
  <div class="alert alert-success alert-dismissible mb-4" role="alert">
    <i class="bx bx-check-circle me-2"></i>{{ session('message') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  @endif
  @if (session()->has('error'))
  <div class="alert alert-danger alert-dismissible mb-4" role="alert">
    <i class="bx bx-x-circle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  @endif

  <div class="row g-4 align-items-start">

    {{-- ═══════════════════════════════════════════════
         LEFT PANEL — Activity Selector
    ═══════════════════════════════════════════════ --}}
    <div class="col-lg-4">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center gap-2">
          <i class="bx bx-task text-primary fs-5"></i>
          <strong class="fs-6">Pilih Kegiatan</strong>
        </div>
        <div class="card-body p-3">

          {{-- Search box --}}
          <div class="input-group mb-3">
            <span class="input-group-text bg-light border-end-0">
              <i class="bx bx-search text-muted"></i>
            </span>
            <input
              type="text"
              class="form-control border-start-0 ps-0"
              wire:model.live.debounce.300ms="activitySearch"
              placeholder="Cari kegiatan..."
              autocomplete="off">
            @if(!empty($activitySearch))
            <button class="btn btn-outline-secondary border" wire:click="$set('activitySearch', '')" type="button">
              <i class="bx bx-x"></i>
            </button>
            @endif
          </div>

          {{-- Selected badge --}}
          @if ($selectedActivity)
          <div class="alert alert-primary py-2 px-3 mb-3 d-flex align-items-start gap-2 rkap-border-l-4-primary">
            <i class="bx bx-check-circle text-primary mt-1 flex-shrink-0"></i>
            <div class="flex-grow-1 min-width-0">
              <div class="fw-semibold small rkap-lh-13">{{ $selectedActivity->code }}</div>
              <div class="text-muted rkap-font-078 rkap-word-break-word">{{ $selectedActivity->title }}</div>
              @if($selectedActivity->workPlan)
              <div class="mt-1">
                <span class="badge bg-label-secondary rkap-font-07">{{ $selectedActivity->workPlan->code }}</span>
              </div>
              @endif
            </div>
            <button type="button" class="btn btn-xs btn-icon btn-text-danger flex-shrink-0" wire:click="clearActivity" title="Batalkan pilihan">
              <i class="bx bx-x"></i>
            </button>
          </div>
          @endif

          {{-- Activity list --}}
          <div class="rkap-h460-scroll">
            @forelse($activities as $activity)
            <button
              type="button"
              wire:click="selectActivity({{ $activity->id }})"
              @click="isDirty = false"
              class="w-100 text-start border rounded-2 mb-2 px-3 py-2 d-flex align-items-start gap-2 position-relative rkap-cursor-pointer rkap-transition-15
                     {{ $activityId == $activity->id
                          ? 'bg-primary text-white border-primary'
                          : 'bg-white text-body border-light-subtle hover-bg-light' }}">
              <i class="bx bx-task mt-1 flex-shrink-0 {{ $activityId == $activity->id ? 'text-white' : 'text-primary' }}"></i>
              <div class="flex-grow-1 min-width-0">
                <div class="fw-semibold small lh-sm">{{ $activity->code }}</div>
                <div class="small rkap-font-078 rkap-ws-normal rkap-word-break-word {{ $activityId == $activity->id ? 'text-white opacity-75' : 'text-muted' }}">
                  {{ $activity->title }}
                </div>
                @if($activity->workPlan)
                <div class="mt-1">
                  <span class="badge {{ $activityId == $activity->id ? 'bg-white text-primary' : 'bg-label-secondary' }} rkap-font-065">
                    {{ $activity->workPlan->code }}
                  </span>
                </div>
                @endif
              </div>
              @if($activityId == $activity->id)
              <i class="bx bx-check-circle text-white mt-1 flex-shrink-0"></i>
              @endif
            </button>
            @empty
            <div class="text-center text-muted py-4">
              <i class="bx bx-search-alt bx-lg d-block mb-2 opacity-50"></i>
              <small>Tidak ada kegiatan ditemukan.</small>
            </div>
            @endforelse
          </div>

        </div>
      </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         RIGHT PANEL — COA Mapping
    ═══════════════════════════════════════════════ --}}
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">

        {{-- Panel Header --}}
        <div class="card-header bg-white border-bottom py-3">
          <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div class="d-flex align-items-center gap-2">
              <i class="bx bx-link-alt text-primary fs-5"></i>
              <strong class="fs-6">Pemetaan COA</strong>
              @if($activityId)
              <span class="badge bg-primary rounded-pill">{{ count($selectedCoaIds) }} dipilih</span>
              @endif
            </div>
            @if($activityId)
            <div class="d-flex align-items-center gap-2 flex-wrap">
              @if(!empty($selectedCoaIds))
              <button type="button"
                wire:click="deselectAll"
                class="btn btn-sm btn-outline-danger">
                <i class="bx bx-minus-circle me-1"></i>Kosongkan Pilihan
              </button>
              @endif
              <button type="button"
                wire:click="selectAll"
                class="btn btn-sm btn-outline-primary">
                <i class="bx bx-select-multiple me-1"></i>Pilih Semua Halaman Ini
              </button>
              <button wire:click="save"
                @click="isDirty = false"
                class="btn btn-primary btn-sm">
                <span wire:loading.remove wire:target="save">
                  <i class="bx bx-save me-1"></i> Simpan Mapping
                </span>
                <span wire:loading wire:target="save">
                  <span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...
                </span>
              </button>
            </div>
            @endif
          </div>
        </div>

        <div class="card-body p-3">
          @if(!$activityId)
          {{-- Empty state --}}
          <div class="text-center py-5 text-muted">
            <i class="bx bx-left-arrow-alt bx-lg d-block mb-3 opacity-40"></i>
            <p class="mb-1 fw-semibold">Pilih kegiatan terlebih dahulu</p>
            <small>Klik salah satu kegiatan di panel kiri untuk mulai mengatur pemetaan COA.</small>
          </div>
          @else

          {{-- COA search --}}
          <div class="input-group mb-3">
            <span class="input-group-text bg-light border-end-0">
              <i class="bx bx-search text-muted"></i>
            </span>
            <input
              type="text"
              class="form-control border-start-0 ps-0"
              wire:model.live.debounce.300ms="coaSearch"
              placeholder="Cari COA (kode / judul / deskripsi)...">
            @if(!empty($coaSearch))
            <button class="btn btn-outline-secondary border" wire:click="$set('coaSearch', '')" type="button">
              <i class="bx bx-x"></i>
            </button>
            @endif
          </div>

          {{-- Mapped COAs section (pinned to top) --}}
          @php
          // $mappedCoas is passed directly from the component to ensure mapped COAs are pinned globally.
          // $coas contains the paginated list of unmapped COAs.
          @endphp

          @if($mappedCoas->isNotEmpty())
          <div class="mb-3">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="badge bg-success">
                <i class="bx bx-check me-1"></i>Dipetakan ({{ $mappedCoas->count() }})
              </span>
              <div class="flex-grow-1 border-bottom"></div>
            </div>
            <div class="rounded-2 overflow-hidden border border-success border-opacity-25">
              @foreach($mappedCoas as $coa)
              <label for="coa_{{ $coa->id }}"
                class="d-flex align-items-center gap-3 px-3 py-2 cursor-pointer bg-success bg-opacity-10 rkap-transition-1
                       {{ !$loop->last ? 'border-bottom border-success border-opacity-10' : '' }}">
                <input
                  type="checkbox"
                  class="form-check-input flex-shrink-0 mt-0 rkap-checkbox-11"
                  value="{{ $coa->id }}"
                  wire:model.live="selectedCoaIds"
                  id="coa_{{ $coa->id }}">
                <div class="flex-grow-1 min-width-0">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-success fw-semibold rkap-font-075">{{ $coa->code }}</span>
                    <span class="fw-semibold small text-success-emphasis">{{ $coa->title }}</span>
                  </div>
                  @if($coa->description)
                  <div class="text-muted mt-1 rkap-font-075">{{ Str::limit($coa->description, 80) }}</div>
                  @endif
                </div>
                <i class="bx bx-check-circle text-success fs-5 flex-shrink-0"></i>
              </label>
              @endforeach
            </div>
          </div>
          @endif

          {{-- Unmapped COAs --}}
          @if($coas->isNotEmpty())
          <div class="mb-3">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="badge bg-label-secondary text-muted">
                Belum dipetakan ({{ $coas->total() }})
              </span>
              <div class="flex-grow-1 border-bottom"></div>
            </div>
            <div class="rounded-2 overflow-hidden border">
              @foreach($coas as $coa)
              <label for="coa_{{ $coa->id }}"
                class="d-flex align-items-center gap-3 px-3 py-2 cursor-pointer rkap-transition-12 rkap-cursor-pointer
                       {{ !$loop->last ? 'border-bottom' : '' }}">
                <input
                  type="checkbox"
                  class="form-check-input flex-shrink-0 mt-0 rkap-checkbox-11"
                  value="{{ $coa->id }}"
                  wire:model.live="selectedCoaIds"
                  id="coa_{{ $coa->id }}">
                <div class="flex-grow-1 min-width-0">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-label-secondary fw-semibold rkap-font-075">{{ $coa->code }}</span>
                    <span class="small">{{ $coa->title }}</span>
                  </div>
                  @if($coa->description)
                  <div class="text-muted mt-1 rkap-font-075">{{ Str::limit($coa->description, 80) }}</div>
                  @endif
                </div>
              </label>
              @endforeach
            </div>
          </div>
          @endif

          @if($mappedCoas->isEmpty() && $coas->isEmpty())
          <div class="text-center text-muted py-4">
            <i class="bx bx-search-alt bx-lg d-block mb-2 opacity-50"></i>
            <small>Tidak ada COA ditemukan.</small>
          </div>
          @endif

          {{-- Pagination --}}
          <div class="mt-3">
            {{ $coas->links() }}
          </div>

          @endif {{-- end if $activityId --}}
        </div>

        {{-- Sticky save footer (only shown when activity is selected and there are changes) --}}
        @if($activityId)
        <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center"
          x-show="isDirty"
          x-transition:enter="transition ease-out duration-200"
          x-transition:enter-start="opacity-0 translate-y-2"
          x-transition:enter-end="opacity-100 translate-y-0">
          <span class="text-warning small">
            <i class="bx bx-error-circle me-1"></i> Ada perubahan yang belum disimpan.
          </span>
          <button wire:click="save" @click="isDirty = false" class="btn btn-primary btn-sm">
            <span wire:loading.remove wire:target="save">
              <i class="bx bx-save me-1"></i> Simpan Sekarang
            </span>
            <span wire:loading wire:target="save">
              <span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...
            </span>
          </button>
        </div>
        @endif

      </div>
    </div>

  </div>{{-- end .row --}}

  {{-- ═══════════════ Upload Modal ═══════════════ --}}
  @if($isUploadModalOpen)
  <div class="modal fade show rkap-modal-show" tabindex="-1" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bx bx-upload me-2 text-info"></i>Import Activity-COA Mappings
          </h5>
          <button type="button" class="btn-close" wire:click="closeUploadModal()"></button>
        </div>
        <form wire:submit.prevent="importExcel">
          <div class="modal-body">
            <p class="mb-3 text-muted small">
              Upload file Excel untuk mengimport mapping activity-COA.
              <a href="{{ route('download-activity-coa-mapping-template') }}" class="btn btn-sm btn-link p-0">
                <i class="bx bx-download me-1"></i>Download template
              </a>
            </p>
            <div class="mb-3">
              <label for="uploadedFile" class="form-label">File Excel</label>
              <input type="file" id="uploadedFile"
                class="form-control @error('uploadedFile') is-invalid @enderror"
                wire:model="uploadedFile" accept=".xlsx,.xls,.csv">
              @error('uploadedFile') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            @if($importMessage)
            <div class="alert alert-{{ $importStatus === 'success' ? 'success' : 'danger' }} alert-dismissible" role="alert">
              <pre class="mb-0 rkap-font-0875 text-wrap">{{ $importMessage }}</pre>
            </div>
            @endif
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-label-secondary" wire:click="closeUploadModal()">Batal</button>
            <button type="submit" class="btn btn-primary" @if($uploadedFile===null) disabled @endif>
              <i class="bx bx-upload me-1"></i> Import
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  @endif

</div>