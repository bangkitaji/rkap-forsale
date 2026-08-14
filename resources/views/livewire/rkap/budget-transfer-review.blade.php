<div class="container-xxl flex-grow-1 container-p-y">
  <div class="py-3 mb-4">
    <div class="d-flex align-items-center">
      <h4 class="mb-0">
        <span class="text-muted fw-light">RKAP / <a href="{{ route('rkap-budget-transfers') }}">{{ __('Transfer Budget') }}</a> /</span> {{ __('Detail & Review') }}
      </h4>
    </div>
  </div>

  @if (session()->has('error'))
  <div class="alert alert-danger alert-dismissible" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
  @endif

  @if (session()->has('message'))
  <div class="alert alert-success alert-dismissible" role="alert">
    {{ session('message') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
  @endif

  @php
  $statusEnum = \App\Enums\BudgetTransferStatus::tryFrom($transfer->status);
  $statusLabel = $statusEnum ? $statusEnum->label() : $transfer->status;
  $statusColor = $statusEnum ? $statusEnum->color() : 'secondary';
  $isCross = $transfer->isCrossDepartment();
  @endphp

  <div class="row">
    {{-- Left Sidebar: Info Transfer & Action Box --}}
    <div class="col-lg-4 col-md-5">
      <div class="card mb-4 shadow-sm">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0 fs-6 fw-bold"><i class="bx bx-info-circle me-1 text-primary"></i>{{ __('Info Transfer') }}</h5>
          <span class="badge bg-label-{{ $statusColor }}">{{ $statusLabel }}</span>
        </div>
        <div class="card-body pt-3">
          <div class="d-flex flex-column gap-3 mb-3">
            <div>
              <span class="text-muted small d-block">{{ __('Tipe Transfer') }}</span>
              @if($isCross)
              <span class="badge bg-label-warning"><i class="bx bx-git-branch me-1"></i>{{ __('Antar Departemen (Satu Direktorat)') }}</span>
              @else
              <span class="badge bg-label-info"><i class="bx bx-buildings me-1"></i>{{ __('Satu Departemen') }}</span>
              @endif
            </div>

            <div>
              <span class="text-muted small d-block">{{ __('Periode RKAP') }}</span>
              <strong class="text-dark">{{ $transfer->period->title }}</strong>
            </div>

            <div>
              <span class="text-muted small d-block">{{ __('Biro Asal (Pengusul)') }}</span>
              <strong class="text-dark"><span class="badge bg-label-info me-1">{{ $transfer->sourceBureau->code }}</span> {{ $transfer->sourceBureau->name }}</strong>
              @if($transfer->sourceBureau->department)
              <small class="text-muted d-block ms-1">{{ $transfer->sourceBureau->department->name }}</small>
              @endif
            </div>

            <div>
              <span class="text-muted small d-block">{{ __('Biro Tujuan (Penerima)') }}</span>
              <strong class="text-dark"><span class="badge bg-label-primary me-1">{{ $transfer->targetBureau->code }}</span> {{ $transfer->targetBureau->name }}</strong>
              @if($transfer->targetBureau->department)
              <small class="text-muted d-block ms-1">{{ $transfer->targetBureau->department->name }}</small>
              @endif
            </div>

            <div>
              <span class="text-muted small d-block">{{ __('Total Nominal Transfer') }}</span>
              <strong class="text-primary fs-5">Rp {{ number_format($transfer->total_amount, 0, ',', '.') }}</strong>
            </div>

            <div>
              <span class="text-muted small d-block">{{ __('Diajukan Oleh') }}</span>
              <strong class="text-dark">{{ $transfer->requester->name }}</strong>
              <small class="text-muted d-block">{{ $transfer->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</small>
            </div>

            @if($transfer->notes)
            <div>
              <span class="text-muted small d-block">{{ __('Catatan Pengirim') }}</span>
              <p class="mb-0 text-dark rkap-preline p-2 bg-lighter rounded small">{{ $transfer->notes }}</p>
            </div>
            @endif
          </div>

          {{-- Approval / Reject Actions Box --}}
          @if ($this->canReview())
          <div class="border-top pt-3">
            <div class="alert alert-primary py-2 px-3 mb-3 fs-7">
              <i class="bx bx-shield-quarter me-1"></i>
              @if($transfer->status === \App\Enums\BudgetTransferStatus::PendingSourceDept->value)
                {{ __('Anda bertindak sebagai') }} <strong>{{ __('Kepala Departemen Pengusul') }}</strong> ({{ $transfer->sourceBureau->department->name ?? '' }}). {{ __('Menyetujui usulan ini akan meneruskan ke Kepala Departemen Penerima.') }}
              @elseif($transfer->status === \App\Enums\BudgetTransferStatus::PendingTargetDept->value)
                {{ __('Anda bertindak sebagai') }} <strong>{{ __('Kepala Departemen Penerima') }}</strong> ({{ $transfer->targetBureau->department->name ?? '' }}). {{ __('Menyetujui usulan ini akan meneruskan ke Kepala Biro Penerima.') }}
              @else
                {{ __('Anda bertindak sebagai') }} <strong>{{ __('Kepala Biro Penerima') }}</strong> ({{ $transfer->targetBureau->name ?? '' }}). {{ __('Menyetujui transfer ini akan memindahkan anggaran secara definitif ke Biro Anda.') }}
              @endif
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold small">{{ __('Catatan Review / Approval') }}</label>
              <textarea class="form-control form-control-sm" rows="3" placeholder="{{ __('Tulis catatan atau alasan di sini...') }}" wire:model="reviewNotes"></textarea>
            </div>

            <div class="d-grid gap-2">
              <button type="button" class="btn btn-success" wire:click="approve"
                wire:confirm="{{ __('Apakah Anda yakin ingin menyetujui transfer budget ini pada tahap Anda?') }}">
                <i class="bx bx-check me-1"></i>
                @if($transfer->status === \App\Enums\BudgetTransferStatus::PendingSourceDept->value)
                  {{ __('Setujui (Lanjut ke Kadep Penerima)') }}
                @elseif($transfer->status === \App\Enums\BudgetTransferStatus::PendingTargetDept->value)
                  {{ __('Setujui (Lanjut ke Kabiro Penerima)') }}
                @else
                  {{ __('Terima & Setujui Transfer') }}
                @endif
              </button>
              <button type="button" class="btn btn-danger" wire:click="reject"
                wire:confirm="{{ __('Apakah Anda yakin ingin menolak transfer budget ini?') }}">
                <i class="bx bx-x me-1"></i> {{ __('Tolak Transfer') }}
              </button>
            </div>
          </div>
          @endif

          <div class="d-grid mt-3">
            <a href="{{ route('rkap-budget-transfers') }}" class="btn btn-outline-secondary">{{ __('Kembali ke Daftar') }}</a>
          </div>
        </div>
      </div>

      {{-- Approval Workflow Tracker / Timeline Card --}}
      <div class="card mb-4 shadow-sm">
        <div class="card-header border-bottom py-3">
          <h5 class="card-title mb-0 fs-6 fw-bold"><i class="bx bx-git-commit me-1 text-primary"></i>{{ __('Alur & Riwayat Approval') }}</h5>
        </div>
        <div class="card-body pt-3">
          <ul class="timeline mb-0 pb-0">
            {{-- Stage 1: Pengusulan --}}
            <li class="timeline-item timeline-item-transparent border-start ps-3 pb-3">
              <span class="timeline-point timeline-point-success"><i class="bx bx-check"></i></span>
              <div class="timeline-event">
                <div class="timeline-header mb-1">
                  <h6 class="mb-0 fs-7 fw-bold">{{ __('1. Pengajuan oleh Biro Pengusul') }}</h6>
                  <small class="text-muted">{{ $transfer->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</small>
                </div>
                <p class="mb-0 small text-muted">
                  {{ $transfer->requester->name }} (<span class="fw-semibold">{{ $transfer->sourceBureau->name }}</span>)
                </p>
              </div>
            </li>

            @if($isCross)
            {{-- Stage 2: Kadep Pengusul --}}
            @php
              $isStage2Done = !in_array($transfer->status, [\App\Enums\BudgetTransferStatus::PendingSourceDept->value, \App\Enums\BudgetTransferStatus::Cancelled->value]) && ($transfer->source_dept_approved_by || in_array($transfer->status, [\App\Enums\BudgetTransferStatus::PendingTargetDept->value, \App\Enums\BudgetTransferStatus::PendingTargetBureau->value, \App\Enums\BudgetTransferStatus::Approved->value]));
              $isStage2Active = ($transfer->status === \App\Enums\BudgetTransferStatus::PendingSourceDept->value);
              $isStage2Rejected = ($transfer->status === \App\Enums\BudgetTransferStatus::Rejected->value && !$transfer->source_dept_approved_by);
            @endphp
            <li class="timeline-item timeline-item-transparent border-start ps-3 pb-3">
              <span class="timeline-point {{ $isStage2Done ? 'timeline-point-success' : ($isStage2Active ? 'timeline-point-primary' : ($isStage2Rejected ? 'timeline-point-danger' : 'timeline-point-secondary')) }}">
                <i class="bx {{ $isStage2Done ? 'bx-check' : ($isStage2Active ? 'bx-loader-circle bx-spin' : ($isStage2Rejected ? 'bx-x' : 'bx-time')) }}"></i>
              </span>
              <div class="timeline-event">
                <div class="timeline-header mb-1">
                  <h6 class="mb-0 fs-7 fw-bold">{{ __('2. Approval Kadep Pengusul') }}</h6>
                  @if($transfer->source_dept_approved_at)
                  <small class="text-muted">{{ $transfer->source_dept_approved_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</small>
                  @endif
                </div>
                <p class="mb-0 small text-muted">
                  {{ $transfer->sourceBureau->department->name ?? 'Dept Pengusul' }}
                  @if($transfer->sourceDeptApprover)
                    — <strong class="text-dark">{{ $transfer->sourceDeptApprover->name }}</strong>
                  @endif
                </p>
                @if($transfer->source_dept_review_notes)
                <small class="text-muted d-block bg-lighter p-1 rounded mt-1"><em>"{{ $transfer->source_dept_review_notes }}"</em></small>
                @endif
              </div>
            </li>

            {{-- Stage 3: Kadep Penerima --}}
            @php
              $isStage3Done = !in_array($transfer->status, [\App\Enums\BudgetTransferStatus::PendingSourceDept->value, \App\Enums\BudgetTransferStatus::PendingTargetDept->value, \App\Enums\BudgetTransferStatus::Cancelled->value]) && ($transfer->target_dept_approved_by || in_array($transfer->status, [\App\Enums\BudgetTransferStatus::PendingTargetBureau->value, \App\Enums\BudgetTransferStatus::Approved->value]));
              $isStage3Active = ($transfer->status === \App\Enums\BudgetTransferStatus::PendingTargetDept->value);
              $isStage3Rejected = ($transfer->status === \App\Enums\BudgetTransferStatus::Rejected->value && $transfer->source_dept_approved_by && !$transfer->target_dept_approved_by);
            @endphp
            <li class="timeline-item timeline-item-transparent border-start ps-3 pb-3">
              <span class="timeline-point {{ $isStage3Done ? 'timeline-point-success' : ($isStage3Active ? 'timeline-point-primary' : ($isStage3Rejected ? 'timeline-point-danger' : 'timeline-point-secondary')) }}">
                <i class="bx {{ $isStage3Done ? 'bx-check' : ($isStage3Active ? 'bx-loader-circle bx-spin' : ($isStage3Rejected ? 'bx-x' : 'bx-time')) }}"></i>
              </span>
              <div class="timeline-event">
                <div class="timeline-header mb-1">
                  <h6 class="mb-0 fs-7 fw-bold">{{ __('3. Approval Kadep Penerima') }}</h6>
                  @if($transfer->target_dept_approved_at)
                  <small class="text-muted">{{ $transfer->target_dept_approved_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</small>
                  @endif
                </div>
                <p class="mb-0 small text-muted">
                  {{ $transfer->targetBureau->department->name ?? 'Dept Penerima' }}
                  @if($transfer->targetDeptApprover)
                    — <strong class="text-dark">{{ $transfer->targetDeptApprover->name }}</strong>
                  @endif
                </p>
                @if($transfer->target_dept_review_notes)
                <small class="text-muted d-block bg-lighter p-1 rounded mt-1"><em>"{{ $transfer->target_dept_review_notes }}"</em></small>
                @endif
              </div>
            </li>

            {{-- Stage 4: Kabiro Penerima --}}
            @php
              $isStage4Done = ($transfer->status === \App\Enums\BudgetTransferStatus::Approved->value);
              $isStage4Active = ($transfer->status === \App\Enums\BudgetTransferStatus::PendingTargetBureau->value);
              $isStage4Rejected = ($transfer->status === \App\Enums\BudgetTransferStatus::Rejected->value && $transfer->target_dept_approved_by);
            @endphp
            <li class="timeline-item timeline-item-transparent ps-3 pb-0">
              <span class="timeline-point {{ $isStage4Done ? 'timeline-point-success' : ($isStage4Active ? 'timeline-point-primary' : ($isStage4Rejected ? 'timeline-point-danger' : 'timeline-point-secondary')) }}">
                <i class="bx {{ $isStage4Done ? 'bx-check' : ($isStage4Active ? 'bx-loader-circle bx-spin' : ($isStage4Rejected ? 'bx-x' : 'bx-time')) }}"></i>
              </span>
              <div class="timeline-event">
                <div class="timeline-header mb-1">
                  <h6 class="mb-0 fs-7 fw-bold">{{ __('4. Penerimaan & Approval Kabiro Penerima') }}</h6>
                  @if($transfer->reviewed_at)
                  <small class="text-muted">{{ $transfer->reviewed_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</small>
                  @endif
                </div>
                <p class="mb-0 small text-muted">
                  {{ $transfer->targetBureau->name ?? 'Biro Penerima' }}
                  @if($transfer->reviewer)
                    — <strong class="text-dark">{{ $transfer->reviewer->name }}</strong>
                  @endif
                </p>
                @if($transfer->review_notes)
                <small class="text-muted d-block bg-lighter p-1 rounded mt-1"><em>"{{ $transfer->review_notes }}"</em></small>
                @endif
              </div>
            </li>
            @else
            {{-- Intra-department single stage --}}
            @php
              $isIntraDone = ($transfer->status === \App\Enums\BudgetTransferStatus::Approved->value);
              $isIntraActive = ($transfer->status === \App\Enums\BudgetTransferStatus::Pending->value);
              $isIntraRejected = ($transfer->status === \App\Enums\BudgetTransferStatus::Rejected->value);
            @endphp
            <li class="timeline-item timeline-item-transparent ps-3 pb-0">
              <span class="timeline-point {{ $isIntraDone ? 'timeline-point-success' : ($isIntraActive ? 'timeline-point-primary' : ($isIntraRejected ? 'timeline-point-danger' : 'timeline-point-secondary')) }}">
                <i class="bx {{ $isIntraDone ? 'bx-check' : ($isIntraActive ? 'bx-loader-circle bx-spin' : ($isIntraRejected ? 'bx-x' : 'bx-time')) }}"></i>
              </span>
              <div class="timeline-event">
                <div class="timeline-header mb-1">
                  <h6 class="mb-0 fs-7 fw-bold">{{ __('2. Approval & Penerimaan Kepala Biro') }}</h6>
                  @if($transfer->reviewed_at)
                  <small class="text-muted">{{ $transfer->reviewed_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</small>
                  @endif
                </div>
                <p class="mb-0 small text-muted">
                  {{ $transfer->targetBureau->name }}
                  @if($transfer->reviewer)
                    — <strong class="text-dark">{{ $transfer->reviewer->name }}</strong>
                  @endif
                </p>
                @if($transfer->review_notes)
                <small class="text-muted d-block bg-lighter p-1 rounded mt-1"><em>"{{ $transfer->review_notes }}"</em></small>
                @endif
              </div>
            </li>
            @endif
          </ul>
        </div>
      </div>
    </div>

    {{-- Right Column: Items List details --}}
    <div class="col-lg-8 col-md-7">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fs-6 fw-bold text-dark"><i class="bx bx-list-ul me-1 text-primary"></i>{{ __('Daftar Kegiatan yang Ditransfer') }} ({{ $transfer->items->count() }})</h5>
      </div>

      @foreach($transfer->items as $idx => $item)
      @php
      $wp = $item->workPlan;
      $bi = $item->budgetItem;
      $transferredAmount = $item->amount_transferred ?: ($bi ? $bi->total_price : $wp->budgetItems->sum('total_price'));
      @endphp
      <div class="card mb-4 border-start border-primary border-3 shadow-sm" wire:key="item-card-{{ $item->id }}">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center py-3">
          <div>
            <span class="badge bg-label-primary mb-1">Item Transfer {{ $idx + 1 }}</span>
            @if($wp && $wp->workPlan)
            <div class="small text-muted mb-1">
              <i class="bx bx-briefcase me-1"></i><strong>{{ __('Program Kerja:') }}</strong> {{ $wp->workPlan->code }} — {{ $wp->workPlan->title }}
            </div>
            @endif
            <h5 class="card-title mb-0 fs-6 fw-bold">
              <i class="bx bx-task me-1 text-primary"></i>
              @if($bi)
                {{ $bi->account_code }} — {{ $bi->description }}
              @elseif($wp)
                {{ $wp->program_code }} — {{ $wp->program_name }}
              @endif
            </h5>
          </div>
          <div class="text-end">
            <span class="small text-muted d-block">{{ __('Nominal Ditransfer') }}</span>
            <strong class="text-primary fs-5">Rp {{ number_format($transferredAmount, 0, ',', '.') }}</strong>
          </div>
        </div>
        <div class="card-body pt-3">
          @if($bi)
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <span class="text-muted small d-block">{{ __('Program Kerja Induk') }}</span>
              <strong class="text-dark">{{ $wp->program_code }} — {{ $wp->program_name }}</strong>
            </div>
            <div class="col-md-4">
              <span class="text-muted small d-block">{{ __('Budget Saat Ini (Biro Asal)') }}</span>
              <strong class="text-dark">Rp {{ number_format($bi->total_price, 0, ',', '.') }}</strong>
            </div>
            <div class="col-md-4">
              <span class="text-muted small d-block">{{ __('Estimasi Sisa Budget (Biro Asal)') }}</span>
              <strong class="text-success">Rp {{ number_format(max(0, $bi->total_price - $transferredAmount), 0, ',', '.') }}</strong>
            </div>
          </div>
          @elseif($wp)
          @if($wp->description)
          <div class="mb-3">
            <span class="text-muted small d-block">{{ __('Deskripsi / Tujuan') }}</span>
            <p class="mb-0 text-dark">{{ $wp->description }}</p>
          </div>
          @endif
          @endif

          @if ($transfer->status === \App\Enums\BudgetTransferStatus::Approved->value && auth()->user()->isAdmin())
          @if (($bi && (float) $bi->total_price == 0) || ($wp && (float) $wp->total_budget == 0))
          <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center bg-lighter p-2 rounded">
            <div class="small text-muted me-2">
              <i class="bx bx-info-circle text-warning me-1"></i>{{ __('Sisa budget di Biro Asal saat ini Rp 0. Administrator dapat membersihkan record ini dari Biro Asal.') }}
            </div>
            <button type="button" class="btn btn-xs btn-outline-danger text-nowrap"
              wire:click="deleteZeroBudgetTransferredItem({{ $item->id }})"
              wire:key="btn-delete-zero-item-{{ $item->id }}"
              wire:confirm="{{ __('Yakin menghapus record kegiatan ber-budget Rp 0 ini dari Biro Asal? Data di Biro Tujuan tidak akan terpengaruh.') }}">
              <i class="bx bx-trash me-1"></i>{{ __('Hapus Record Budget Rp 0') }}
            </button>
          </div>
          @endif
          @endif
        </div>
      </div>
      @endforeach
    </div>
  </div>
</div>
