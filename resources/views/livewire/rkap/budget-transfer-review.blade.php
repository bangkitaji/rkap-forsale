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

  <div class="row">
    {{-- Header Details --}}
    <div class="col-md-4">
      <div class="card mb-4">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">{{ __('Info Transfer') }}</h5>
          @php
          $statusEnum = \App\Enums\BudgetTransferStatus::tryFrom($transfer->status);
          $statusLabel = $statusEnum ? $statusEnum->label() : $transfer->status;
          $statusColor = $statusEnum ? $statusEnum->color() : 'secondary';
          @endphp
          <span class="badge bg-label-{{ $statusColor }}">{{ $statusLabel }}</span>
        </div>
        <div class="card-body pt-4">
          <div class="d-flex flex-column gap-3 mb-4">
            <div>
              <span class="text-muted small d-block">{{ __('Periode RKAP') }}</span>
              <strong class="text-dark">{{ $transfer->period->title }}</strong>
            </div>

            <div>
              <span class="text-muted small d-block">{{ __('Biro Asal') }}</span>
              <strong class="text-dark"><span class="badge bg-label-info me-1">{{ $transfer->sourceBureau->code }}</span> {{ $transfer->sourceBureau->name }}</strong>
            </div>

            <div>
              <span class="text-muted small d-block">{{ __('Biro Tujuan') }}</span>
              <strong class="text-dark"><span class="badge bg-label-primary me-1">{{ $transfer->targetBureau->code }}</span> {{ $transfer->targetBureau->name }}</strong>
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
              <p class="mb-0 text-dark rkap-preline">{{ $transfer->notes }}</p>
            </div>
            @endif

            @if($transfer->reviewed_by)
            <div class="border-top pt-3">
              <span class="text-muted small d-block">{{ __('Direview Oleh') }}</span>
              <strong class="text-dark">{{ $transfer->reviewer->name }}</strong>
              @if($transfer->reviewed_at)
              <small class="text-muted d-block">{{ $transfer->reviewed_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</small>
              @endif
            </div>

            @if($transfer->review_notes)
            <div>
              <span class="text-muted small d-block">{{ __('Catatan Review') }}</span>
              <p class="mb-0 text-dark rkap-preline">{{ $transfer->review_notes }}</p>
            </div>
            @endif
            @endif
          </div>

          {{-- Approval / Reject Actions --}}
          @if ($this->canReview())
          <div class="border-top pt-3">
            <div class="mb-3">
              <label class="form-label fw-semibold">{{ __('Catatan Review') }}</label>
              <textarea class="form-control" rows="3" placeholder="{{ __('Tulis catatan atau alasan di sini...') }}" wire:model="reviewNotes"></textarea>
            </div>

            <div class="d-grid gap-2">
              <button type="button" class="btn btn-success" wire:click="approve"
                wire:confirm="{{ __('Apakah Anda yakin ingin menyetujui transfer budget ini ke Biro Anda?') }}">
                <i class="bx bx-check me-1"></i> {{ __('Setujui Transfer') }}
              </button>
              <button type="button" class="btn btn-danger" wire:click="reject"
                wire:confirm="{{ __('Apakah Anda yakin ingin menolak transfer budget ini?') }}">
                <i class="bx bx-x me-1"></i> {{ __('Tolak Transfer') }}
              </button>
            </div>
          </div>
          @endif

          <div class="d-grid mt-3">
            <a href="{{ route('rkap-budget-transfers') }}" class="btn btn-outline-secondary">{{ __('Kembali') }}</a>
          </div>
        </div>
      </div>
    </div>

    {{-- Items List details --}}
    <div class="col-md-8">
      @foreach($transfer->items as $idx => $item)
      @php
      $wp = $item->workPlan;
      $bi = $item->budgetItem;
      $transferredAmount = $item->amount_transferred ?: ($bi ? $bi->total_price : $wp->budgetItems->sum('total_price'));
      @endphp
      <div class="card mb-4 border-start border-primary border-3" wire:key="item-card-{{ $item->id }}">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center">
          <div>
            <span class="badge bg-label-primary mb-1">Item Transfer {{ $idx + 1 }}</span>
            @if($wp && $wp->workPlan)
            <div class="small text-muted mb-1">
              <i class="bx bx-briefcase me-1"></i><strong>{{ __('Program Kerja:') }}</strong> {{ $wp->workPlan->code }} — {{ $wp->workPlan->title }}
            </div>
            @endif
            <h5 class="card-title mb-0">
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
        <div class="card-body pt-4">
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
