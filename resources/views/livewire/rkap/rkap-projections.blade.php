<div x-data="{
    showToast: @js(session()->has('message') || session()->has('error')),
    toastMessage: @js(session('message') ?: session('error') ?: ''),
    toastType: @js(session()->has('error') ? 'danger' : 'success')
}" @projections-saved.window="
    toastMessage = 'Proyeksi RKAP berhasil disimpan.';
    toastType = 'success';
    showToast = true;
    setTimeout(() => showToast = false, 5000);
" x-init="if (showToast) { setTimeout(() => showToast = false, 5000); }">
    <style>
        .activity-section {
            background-color: #f8fafc;
            border-left: 3px solid #666cff;
        }
        .table-responsive {
            overflow: visible !important;
        }
        .form-control-projection {
            min-width: 140px;
        }
    </style>

    <div class="d-flex justify-content-between align-items-center py-3 mb-4">
        <h4 class="mb-0"><span class="text-muted fw-light">RKAP /</span> Input Proyeksi</h4>
        <div class="text-muted small">
            <i class="bx bx-calendar me-1"></i> Periode Perencanaan: <strong>{{ $activePeriodTitle ?? '-' }}</strong>
        </div>
    </div>

    {{-- Toast Notification --}}
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
                <i class="bx me-2 text-white" :class="toastType === 'success' ? 'bx-check-circle' : 'bx-x-circle'"></i>
                <div class="me-auto fw-semibold" x-text="toastType === 'success' ? 'Berhasil' : 'Error'"></div>
                <button type="button" class="btn-close btn-close-white" @click="showToast = false" aria-label="Close"></button>
            </div>
            <div class="toast-body" x-text="toastMessage"></div>
        </div>
    </div>

    {{-- Alert Info / Guide --}}
    <div class="alert alert-primary d-flex align-items-center gap-2 mb-4" role="alert">
        <span class="badge bg-primary rounded-pill"><i class="bx bx-info-circle text-white"></i></span>
        <div>
            Halaman ini digunakan untuk menginput <strong>Proyeksi Realisasi Akhir Tahun</strong> dari masing-masing item anggaran pada periode berjalan (<strong>{{ $activePeriodTitle ?? '-' }}</strong>). Proyeksi ini membantu evaluasi pemenuhan budget dan realisasi.
        </div>
    </div>

    {{-- Filter Selector Card --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Pilih Biro <span class="text-danger">*</span></label>
                    <div class="position-relative" x-data="{
                        open: false,
                        search: '',
                        selectedId: @entangle('bureauId'),
                        bureaus: @js($bureauOptions->map(fn($b) => [
                            'id' => $b->id,
                            'code' => $b->code,
                            'name' => $b->name,
                            'label' => $b->code . ' — ' . $b->name
                        ])->toArray()),
                        get selectedLabel() {
                            const found = this.bureaus.find(b => b.id == this.selectedId);
                            return found ? found.label : '-- Pilih Biro --';
                        },
                        get filteredBureaus() {
                            if (!this.search) return this.bureaus;
                            const q = this.search.toLowerCase();
                            return this.bureaus.filter(b => 
                                b.code.toLowerCase().includes(q) || 
                                b.name.toLowerCase().includes(q)
                            );
                        }
                    }" @click.outside="open = false">
                        
                        <button type="button" 
                                class="form-select text-start"
                                id="bureauSelectButton"
                                @click="open = !open"
                                @disabled(Auth::user()->isKepalaBiro())>
                            <span x-text="selectedLabel"></span>
                        </button>

                        <div x-show="open" 
                             class="dropdown-menu show w-100 p-2 shadow-sm border mt-1" 
                             style="display: none; position: absolute; z-index: 1000; max-height: 250px; overflow-y: auto;">
                            
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light"><i class="bx bx-search"></i></span>
                                <input type="text" 
                                       class="form-control" 
                                       placeholder="Cari kode atau nama biro..." 
                                       x-model="search" 
                                       @keydown.escape.prevent.stop="open = false"
                                       @click.stop>
                            </div>

                            <div class="dropdown-divider"></div>

                            <div class="list-group list-group-flush">
                                <template x-for="bureau in filteredBureaus" :key="bureau.id">
                                    <button type="button" 
                                            class="list-group-item list-group-item-action py-1 px-2 border-0 rounded text-start"
                                            :class="selectedId == bureau.id ? 'active text-white' : 'text-dark'"
                                            @click="
                                                selectedId = bureau.id;
                                                $wire.set('bureauId', bureau.id);
                                                open = false;
                                                search = '';
                                            ">
                                        <span x-text="bureau.label"></span>
                                    </button>
                                </template>
                                <div x-show="filteredBureaus.length === 0" class="text-muted small text-center py-2">
                                    Tidak ada biro yang cocok
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Target Periode Proyeksi</label>
                    <input type="text" class="form-control" value="{{ $activePeriodTitle ?? 'Tidak ada periode aktif' }}" disabled>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Input Form --}}
    @if(!$activePeriodId)
        <div class="card py-5 text-center text-muted">
            <div class="card-body">
                <i class="bx bx-calendar-exclamation bx-lg d-block mb-3 text-danger"></i>
                <h5 class="fw-semibold text-danger">Periode RKAP Tahun Ini Belum Difinalisasi</h5>
                <p class="small mb-0">Proyeksi hanya dapat diinput pada periode RKAP tahun berjalan ({{ date('Y') }}) yang memiliki status <strong>Finalized</strong>.</p>
            </div>
        </div>
    @elseif(!$bureauId)
        <div class="card py-5 text-center text-muted">
            <div class="card-body">
                <i class="bx bx-pointer bx-lg d-block mb-3 text-secondary"></i>
                <h5 class="fw-semibold">Silakan pilih Biro terlebih dahulu</h5>
                <p class="small mb-0">Pilih biro dari dropdown di atas untuk memuat item anggaran yang akan diproyeksikan.</p>
            </div>
        </div>
    @elseif(!$submission)
        <div class="card py-5 text-center text-muted">
            <div class="card-body">
                <i class="bx bx-info-circle bx-lg d-block mb-3 text-warning"></i>
                <h5 class="fw-semibold">Tidak ditemukan pengajuan RKAP yang disetujui</h5>
                <p class="small mb-0">Tidak ada pengajuan RKAP dengan status <strong>Disetujui</strong> untuk biro terpilih pada periode <strong>{{ $activePeriodTitle ?? '-' }}</strong>.</p>
            </div>
        </div>
    @else
            @foreach($submission->workPlans as $wp)
                <div class="card mb-4 border-start border-primary border-3">
                    <div class="card-header bg-lighter py-3 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bx bx-list-ul text-primary"></i>
                            <h6 class="mb-0 fw-bold text-primary">{{ $wp->program_code }} — {{ $wp->program_name }}</h6>
                        </div>
                    </div>

                    <div class="card-body p-3">
                        @foreach($wp->budgetItems->groupBy('rkap_work_plan_id') as $workPlanId => $items)
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Akun Belanja / COA</th>
                                            <th>Detail Belanja (Remarks)</th>
                                            <th class="text-center text-nowrap">Volume & Satuan</th>
                                            <th class="text-end text-nowrap">Anggaran RKAP</th>
                                            <th class="text-end text-nowrap">Realisasi YTD</th>
                                            <th class="text-end text-nowrap">Proyeksi (Total)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($items as $bi)
                                            @php
                                                $realizationYtd = (float) $bi->realizations->sum('amount');
                                            @endphp
                                            <tr>
                                                <td>
                                                    <strong class="text-dark">{{ $bi->account_code }}</strong>
                                                    <div class="small text-muted">{{ $bi->description }}</div>
                                                </td>
                                                <td>
                                                    <span class="small">{{ $bi->remarks ?: '-' }}</span>
                                                </td>
                                                <td class="text-center text-nowrap small">
                                                    {{ $bi->quantity }} {{ $bi->unit }}
                                                    @if($bi->unit_2)
                                                        &times; {{ $bi->quantity_2 }} {{ $bi->unit_2 }}
                                                    @endif
                                                </td>
                                                <td class="text-end text-nowrap fw-semibold">
                                                    Rp {{ number_format($bi->total_price, 0, ',', '.') }}
                                                </td>
                                                <td class="text-end text-nowrap text-success fw-semibold">
                                                    Rp {{ number_format($realizationYtd, 0, ',', '.') }}
                                                </td>
                                                <td class="text-nowrap">
                                                    <div class="d-flex justify-content-end align-items-center gap-2">
                                                        <span class="fw-bold text-dark">Rp {{ number_format($bi->projections->sum('amount'), 0, ',', '.') }}</span>
                                                        <button type="button" 
                                                                class="btn btn-xs btn-icon btn-outline-primary p-1" 
                                                                wire:click="selectBudgetItem({{ $bi->id }})"
                                                                title="Input/Edit Proyeksi">
                                                            <i class="bx bx-edit-alt"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif

        {{-- Modal Input Proyeksi --}}
        <div class="modal fade" 
             id="projectionModal" 
             tabindex="-1" 
             aria-hidden="true"
             x-data
             @open-projection-modal.window="new bootstrap.Modal(document.getElementById('projectionModal')).show()"
             @close-projection-modal.window="
                let m = bootstrap.Modal.getInstance(document.getElementById('projectionModal'));
                if (m) m.hide();
             ">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Input Proyeksi Bulanan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    @if($selectedBudgetItemId)
                        @php
                            $selectedItem = \App\Models\RkapBudgetItem::with(['workPlan.submission.bureau', 'realizations', 'projections'])->find($selectedBudgetItemId);
                        @endphp
                        <form wire:submit.prevent="saveMonthlyProjections">
                            <div class="modal-body">
                                <div class="mb-3 p-2 bg-lighter rounded border">
                                    <span class="text-muted d-block small mb-1 fw-semibold">Detail Item Anggaran</span>
                                    <span class="fw-bold text-dark fs-6">{{ $selectedItem->account_code }} — {{ $selectedItem->description }}</span>
                                    @if($selectedItem->remarks)
                                        <div class="text-muted small mt-1">Remarks: {{ $selectedItem->remarks }}</div>
                                    @endif
                                </div>
                                <div style="max-height: 400px; overflow-y: auto; display: block;" class="border rounded p-1 mb-3 bg-white">
                                    <table class="table table-sm table-bordered align-middle mb-0">
                                        <thead class="table-light sticky-top" style="z-index: 10;">
                                            <tr>
                                                <th style="width: 30%;">Bulan</th>
                                                <th style="width: 35%;" class="text-end">Realisasi (Rp)</th>
                                                <th style="width: 35%;" class="text-end">Jumlah Proyeksi (Rp)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $currentMonth = (int) date('n');
                                                $monthNames = [
                                                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
                                                    4 => 'April', 5 => 'Mei', 6 => 'Juni',
                                                    7 => 'Juli', 8 => 'Agustus', 9 => 'September',
                                                    10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                                ];
                                            @endphp
                                            @for($m = 1; $m <= 12; $m++)
                                                @php
                                                    $realizationAmount = $selectedItem->realizations->where('month', $m)->sum('amount');
                                                    $existingProj = $selectedItem->projections->where('month', $m)->first();
                                                    $hasProjection = $existingProj && (float)$existingProj->amount > 0;
                                                    $isPastMonth = $m < $currentMonth;
                                                    $isLocked = $isPastMonth || $hasProjection;
                                                @endphp
                                                <tr>
                                                    <td class="fw-semibold text-muted">
                                                        {{ $monthNames[$m] }}
                                                        @if($isPastMonth)
                                                            <span class="d-block text-secondary small" style="font-size: 0.7rem;">
                                                                <i class="bx bx-history"></i> Terkunci (Lewat Bulan)
                                                            </span>
                                                        @elseif($hasProjection)
                                                            <span class="d-block text-warning small" style="font-size: 0.7rem;">
                                                                <i class="bx bx-lock-alt"></i> Terkunci (Proyeksi Ada)
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end text-success fw-semibold">
                                                        Rp {{ number_format($realizationAmount, 0, ',', '.') }}
                                                    </td>
                                                    <td>
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text">Rp</span>
                                                            <input type="number" 
                                                                   class="form-control form-control-sm text-end @error('editingProjections.'.$m) is-invalid @enderror"
                                                                   wire:model.defer="editingProjections.{{ $m }}"
                                                                   min="0" 
                                                                   step="0.01"
                                                                   @disabled($isLocked)>
                                                        </div>
                                                        @if($hasProjection)
                                                            <div class="small text-muted text-end mt-1" style="font-size:0.7rem;">
                                                                Nilai Proyeksi: Rp {{ number_format($existingProj->amount, 0, ',', '.') }}
                                                            </div>
                                                        @endif
                                                        @error('editingProjections.'.$m)
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                        @enderror
                                                    </td>
                                                </tr>
                                            @endfor
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary d-flex align-items-center gap-1">
                                    <i class="bx bx-save"></i> Simpan Proyeksi
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
</div>
