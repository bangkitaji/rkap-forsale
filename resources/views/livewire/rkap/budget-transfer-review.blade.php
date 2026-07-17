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
              <span class="text-muted small d-block">{{ __('Total Anggaran') }}</span>
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

    {{-- Program Kerja List details --}}
    <div class="col-md-8">
      @foreach($transfer->items as $idx => $item)
      @php
      $wp = $item->workPlan;
      $wpTotal = $wp->budgetItems->sum('total_price');
      @endphp
      <div class="card mb-4 border-start border-primary border-3" wire:key="item-card-{{ $item->id }}">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center">
          <div>
            <span class="badge bg-label-primary mb-1">Item Transfer {{ $idx + 1 }}</span>
            @if($wp->workPlan)
            <div class="small text-muted mb-1">
              <i class="bx bx-briefcase me-1"></i><strong>{{ __('Program Kerja:') }}</strong> {{ $wp->workPlan->code }} — {{ $wp->workPlan->title }}
            </div>
            @endif
            <h5 class="card-title mb-0"><i class="bx bx-task me-1 text-primary"></i>{{ __('Kegiatan:') }} {{ $wp->program_code }} — {{ $wp->program_name }}</h5>
          </div>
          <strong class="text-primary fs-5">Rp {{ number_format($wpTotal, 0, ',', '.') }}</strong>
        </div>
        <div class="card-body pt-4">
          @if($wp->description)
          <div class="mb-3">
            <span class="text-muted small d-block">{{ __('Deskripsi / Tujuan') }}</span>
            <p class="mb-0 text-dark">{{ $wp->description }}</p>
          </div>
          @endif

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <span class="text-muted small d-block">{{ __('Target Output') }}</span>
              <strong class="text-dark">{{ $wp->output_target ?: '-' }}</strong>
            </div>
            <div class="col-md-6">
              <span class="text-muted small d-block">{{ __('Volume & Satuan') }}</span>
              <strong class="text-dark">{{ $wp->quantity }} {{ $wp->unit ?: '-' }}</strong>
            </div>
          </div>

          {{-- Budget Items Detail Table --}}
          <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bx bx-list-check me-1 text-primary"></i>{{ __('Rincian Anggaran') }}</h6>
          <div class="table-responsive text-nowrap">
            <table class="table table-bordered table-sm">
              <thead class="table-light">
                <tr>
                  <th>{{ __('COA / Detail Belanja') }}</th>
                  <th class="text-center">{{ __('Vol') }}</th>
                  <th>{{ __('Satuan') }}</th>
                  <th class="text-end">{{ __('Harga Satuan') }}</th>
                  <th class="text-end">{{ __('Total') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($wp->budgetItems as $bi)
                <tr>
                  <td>
                    <span class="badge bg-label-info">{{ $bi->account_code }}</span>
                    <strong class="text-dark d-block mt-1">{{ $bi->description }}</strong>
                    @if($bi->remarks)
                    <small class="text-muted">{{ $bi->remarks }}</small>
                    @endif
                  </td>
                  <td class="text-center">{{ $bi->quantity }}</td>
                  <td>{{ $bi->unit }}</td>
                  <td class="text-end">Rp {{ number_format($bi->unit_price, 0, ',', '.') }}</td>
                  <td class="text-end"><strong class="text-primary">Rp {{ number_format($bi->total_price, 0, ',', '.') }}</strong></td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</div>
