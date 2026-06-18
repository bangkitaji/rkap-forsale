<div>
  <div class="d-flex justify-content-between align-items-center py-3 mb-4">
    <h4 class="mb-0">
      <span class="text-muted fw-light">RKAP /</span> Usulan Program & Kegiatan Baru
    </h4>
    @if(!Auth::user()->can('masterdata.request.approve'))
      <button class="btn btn-primary" wire:click="openRequestModal">
        <i class="bx bx-plus me-1"></i> Buat Usulan Baru
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
              <i class="bx bx-task me-1"></i> Usulan Program Kerja
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link @if($activeTab === 'activity') active @endif" wire:click="$set('activeTab', 'activity')">
              <i class="bx bx-list-check me-1"></i> Usulan Kegiatan
            </button>
          </li>
        </ul>

        {{-- Search input --}}
        <div style="max-width: 300px; width: 100%;">
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="bx bx-search"></i></span>
            <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Cari usulan...">
          </div>
        </div>
      </div>
    </div>

    {{-- Requests Table List --}}
    <div class="table-responsive text-nowrap">
      <table class="table table-hover align-middle mb-0">
        <thead>
          @if($activeTab === 'work_plan')
            <tr>
              <th>Kode</th>
              <th>Nama Program Kerja</th>
              <th>Status</th>
              <th>Biro Pengusul</th>
              <th>Tanggal Diusulkan</th>
              @if($isApprover)
                <th class="text-center" style="width: 150px;">Aksi</th>
              @endif
            </tr>
          @else
            <tr>
              <th>Program Kerja</th>
              <th>Kode</th>
              <th>Nama Kegiatan</th>
              <th>Deskripsi</th>
              <th>Status</th>
              <th>Biro Pengusul</th>
              <th>Tanggal Diusulkan</th>
              @if($isApprover)
                <th class="text-center" style="width: 150px;">Aksi</th>
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
                    <span class="badge bg-label-warning">Menunggu Persetujuan</span>
                  @elseif($req->approval_status === 'approved')
                    <span class="badge bg-label-success">Disetujui</span>
                  @else
                    <span class="badge bg-label-danger">Ditolak</span>
                  @endif
                </td>
                <td>{{ $req->requestedBureau->name ?? '-' }}</td>
                <td>{{ $req->created_at ? $req->created_at->format('d M Y, H:i') : '-' }}</td>
                @if($isApprover)
                  <td class="text-center">
                    @if($req->approval_status === 'pending')
                      <div class="d-flex justify-content-center gap-1">
                        <button class="btn btn-xs btn-success" wire:click="openApproveModal('work_plan', {{ $req->id }})">
                          <i class="bx bx-check me-0.5"></i> Setuju
                        </button>
                        <button class="btn btn-xs btn-danger" wire:click="rejectRequest('work_plan', {{ $req->id }})" wire:confirm="Yakin ingin menolak program kerja ini?">
                          <i class="bx bx-x me-0.5"></i> Tolak
                        </button>
                      </div>
                    @else
                      <span class="text-muted small">Selesai diproses</span>
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
                <td class="text-wrap" style="max-width: 250px;">{{ $req->description ?: '-' }}</td>
                <td>
                  @if($req->approval_status === 'pending')
                    <span class="badge bg-label-warning">Menunggu Persetujuan</span>
                  @elseif($req->approval_status === 'approved')
                    <span class="badge bg-label-success">Disetujui</span>
                  @else
                    <span class="badge bg-label-danger">Ditolak</span>
                  @endif
                </td>
                <td>{{ $req->requestedBureau->name ?? '-' }}</td>
                <td>{{ $req->created_at ? $req->created_at->format('d M Y, H:i') : '-' }}</td>
                @if($isApprover)
                  <td class="text-center">
                    @if($req->approval_status === 'pending')
                      <div class="d-flex justify-content-center gap-1">
                        <button class="btn btn-xs btn-success" wire:click="openApproveModal('activity', {{ $req->id }})">
                          <i class="bx bx-check me-0.5"></i> Setuju
                        </button>
                        <button class="btn btn-xs btn-danger" wire:click="rejectRequest('activity', {{ $req->id }})" wire:confirm="Yakin ingin menolak kegiatan ini?">
                          <i class="bx bx-x me-0.5"></i> Tolak
                        </button>
                      </div>
                    @else
                      <span class="text-muted small">Selesai diproses</span>
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
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); z-index: 1080;">
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
                <label class="form-label fw-semibold">Jenis Usulan</label>
                <div class="d-flex gap-3 mt-1">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" value="work_plan" id="typeWorkPlan" wire:model.live="requestType">
                    <label class="form-check-label" for="typeWorkPlan">Program Kerja Baru</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" value="activity" id="typeActivity" wire:model.live="requestType">
                    <label class="form-check-label" for="typeActivity">Kegiatan Baru</label>
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
                  <select id="actWorkPlanId" class="form-select @error('actWorkPlanId') is-invalid @enderror" wire:model.defer="actWorkPlanId">
                    <option value="">-- Pilih Program Kerja --</option>
                    @foreach($approvedWorkPlans as $wpOpt)
                      <option value="{{ $wpOpt->id }}">{{ $wpOpt->code }} — {{ $wpOpt->title }}</option>
                    @endforeach
                  </select>
                  @error('actWorkPlanId')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
                <div class="mb-3">
                  <label for="actTitle" class="form-label fw-semibold">Nama Kegiatan <span class="text-danger">*</span></label>
                  <input type="text" id="actTitle" class="form-control @error('actTitle') is-invalid @enderror" wire:model.defer="actTitle" placeholder="Misal: Sertifikasi Keahlian IT">
                  @error('actTitle')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
                <div class="mb-3">
                  <label for="actDescription" class="form-label fw-semibold">Deskripsi / Tujuan Kegiatan</label>
                  <textarea id="actDescription" class="form-control" wire:model.defer="actDescription" rows="3" placeholder="Sebutkan tujuan detail kegiatan..."></textarea>
                </div>
              @endif
            </div>
            <div class="modal-footer border-top">
              <button type="button" class="btn btn-outline-secondary" wire:click="closeRequestModal">Batal</button>
              <button type="submit" class="btn btn-primary">Kirim Usulan</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endif

  {{-- Interactive Code Assignment Modal (Approval) --}}
  @if($isApproveModalOpen)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); z-index: 1080;">
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
              <button type="button" class="btn btn-outline-secondary" wire:click="closeApproveModal">Batal</button>
              <button type="submit" class="btn btn-success">Setujui & Simpan</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endif
</div>
