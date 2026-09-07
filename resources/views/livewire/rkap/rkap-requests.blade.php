<div>
  <div class="d-flex justify-content-between align-items-center py-3 mb-4">
    <h4 class="mb-0">
      <span class="text-muted fw-light">RKAP /</span> {{ __('Usulan Program & Kegiatan Baru') }}
    </h4>
    @if(!Auth::user()->can('masterdata.request.approve'))
    <button class="btn btn-primary" wire:click="openRequestModal">
      <i class="bx bx-plus me-1"></i> {{ __('Buat Usulan Baru') }}
    </button>
    @endif
  </div>

  @if (session()->has('message'))
  <div class="alert alert-success alert-dismissible" role="alert">
    {{ session('message') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  @endif

  @if (session()->has('error'))
  <div class="alert alert-danger alert-dismissible" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  @endif

  {{-- Filters and Search --}}
  <div class="card mb-4">
    <div class="card-header border-bottom">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        {{-- Navigation Tabs --}}
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
          <li class="nav-item">
            <button class="nav-link @if($activeTab === 'work_plan') active @endif" wire:click="$set('activeTab', 'work_plan')">
              <i class="bx bx-task me-1"></i> {{ __('Usulan Program Kerja') }}
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link @if($activeTab === 'activity') active @endif" wire:click="$set('activeTab', 'activity')">
              <i class="bx bx-list-check me-1"></i> {{ __('Usulan Kegiatan') }}
            </button>
          </li>
        </ul>

        {{-- Search and Filter --}}
        <div class="d-flex align-items-center gap-3 flex-wrap flex-grow-1 justify-content-md-end">
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="onlyPending" wire:model.live="onlyPending" style="cursor:pointer">
            <label class="form-check-label small fw-semibold text-nowrap" for="onlyPending" style="cursor:pointer">
              <i class="bx bx-time-five me-1 text-warning"></i>{{ __('Belum ditanggapi') }}
            </label>
          </div>
          <div class="rkap-w-300 w-100">
            <div class="input-group input-group-merge">
              <span class="input-group-text"><i class="bx bx-search"></i></span>
              <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="{{ __('Cari usulan...') }}">
            </div>
          </div>
        </div>
      </div>
    </div>

    @if($onlyPending)
    <div class="card-body py-2 px-4 border-bottom bg-light bg-opacity-50">
      <div class="alert alert-warning py-2 px-3 mb-0 d-flex align-items-center gap-2 small">
        <i class="bx bx-filter-alt flex-shrink-0"></i>
        Menampilkan usulan yang <strong class="ms-1">belum ditanggapi (menunggu persetujuan)</strong>.
        <button wire:click="$set('onlyPending', false)" class="btn btn-xs btn-link p-0 ms-2 text-warning">
          {{ __('Tampilkan semua') }}
        </button>
      </div>
    </div>
    @endif

    {{-- Requests Table List --}}
    <div class="table-responsive text-nowrap">
      <table class="table table-hover align-middle mb-0">
        <thead>
          @if($activeTab === 'work_plan')
          <tr>
            <th>{{ __('Kode') }}</th>
            <th>{{ __('Nama Program Kerja') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Biro Pengusul') }}</th>
            <th>{{ __('Tanggal Diusulkan') }}</th>
            @if($isApprover)
            <th class="text-center rkap-w-150">{{ __('Aksi') }}</th>
            @endif
          </tr>
          @else
          <tr>
            <th>{{ __('Program Kerja') }}</th>
            <th>{{ __('Kode') }}</th>
            <th>{{ __('Nama Kegiatan') }}</th>
            <th>{{ __('Deskripsi') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Biro Pengusul') }}</th>
            <th>{{ __('Tanggal Diusulkan') }}</th>
            @if($isApprover)
            <th class="text-center rkap-w-150">{{ __('Aksi') }}</th>
            @endif
          </tr>
          @endif
        </thead>
        <tbody>
          @forelse($requests as $req)
          <tr wire:key="request-row-{{ $req->id }}">
            @if($activeTab === 'work_plan')
            <td>
              @if($req->approval_status === 'approved')
              <strong>{{ $req->code }}</strong>
              @elseif($req->approval_status === 'pending')
              <span class="text-muted text-warning fw-semibold"><em>[Menunggu Kode]</em></span>
              @else
              <span class="text-muted"><em>-</em></span>
              @endif
            </td>
            <td>{{ $req->title }}</td>
            <td>
              @if($req->approval_status === 'pending')
              <span class="badge bg-label-warning">{{ __('Menunggu Persetujuan') }}</span>
              @elseif($req->approval_status === 'approved')
              <span class="badge bg-label-success">{{ __('Disetujui') }}</span>
              @else
              <span class="badge bg-label-danger">{{ __('Ditolak') }}</span>
              @if($req->rejection_note)
              <div class="mt-1 small text-danger rkap-ws-normal" style="max-width:220px" title="{{ $req->rejection_note }}">
                <i class="bx bx-comment-x me-1"></i>{{ Str::limit($req->rejection_note, 80) }}
              </div>
              @endif
              @endif
            </td>
            <td>{{ $req->requestedBureau->name ?? '-' }}</td>
            <td>{{ $req->created_at ? $req->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') : '-' }}</td>
            @if($isApprover)
            <td class="text-center">
              @if($req->approval_status === 'pending')
              <div class="d-flex justify-content-center gap-1">
                <button class="btn btn-xs btn-success" wire:click="openApproveModal('work_plan', {{ $req->id }})">
                  <i class="bx bx-check me-0.5"></i> {{ __('Setuju') }}
                </button>
                <button class="btn btn-xs btn-danger" wire:click="openRejectModal('work_plan', {{ $req->id }})">
                  <i class="bx bx-x me-0.5"></i> {{ __('Tolak') }}
                </button>
              </div>
              @else
              <span class="text-muted small">{{ __('Selesai') }} diproses</span>
              @endif
            </td>
            @endif
            @else
            <td class="text-wrap">
              @if($req->workPlan)
              <span class="fw-semibold text-primary">{{ $req->workPlan->code }}</span> — {{ $req->workPlan->title }}
              @else
              -
              @endif
            </td>
            <td>
              @if($req->approval_status === 'approved')
              <strong>{{ $req->code }}</strong>
              @elseif($req->approval_status === 'pending')
              <span class="text-muted text-warning fw-semibold"><em>[Menunggu Kode]</em></span>
              @else
              <span class="text-muted"><em>-</em></span>
              @endif
            </td>
            <td class="text-wrap">{{ $req->title }}</td>
            <td class="text-wrap rkap-mw-250">{{ $req->description ?: '-' }}</td>
            <td>
              @if($req->approval_status === 'pending')
              <span class="badge bg-label-warning">{{ __('Menunggu Persetujuan') }}</span>
              @elseif($req->approval_status === 'approved')
              <span class="badge bg-label-success">{{ __('Disetujui') }}</span>
              @else
              <span class="badge bg-label-danger">{{ __('Ditolak') }}</span>
              @if($req->rejection_note)
              <div class="mt-1 small text-danger rkap-ws-normal" style="max-width:220px" title="{{ $req->rejection_note }}">
                <i class="bx bx-comment-x me-1"></i>{{ Str::limit($req->rejection_note, 80) }}
              </div>
              @endif
              @endif
            </td>
            <td>{{ $req->requestedBureau->name ?? '-' }}</td>
            <td>{{ $req->created_at ? $req->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') : '-' }}</td>
            @if($isApprover)
            <td class="text-center">
              @if($req->approval_status === 'pending')
              <div class="d-flex justify-content-center gap-1">
                <button class="btn btn-xs btn-success" wire:click="openApproveModal('activity', {{ $req->id }})">
                  <i class="bx bx-check me-0.5"></i> {{ __('Setuju') }}
                </button>
                <button class="btn btn-xs btn-danger" wire:click="openRejectModal('activity', {{ $req->id }})">
                  <i class="bx bx-x me-0.5"></i> {{ __('Tolak') }}
                </button>
              </div>
              @else
              <span class="text-muted small">{{ __('Selesai') }} diproses</span>
              @endif
            </td>
            @endif
            @endif
          </tr>
          @empty
          <tr>
            <td colspan="10" class="text-center py-4 text-muted">
              <i class="bx bx-info-circle fs-3 mb-2 d-block"></i> Belum ada usulan data.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Pagination --}}
    @if($requests->hasPages())
    <div class="card-footer border-top d-flex justify-content-between align-items-center">
      <div class="small text-muted">
        Menampilkan {{ $requests->firstItem() ?? 0 }} - {{ $requests->lastItem() ?? 0 }} dari {{ $requests->total() }} data
      </div>
      <div>
        {{ $requests->links('layouts.pagination') }}
      </div>
    </div>
    @endif
  </div>

  {{-- Interactive Request Creation Modal --}}
  @if($isModalOpen)
  <div class="modal fade show rkap-modal-show rkap-z-1080" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-bottom">
          <h5 class="modal-title"><i class="bx bx-git-pull-request me-1 text-primary"></i> Buat Usulan Baru</h5>
          <button type="button" class="btn-close" wire:click="closeRequestModal"></button>
        </div>
        <form wire:submit.prevent="submitRequest">
          <div class="modal-body">
            {{-- Type Selector --}}
            <div class="mb-3">
              <label class="form-label fw-semibold">{{ __('Jenis Usulan') }}</label>
              <div class="d-flex gap-3 mt-1">
                <div class="form-check">
                  <input class="form-check-input" type="radio" value="work_plan" id="typeWorkPlan" wire:model.live="requestType">
                  <label class="form-check-label" for="typeWorkPlan">{{ __('Program Kerja Baru') }}</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" value="activity" id="typeActivity" wire:model.live="requestType">
                  <label class="form-check-label" for="typeActivity">{{ __('Kegiatan Baru') }}</label>
                </div>
              </div>
            </div>

            <hr class="my-3">

            {{-- Fields for Work Plan Request --}}
            @if($requestType === 'work_plan')
            <div class="mb-3">
              <label for="wpTitle" class="form-label fw-semibold">Nama Program Kerja <span class="text-danger">*</span></label>
              <input type="text" id="wpTitle" class="form-control @error('wpTitle') is-invalid @enderror" wire:model.defer="wpTitle" placeholder="Misal: Peningkatan Kapasitas SDM">
              @error('wpTitle')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            @else
            {{-- Fields for Activity Request --}}
            <div class="mb-3">
              <label for="actWorkPlanId" class="form-label fw-semibold">Pilih Program Kerja Terkait <span class="text-danger">*</span></label>
              @php
                $selectedActWp = $actWorkPlanId
                    ? $approvedWorkPlans->firstWhere('id', $actWorkPlanId)
                    : null;
                $selectedActWpLabel = $selectedActWp
                    ? $selectedActWp->code . ' — ' . $selectedActWp->title
                    : '';
              @endphp
              <div x-data="{
                  open: false,
                  search: @js($selectedActWpLabel),
                  currentLabel: @js($selectedActWpLabel),
                }"
                class="position-relative"
                @click.outside="open = false"
                x-effect="if (!open && search !== currentLabel) search = currentLabel">

                <div class="input-group">
                  <input type="text"
                    id="actWorkPlanId_search"
                    class="form-control @error('actWorkPlanId') is-invalid @enderror"
                    placeholder="{{ __('Cari program kerja...') }}"
                    x-model="search"
                    @focus="open = true"
                    @input="open = true"
                    autocomplete="off">
                  @if($actWorkPlanId)
                  <button type="button" class="btn btn-outline-secondary"
                    wire:click="$set('actWorkPlanId', null)"
                    @click="search = ''; currentLabel = ''; open = false"
                    title="{{ __('Hapus pilihan') }}">
                    <i class="bx bx-x"></i>
                  </button>
                  @endif
                </div>

                @error('actWorkPlanId')
                <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror

                {{-- Hidden select as Livewire binding --}}
                <select wire:model="actWorkPlanId" id="actWorkPlanId" class="d-none">
                  <option value=""></option>
                  @if($actWorkPlanId)
                    @if($selectedActWp)
                      <option value="{{ $selectedActWp->id }}" selected>{{ $selectedActWp->code }} — {{ $selectedActWp->title }}</option>
                    @endif
                  @endif
                </select>

                {{-- Dropdown options --}}
                <div x-show="open" x-cloak
                  class="position-absolute bg-white border rounded shadow-sm w-100 mt-1"
                  style="z-index: 1055; max-height: 200px; overflow-y: auto;">
                  @forelse($approvedWorkPlans as $wpOpt)
                  <div
                    class="px-3 py-2 cursor-pointer dropdown-item small {{ $actWorkPlanId == $wpOpt->id ? 'bg-primary text-white' : '' }}"
                    x-show="search === '' || '{{ strtolower($wpOpt->code . ' ' . $wpOpt->title) }}'.includes(search.toLowerCase())"
                    @click="
                      $wire.set('actWorkPlanId', {{ $wpOpt->id }});
                      search = '{{ addslashes($wpOpt->code . ' — ' . $wpOpt->title) }}';
                      currentLabel = search;
                      open = false;
                    ">
                    <span class="fw-semibold text-primary">{{ $wpOpt->code }}</span>
                    <span class="ms-1 text-muted">{{ $wpOpt->title }}</span>
                  </div>
                  @empty
                  <div class="px-3 py-2 text-muted small">{{ __('Tidak ada data program kerja.') }}</div>
                  @endforelse
                  <div class="px-3 py-2 text-muted small border-top">
                    <i class="bx bx-info-circle me-1"></i>Ketik untuk menyaring program kerja.
                  </div>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label for="actTitle" class="form-label fw-semibold">Nama Kegiatan <span class="text-danger">*</span></label>
              <input type="text" id="actTitle" class="form-control @error('actTitle') is-invalid @enderror" wire:model.defer="actTitle" placeholder="Misal: Sertifikasi Keahlian IT">
              @error('actTitle')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="mb-3">
              <label for="actDescription" class="form-label fw-semibold">{{ __('Deskripsi / Tujuan Kegiatan') }}</label>
              <textarea id="actDescription" class="form-control" wire:model.defer="actDescription" rows="3" placeholder="{{ __('Sebutkan tujuan detail kegiatan...') }}"></textarea>
            </div>
            @endif
          </div>
          <div class="modal-footer border-top">
            <button type="button" class="btn btn-outline-secondary" wire:click="closeRequestModal">{{ __('Batal') }}</button>
            <button type="submit" class="btn btn-primary">{{ __('Kirim Usulan') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  @endif

  {{-- Interactive Code Assignment Modal (Approval) --}}
  @if($isApproveModalOpen)
  <div class="modal fade show rkap-modal-show rkap-z-1080" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-bottom">
          <h5 class="modal-title">
            <i class="bx bx-check-shield me-1 text-success"></i>
            Persetujuan Usulan {{ $approveType === 'work_plan' ? 'Program Kerja' : 'Kegiatan' }}
          </h5>
          <button type="button" class="btn-close" wire:click="closeApproveModal"></button>
        </div>
        <form wire:submit.prevent="confirmApprove">
          <div class="modal-body">
            <p class="mb-3 text-wrap text-secondary">
              Anda akan menyetujui usulan {{ $approveType === 'work_plan' ? 'program kerja' : 'kegiatan' }} baru. Silakan tentukan kode resmi yang akan digunakan dalam master data.
            </p>
            <div class="mb-3">
              <label for="approvalCode" class="form-label fw-semibold">
                Kode Resmi {{ $approveType === 'work_plan' ? 'Program Kerja' : 'Kegiatan' }} <span class="text-danger">*</span>
              </label>
              <input type="text" id="approvalCode" class="form-control @error('approvalCode') is-invalid @enderror" wire:model.defer="approvalCode" placeholder="Misal: {{ $approveType === 'work_plan' ? 'P.01.03' : 'K.01.03.001' }}" autofocus>
              @error('approvalCode')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="modal-footer border-top">
            <button type="button" class="btn btn-outline-secondary" wire:click="closeApproveModal">{{ __('Batal') }}</button>
            <button type="submit" class="btn btn-success">{{ __('Setujui & Simpan') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  @endif

  {{-- Rejection Note Modal --}}
  @if($isRejectModalOpen)
  <div class="modal fade show rkap-modal-show rkap-z-1080" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-bottom bg-danger bg-opacity-10">
          <h5 class="modal-title text-danger">
            <i class="bx bx-x-circle me-1"></i>
            Tolak Usulan {{ $rejectType === 'work_plan' ? 'Program Kerja' : 'Kegiatan' }}
          </h5>
          <button type="button" class="btn-close" wire:click="closeRejectModal"></button>
        </div>
        <form wire:submit.prevent="confirmReject">
          <div class="modal-body">
            <div class="alert alert-warning py-2 px-3 mb-3 small">
              <i class="bx bx-info-circle me-1"></i>
              Catatan penolakan akan ditampilkan kepada biro pengusul agar mereka mengetahui alasan penolakan.
            </div>
            <div class="mb-3">
              <label for="rejectionNote" class="form-label fw-semibold">
                Catatan / Alasan Penolakan <span class="text-danger">*</span>
              </label>
              <textarea
                id="rejectionNote"
                class="form-control @error('rejectionNote') is-invalid @enderror"
                wire:model.defer="rejectionNote"
                rows="4"
                placeholder="Jelaskan alasan penolakan agar pengusul dapat memahami dan memperbaiki usulannya..."
                autofocus></textarea>
              @error('rejectionNote')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <div class="form-text text-muted">Minimal 5 karakter, maksimal 1000 karakter.</div>
            </div>
          </div>
          <div class="modal-footer border-top">
            <button type="button" class="btn btn-outline-secondary" wire:click="closeRejectModal">{{ __('Batal') }}</button>
            <button type="submit" class="btn btn-danger">
              <span wire:loading.remove wire:target="confirmReject">
                <i class="bx bx-x me-1"></i>Konfirmasi Penolakan
              </span>
              <span wire:loading wire:target="confirmReject">
                <span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...
              </span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  @endif

</div>