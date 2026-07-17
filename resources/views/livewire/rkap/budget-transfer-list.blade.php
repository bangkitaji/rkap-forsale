<div class="container-xxl flex-grow-1 container-p-y">
  <div class="py-3 mb-4">
    <div class="d-flex justify-content-between align-items-center">
      <h4 class="mb-0">
        <span class="text-muted fw-light">RKAP /</span> {{ __('Transfer Budget') }}
      </h4>
      @can('rkap.transfer.create')
      <a href="{{ route('rkap-budget-transfers-create') }}" class="btn btn-primary">
        <i class="bx bx-plus me-1"></i> {{ __('Ajukan Transfer') }}
      </a>
      @endcan
    </div>
  </div>

  @if (session()->has('message'))
  <div class="alert alert-success alert-dismissible" role="alert">
    {{ session('message') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
  @endif

  @if (session()->has('error'))
  <div class="alert alert-danger alert-dismissible" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
  @endif

  {{-- Tab Navigation --}}
  <div class="row mb-4">
    <div class="col-12">
      <div class="nav-align-top">
        <ul class="nav nav-pills mb-3" role="tablist">
          <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'incoming' ? 'active' : '' }}" role="tab"
              wire:click="selectTab('incoming')">
              <i class="bx bx-down-arrow-alt me-1"></i> {{ __('Transfer Masuk') }}
            </button>
          </li>
          <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'outgoing' ? 'active' : '' }}" role="tab"
              wire:click="selectTab('outgoing')">
              <i class="bx bx-up-arrow-alt me-1"></i> {{ __('Transfer Keluar') }}
            </button>
          </li>
        </ul>
      </div>
    </div>
  </div>

  {{-- Filters --}}
  <div class="card mb-4">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">{{ __('Cari') }}</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="bx bx-search"></i></span>
            <input type="text" class="form-control" placeholder="{{ __('Cari catatan, biro asal/tujuan...') }}"
              wire:model.live.debounce.300ms="search">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ __('Periode RKAP') }}</label>
          <select class="form-select" wire:model.live="filterPeriod">
            <option value="">{{ __('Semua Periode') }}</option>
            @foreach($periods as $p)
            <option value="{{ $p->id }}">{{ $p->title }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ __('Status') }}</label>
          <select class="form-select" wire:model.live="filterStatus">
            <option value="">{{ __('Semua Status') }}</option>
            <option value="pending">{{ __('Menunggu Approval') }}</option>
            <option value="approved">{{ __('Disetujui') }}</option>
            <option value="rejected">{{ __('Ditolak') }}</option>
            <option value="cancelled">{{ __('Dibatalkan') }}</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  {{-- Table List --}}
  <div class="card">
    <div class="table-responsive text-nowrap">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>{{ __('No') }}</th>
            <th>{{ __('Periode') }}</th>
            <th>{{ $activeTab === 'incoming' ? __('Biro Asal') : __('Biro Tujuan') }}</th>
            <th>{{ __('Total Anggaran') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Tanggal Pengajuan') }}</th>
            <th class="text-center">{{ __('Aksi') }}</th>
          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          @forelse ($transfers as $index => $t)
          @php
          $statusEnum = \App\Enums\BudgetTransferStatus::tryFrom($t->status);
          $statusLabel = $statusEnum ? $statusEnum->label() : $t->status;
          $statusColor = $statusEnum ? $statusEnum->color() : 'secondary';
          @endphp
          <tr>
            <td>{{ $transfers->firstItem() + $index }}</td>
            <td><strong>{{ $t->period->title }}</strong></td>
            <td>
              @if($activeTab === 'incoming')
              <span class="badge bg-label-info">{{ $t->sourceBureau->code }}</span> {{ $t->sourceBureau->name }}
              @else
              <span class="badge bg-label-primary">{{ $t->targetBureau->code }}</span> {{ $t->targetBureau->name }}
              @endif
            </td>
            <td><strong class="text-primary">Rp {{ number_format($t->total_amount, 0, ',', '.') }}</strong></td>
            <td><span class="badge bg-label-{{ $statusColor }}">{{ $statusLabel }}</span></td>
            <td>{{ $t->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</td>
            <td class="text-center">
              @if($activeTab === 'incoming' && $t->isPending() && auth()->user()->hasPermissionTo('rkap.transfer.review'))
              <a href="{{ route('rkap-budget-transfers-review', $t->id) }}" class="btn btn-xs btn-primary">
                <i class="bx bx-edit-alt me-1"></i> {{ __('Review') }}
              </a>
              @else
              <a href="{{ route('rkap-budget-transfers-review', $t->id) }}" class="btn btn-xs btn-outline-secondary">
                <i class="bx bx-show me-1"></i> {{ __('Detail') }}
              </a>
              @endif

              @if($activeTab === 'outgoing' && $t->isPending())
              <button type="button" class="btn btn-xs btn-danger ms-1"
                wire:click="cancelTransfer({{ $t->id }})"
                wire:confirm="{{ __('Apakah Anda yakin ingin membatalkan pengajuan transfer ini?') }}">
                <i class="bx bx-x me-1"></i> {{ __('Batalkan') }}
              </button>
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center py-4 text-muted">
              <i class="bx bx-transfer fs-1 mb-2"></i>
              <p class="mb-0">{{ __('Tidak ada data transfer budget.') }}</p>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($transfers->hasPages())
    <div class="card-footer py-3">
      {{ $transfers->links() }}
    </div>
    @endif
  </div>
</div>
