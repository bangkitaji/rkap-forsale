<div x-data="{
    showToast: @js(session()->has('message') || session()->has('error')),
    toastMessage: @js(session('message') ?: session('error') ?: ''),
    toastType: @js(session()->has('error') ? 'danger' : 'success'),
    dragOver: false,
}" x-init="if (showToast) { setTimeout(() => showToast = false, 6000); }">

  {{-- Breadcrumb --}}
  <div class="d-flex justify-content-between align-items-center py-3 mb-4">
    <h4 class="mb-0">
      <span class="text-muted fw-light">RKAP / {{ __('Pengajuan') }} /</span>
      {{ __('Upload Massal') }}
    </h4>
    <div class="d-flex gap-2">
      <a href="{{ route('rkap-submissions') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back me-1"></i> {{ __('Kembali') }}
      </a>
    </div>
  </div>

  {{-- Toast --}}
  <div class="toast-container position-fixed top-0 end-0 p-3 rkap-z-1090">
    <div x-show="showToast" x-cloak x-transition class="bs-toast toast show text-white"
      :class="'bg-' + toastType" role="alert">
      <div class="toast-header text-white" :class="'bg-' + toastType">
        <i class="bx me-2 text-white" :class="toastType === 'success' ? 'bx-check-circle' : 'bx-x-circle'"></i>
        <div class="me-auto fw-semibold" x-text="toastType === 'success' ? '{{ __('Berhasil') }}' : 'Error'"></div>
        <button type="button" class="btn-close btn-close-white" @click="showToast = false"></button>
      </div>
      <div class="toast-body" x-text="toastMessage"></div>
    </div>
  </div>

  {{-- Period Info Card --}}
  <div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex align-items-center gap-3">
        <div class="avatar avatar-md flex-shrink-0">
          <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-calendar bx-sm"></i></span>
        </div>
        <div>
          <h6 class="mb-0">{{ $period->title ?? '-' }}</h6>
          <small class="text-muted">{{ __('Periode') }}: {{ $period->year ?? '-' }} &bull;
            <span class="badge bg-label-success">{{ ucfirst($period->status ?? '') }}</span>
          </small>
        </div>
      </div>
    </div>
  </div>

  {{-- Upload Area --}}
  @if (!$imported)
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="bx bx-upload me-2 text-primary"></i>{{ __('Upload File Excel') }}</h5>
      <a href="{{ route('rkap-submission-template-download') }}" class="btn btn-sm btn-outline-primary">
        <i class="bx bx-download me-1"></i> {{ __('Download Template') }}
      </a>
    </div>
    <div class="card-body">
      <form wire:submit.prevent="uploadAndParse">
        {{-- Drag & Drop Zone --}}
        <div class="border rounded-3 p-5 text-center mb-3 position-relative"
          :class="{ 'border-primary bg-primary bg-opacity-10': dragOver, 'border-dashed': !dragOver }"
          @dragover.prevent="dragOver = true"
          @dragleave.prevent="dragOver = false"
          @drop.prevent="dragOver = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'));"
          style="cursor: pointer; border-style: dashed;"
          @click="$refs.fileInput.click()">

          <input type="file" x-ref="fileInput" wire:model="file"
            accept=".xlsx,.xls" class="d-none" id="bulk-upload-file">

          <div x-show="!dragOver">
            <i class="bx bx-cloud-upload text-primary" style="font-size: 3rem;"></i>
            <p class="mb-1 mt-2 fw-semibold">{{ __('Seret & lepas file Excel di sini') }}</p>
            <p class="text-muted small mb-0">{{ __('atau klik untuk memilih file (.xlsx, .xls, maks 2MB)') }}</p>
          </div>
          <div x-show="dragOver" x-cloak>
            <i class="bx bx-target-lock text-primary" style="font-size: 3rem;"></i>
            <p class="mb-0 fw-semibold text-primary">{{ __('Lepaskan file di sini...') }}</p>
          </div>
        </div>

        {{-- File info --}}
        @if ($file)
        <div class="alert alert-info py-2 d-flex align-items-center gap-2 mb-3">
          <i class="bx bx-file"></i>
          <span>{{ $file->getClientOriginalName() }} ({{ number_format($file->getSize() / 1024, 1) }} KB)</span>
          <button type="button" wire:click="$set('file', null)" class="btn-close ms-auto" style="font-size: 0.6rem;"></button>
        </div>
        @endif

        @error('file')
        <div class="alert alert-danger py-2 mb-3">
          <i class="bx bx-error-circle me-1"></i> {{ $message }}
        </div>
        @enderror

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary" @if(!$file) disabled @endif wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="uploadAndParse">
              <i class="bx bx-analyse me-1"></i> {{ __('Proses & Validasi') }}
            </span>
            <span wire:loading wire:target="uploadAndParse">
              <span class="spinner-border spinner-border-sm me-1"></span> {{ __('Memproses...') }}
            </span>
          </button>

          @if ($parsed)
          <button type="button" wire:click="resetUpload" class="btn btn-outline-secondary">
            <i class="bx bx-refresh me-1"></i> {{ __('Upload Ulang') }}
          </button>
          @endif
        </div>
      </form>
    </div>
  </div>
  @endif

  {{-- Validation Errors --}}
  @if (!empty($importErrors))
  <div class="card mb-4 border-danger">
    <div class="card-header bg-danger bg-opacity-10 d-flex justify-content-between align-items-center">
      <h5 class="mb-0 text-danger">
        <i class="bx bx-error-circle me-2"></i>{{ __('Error Validasi') }} ({{ count($importErrors) }})
      </h5>
    </div>
    <div class="card-body">
      <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light sticky-top">
            <tr>
              <th style="width: 40px;">#</th>
              <th>{{ __('Pesan Error') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($importErrors as $idx => $error)
            <tr>
              <td class="text-muted">{{ $idx + 1 }}</td>
              <td><i class="bx bx-x text-danger me-1"></i>{{ $error }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
  @endif

  {{-- Preview Table --}}
  @if ($parsed && empty($importErrors))
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">
        <i class="bx bx-table me-2 text-success"></i>{{ __('Preview Data') }}
        <span class="badge bg-success ms-2">{{ count($parsedRows) }} {{ __('item') }}</span>
      </h5>
    </div>
    <div class="card-body p-0">
      @php $grouped = $this->groupedPreview; @endphp
      @foreach ($grouped as $groupKey => $group)
      <div class="border-bottom">
        {{-- Group Header --}}
        <div class="px-4 py-3 bg-lighter d-flex justify-content-between align-items-center">
          <div>
            <span class="badge bg-primary me-2">{{ $group['work_plan_code'] }}</span>
            <span class="fw-semibold">{{ $group['work_plan_name'] }}</span>
            <i class="bx bx-chevron-right mx-1 text-muted"></i>
            <span class="badge bg-info me-2">{{ $group['activity_code'] }}</span>
            <span>{{ $group['activity_name'] }}</span>
          </div>
          <div class="text-end">
            <small class="text-muted">{{ __('Subtotal') }}:</small>
            <span class="fw-bold text-primary">Rp {{ number_format($group['subtotal'], 0, ',', '.') }}</span>
          </div>
        </div>

        {{-- Items Table --}}
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Kode COA') }}</th>
                <th>{{ __('Nama COA') }}</th>
                <th>{{ __('Satuan') }}</th>
                <th class="text-end">{{ __('Volume') }}</th>
                <th class="text-end">{{ __('Harga Satuan') }}</th>
                <th class="text-end">{{ __('Total') }}</th>
                <th>{{ __('Ket.') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($group['items'] as $item)
              <tr>
                <td><code>{{ $item['coa_code'] }}</code></td>
                <td>{{ $item['coa_name'] }}</td>
                <td>
                  {{ $item['unit'] }}
                  @if (!empty($item['unit_2']))
                  × {{ $item['unit_2'] }}
                  @endif
                </td>
                <td class="text-end">
                  {{ $item['quantity'] }}
                  @if (!empty($item['unit_2']) && !empty($item['quantity_2']))
                  × {{ $item['quantity_2'] }}
                  @endif
                </td>
                <td class="text-end">Rp {{ number_format($item['unit_price'], 0, ',', '.') }}</td>
                <td class="text-end fw-semibold">Rp {{ number_format($item['total'], 0, ',', '.') }}</td>
                <td class="text-muted small">{{ $item['remarks'] }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endforeach
    </div>

    {{-- Grand Total & Save --}}
    <div class="card-footer d-flex justify-content-between align-items-center">
      <div>
        <span class="fs-5 fw-bold">
          {{ __('Grand Total') }}: Rp {{ number_format(collect($grouped)->sum('subtotal'), 0, ',', '.') }}
        </span>
        <div class="text-muted small">
          {{ count($grouped) }} {{ __('kegiatan') }} &bull; {{ count($parsedRows) }} {{ __('item anggaran') }}
        </div>
      </div>
      <div class="d-flex gap-2">
        <button wire:click="resetUpload" class="btn btn-outline-secondary">
          <i class="bx bx-x me-1"></i> {{ __('Batal') }}
        </button>
        <button wire:click="saveAsDraft" class="btn btn-success btn-lg" wire:loading.attr="disabled">
          <span wire:loading.remove wire:target="saveAsDraft">
            <i class="bx bx-save me-1"></i> {{ __('Simpan sebagai Draft') }}
          </span>
          <span wire:loading wire:target="saveAsDraft">
            <span class="spinner-border spinner-border-sm me-1"></span> {{ __('Menyimpan...') }}
          </span>
        </button>
      </div>
    </div>
  </div>
  @endif

  {{-- Success / Import Summary --}}
  @if ($imported && !empty($importSummary))
  <div class="card mb-4 border-success">
    <div class="card-body text-center py-5">
      <div class="mb-3">
        <i class="bx bx-check-circle text-success" style="font-size: 4rem;"></i>
      </div>
      <h4 class="text-success mb-3">{{ __('Upload Massal Berhasil!') }}</h4>
      <div class="row justify-content-center g-3 mb-4">
        <div class="col-auto">
          <div class="border rounded px-4 py-3 text-center">
            <div class="fs-3 fw-bold text-primary">{{ $importSummary['total_rows'] }}</div>
            <div class="text-muted small">{{ __('Item Anggaran') }}</div>
          </div>
        </div>
        <div class="col-auto">
          <div class="border rounded px-4 py-3 text-center">
            <div class="fs-3 fw-bold text-info">{{ $importSummary['total_groups'] }}</div>
            <div class="text-muted small">{{ __('Kegiatan') }}</div>
          </div>
        </div>
        <div class="col-auto">
          <div class="border rounded px-4 py-3 text-center">
            <div class="fs-3 fw-bold text-success">Rp {{ number_format($importSummary['total_budget'], 0, ',', '.') }}</div>
            <div class="text-muted small">{{ __('Total Anggaran') }}</div>
          </div>
        </div>
      </div>
      <div class="d-flex justify-content-center gap-3">
        <a href="{{ route('rkap-submissions-edit', ['id' => $importSummary['submission_id']]) }}"
          class="btn btn-primary btn-lg">
          <i class="bx bx-edit me-1"></i> {{ __('Edit & Lengkapi Draft') }}
        </a>
        <a href="{{ route('rkap-submissions') }}" class="btn btn-outline-secondary btn-lg">
          <i class="bx bx-list-ul me-1"></i> {{ __('Daftar Pengajuan') }}
        </a>
      </div>
    </div>
  </div>
  @endif

</div>
