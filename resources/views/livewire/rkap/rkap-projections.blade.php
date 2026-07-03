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

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 mb-4">
        <h4 class="mb-0"><span class="text-muted fw-light">RKAP /</span> Input Proyeksi</h4>
        <div class="d-flex align-items-center gap-2">
            @can('rkap.projection.input')
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasRole('verifikator'))
            <a href="{{ route('rkap-projection-upload') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                <i class="bx bx-upload"></i> Upload Massal Proyeksi
            </a>
            @endif
            @endcan
            <div class="text-muted small border-start ps-2">
                <i class="bx bx-calendar me-1"></i> Periode Perencanaan: <strong>{{ $activePeriodTitle ?? '-' }}</strong>
            </div>
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
        <div class="card-header">
            <h5 class="mb-0 fw-bold">Input Proyeksi {{ $activePeriodTitle ?? '' }}</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                {{-- Directorate Filter --}}
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Direktorat</label>
                    <div class="position-relative" x-data="{
                        open: false,
                        search: '',
                        selectedId: @entangle('directorateId').live,
                        get selectedLabel() {
                            const list = {{ json_encode($directorateOptions->map(fn($d) => ['id' => $d->id, 'label' => $d->code . ' — ' . $d->name])->toArray()) }};
                            const found = list.find(item => item.id == this.selectedId);
                            return found ? found.label : '-- Semua Direktorat --';
                        }
                    }" @click.outside="open = false">

                        <button type="button"
                            class="form-select text-start"
                            @click="open = !open"
                            @disabled(Auth::user()->isKepalaBiro() || Auth::user()->isKepalaDepartemen() || Auth::user()->isDireksi())>
                            <span x-text="selectedLabel"></span>
                        </button>

                        <div class="dropdown-menu w-100 p-2 shadow-sm border mt-1"
                            :class="{ 'show': open }"
                            style="position: absolute; z-index: 1000; max-height: 250px; overflow-y: auto;">

                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light"><i class="bx bx-search"></i></span>
                                <input type="text"
                                    class="form-control"
                                    placeholder="Cari direktorat..."
                                    x-model="search"
                                    @keydown.escape.prevent.stop="open = false"
                                    @click.stop>
                            </div>

                            <div class="dropdown-divider"></div>

                            <div class="list-group list-group-flush">
                                <button type="button"
                                    class="list-group-item list-group-item-action py-1 px-2 border-0 rounded text-start text-dark"
                                    @click="
                                            selectedId = null;
                                            open = false;
                                            search = '';
                                        ">
                                    -- Semua Direktorat --
                                </button>
                                <template x-for="item in {{ json_encode($directorateOptions->map(fn($d) => ['id' => $d->id, 'code' => $d->code, 'name' => $d->name, 'label' => $d->code . ' — ' . $d->name])->toArray()) }}.filter(d => !search || d.code.toLowerCase().includes(search.toLowerCase()) || d.name.toLowerCase().includes(search.toLowerCase()))" :key="item.id">
                                    <button type="button"
                                        class="list-group-item list-group-item-action py-1 px-2 border-0 rounded text-start"
                                        :class="selectedId == item.id ? 'active text-white' : 'text-dark'"
                                        @click="
                                                selectedId = item.id;
                                                open = false;
                                                search = '';
                                            ">
                                        <span x-text="item.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Department Filter --}}
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Departemen</label>
                    <div class="position-relative" x-data="{
                        open: false,
                        search: '',
                        selectedId: @entangle('departmentId').live,
                        get selectedLabel() {
                            const list = {{ json_encode($departmentOptions->map(fn($d) => ['id' => $d->id, 'label' => $d->code . ' — ' . $d->name])->toArray()) }};
                            const found = list.find(item => item.id == this.selectedId);
                            return found ? found.label : '-- Semua Departemen --';
                        }
                    }" @click.outside="open = false">

                        <button type="button"
                            class="form-select text-start"
                            @click="open = !open"
                            @disabled(Auth::user()->isKepalaBiro() || Auth::user()->isKepalaDepartemen())>
                            <span x-text="selectedLabel"></span>
                        </button>

                        <div class="dropdown-menu w-100 p-2 shadow-sm border mt-1"
                            :class="{ 'show': open }"
                            style="position: absolute; z-index: 1000; max-height: 250px; overflow-y: auto;">

                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light"><i class="bx bx-search"></i></span>
                                <input type="text"
                                    class="form-control"
                                    placeholder="Cari departemen..."
                                    x-model="search"
                                    @keydown.escape.prevent.stop="open = false"
                                    @click.stop>
                            </div>

                            <div class="dropdown-divider"></div>

                            <div class="list-group list-group-flush">
                                <button type="button"
                                    class="list-group-item list-group-item-action py-1 px-2 border-0 rounded text-start text-dark"
                                    @click="
                                            selectedId = null;
                                            open = false;
                                            search = '';
                                        ">
                                    -- Semua Departemen --
                                </button>
                                <template x-for="item in {{ json_encode($departmentOptions->map(fn($d) => ['id' => $d->id, 'code' => $d->code, 'name' => $d->name, 'label' => $d->code . ' — ' . $d->name])->toArray()) }}.filter(d => !search || d.code.toLowerCase().includes(search.toLowerCase()) || d.name.toLowerCase().includes(search.toLowerCase()))" :key="item.id">
                                    <button type="button"
                                        class="list-group-item list-group-item-action py-1 px-2 border-0 rounded text-start"
                                        :class="selectedId == item.id ? 'active text-white' : 'text-dark'"
                                        @click="
                                                selectedId = item.id;
                                                open = false;
                                                search = '';
                                            ">
                                        <span x-text="item.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Bureau Filter --}}
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Biro</label>
                    <div class="position-relative" x-data="{
                        open: false,
                        search: '',
                        selectedId: @entangle('bureauId').live,
                        get selectedLabel() {
                            const list = {{ json_encode($bureauOptions->map(fn($b) => ['id' => $b->id, 'label' => $b->code . ' — ' . $b->name])->toArray()) }};
                            const found = list.find(item => item.id == this.selectedId);
                            return found ? found.label : '-- Semua Biro --';
                        }
                    }" @click.outside="open = false">

                        <button type="button"
                            class="form-select text-start"
                            @click="open = !open"
                            @disabled(Auth::user()->isKepalaBiro())>
                            <span x-text="selectedLabel"></span>
                        </button>

                        <div class="dropdown-menu w-100 p-2 shadow-sm border mt-1"
                            :class="{ 'show': open }"
                            style="position: absolute; z-index: 1000; max-height: 250px; overflow-y: auto;">

                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light"><i class="bx bx-search"></i></span>
                                <input type="text"
                                    class="form-control"
                                    placeholder="Cari biro..."
                                    x-model="search"
                                    @keydown.escape.prevent.stop="open = false"
                                    @click.stop>
                            </div>

                            <div class="dropdown-divider"></div>

                            <div class="list-group list-group-flush">
                                <button type="button"
                                    class="list-group-item list-group-item-action py-1 px-2 border-0 rounded text-start text-dark"
                                    @click="
                                            selectedId = null;
                                            open = false;
                                            search = '';
                                        ">
                                    -- Semua Biro --
                                </button>
                                <template x-for="item in {{ json_encode($bureauOptions->map(fn($b) => ['id' => $b->id, 'code' => $b->code, 'name' => $b->name, 'label' => $b->code . ' — ' . $b->name])->toArray()) }}.filter(b => !search || b.code.toLowerCase().includes(search.toLowerCase()) || b.name.toLowerCase().includes(search.toLowerCase()))" :key="item.id">
                                    <button type="button"
                                        class="list-group-item list-group-item-action py-1 px-2 border-0 rounded text-start"
                                        :class="selectedId == item.id ? 'active text-white' : 'text-dark'"
                                        @click="
                                                selectedId = item.id;
                                                open = false;
                                                search = '';
                                            ">
                                        <span x-text="item.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
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
    @elseif(!$directorateId && !$departmentId && !$bureauId && !Auth::user()->isKepalaBiro() && !Auth::user()->isKepalaDepartemen() && !Auth::user()->isDireksi())
    <div class="card py-5 text-center text-muted">
        <div class="card-body">
            <i class="bx bx-pointer bx-lg d-block mb-3 text-secondary"></i>
            <h5 class="fw-semibold">Silakan pilih Direktorat, Departemen, atau Biro terlebih dahulu</h5>
            <p class="small mb-0">Pilih salah satu filter di atas untuk memuat item anggaran yang akan diproyeksikan.</p>
        </div>
    </div>
    @elseif(!$submissions || $submissions->isEmpty())
    <div class="card py-5 text-center text-muted">
        <div class="card-body">
            <i class="bx bx-info-circle bx-lg d-block mb-3 text-warning"></i>
            <h5 class="fw-semibold">Tidak ditemukan pengajuan RKAP yang disetujui</h5>
            <p class="small mb-0">Tidak ada pengajuan RKAP dengan status <strong>Disetujui</strong> untuk filter terpilih pada periode <strong>{{ $activePeriodTitle ?? '-' }}</strong>.</p>
        </div>
    </div>
    @else
    @foreach($submissions as $sub)
    <div class="mb-5">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bx bx-building fs-4 text-primary"></i>
            <h5 class="fw-bold text-dark mb-0">
                Biro: <span class="text-primary">{{ $sub->bureau->code }} — {{ $sub->bureau->name }}</span>
            </h5>
        </div>

        @foreach($sub->workPlans as $wp)
        <div class="card mb-4 border-start border-primary border-3">
            <div class="card-header bg-lighter py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bx bx-list-ul text-primary"></i>
                    <h6 class="mb-0 fw-bold text-primary">{{ $wp->program_code }} — {{ $wp->program_name }}</h6>
                </div>
            </div>

            <div class="card-body p-3">
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
                            @foreach($wp->budgetItems as $bi)
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
                                        <span class="fw-bold text-dark">Rp {{ number_format($bi->projection, 0, ',', '.') }}</span>
                                        @can('rkap.projection.input')
                                        <button type="button"
                                            class="btn btn-xs btn-icon btn-outline-primary p-1"
                                            wire:click="selectBudgetItem({{ $bi->id }})"
                                            title="Input/Edit Proyeksi">
                                            <i class="bx bx-edit-alt"></i>
                                        </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endforeach
    @endif

    {{-- Modal Input Proyeksi --}}
    <div class="modal fade"
        id="projectionModal"
        tabindex="-1"
        aria-hidden="true"
        wire:ignore.self
        x-data
        @open-projection-modal.window="bootstrap.Modal.getOrCreateInstance(document.getElementById('projectionModal')).show()"
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
                $selectedItem = \App\Models\RkapBudgetItem::with(['workPlan.submission.bureau', 'monthlies', 'realizations', 'projections'])->find($selectedBudgetItemId);
                @endphp
                <form wire:submit.prevent="saveMonthlyProjections">
                    <div class="modal-body">
                        <div class="mb-3 p-2 bg-lighter rounded border d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted d-block small mb-1 fw-semibold">Detail Item Anggaran</span>
                                <span class="fw-bold text-dark fs-6">{{ $selectedItem->account_code }} — {{ $selectedItem->description }}</span>
                                @if($selectedItem->remarks)
                                <div class="text-muted small mt-1">Remarks: {{ $selectedItem->remarks }}</div>
                                @endif
                            </div>
                            <div class="text-end border-start ps-3" style="min-width: 220px;">
                                <span class="text-muted d-block small mb-1 fw-semibold">Rencana Anggaran (Total)</span>
                                <span class="fw-bold text-primary fs-6">Rp {{ number_format($selectedItem->total_price, 0, ',', '.') }}</span>
                                <span class="text-muted d-block small mt-2 mb-1 fw-semibold">Akumulasi Proyeksi</span>
                                @php
                                $totalEditingProj = $inputMode === 'yearly' ? (float)$yearlyProjection : array_sum(array_map(fn($v) => is_numeric($v) ? (float)$v : 0, $editingProjections));
                                $isOverBudget = $totalEditingProj > (float) $selectedItem->total_price;
                                @endphp
                                <span class="fw-bold fs-6 {{ $isOverBudget ? 'text-danger' : 'text-success' }}">
                                    Rp {{ number_format($totalEditingProj, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>

                        @error('editingProjections')
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" role="alert" wire:key="accumulation-error-{{ $selectedBudgetItemId }}">
                            <i class="bx bx-error-circle fs-4"></i>
                            <div class="small fw-semibold">{{ $message }}</div>
                        </div>
                        @enderror

                        {{-- Input Method Toggle Selector --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold d-block">Metode Input Proyeksi</label>
                            <!-- <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="inputMode" id="inputModeMonthly" value="monthly" wire:model.live="inputMode" @disabled($modeLocked)>
                                        <label class="btn btn-outline-primary" for="inputModeMonthly">
                                            <i class="bx bx-calendar me-1"></i> Per Bulan (Bulanan)
                                        </label>

                                        <input type="radio" class="btn-check" name="inputMode" id="inputModeYearly" value="yearly" wire:model.live="inputMode" @disabled($modeLocked)>
                                        <label class="btn btn-outline-primary" for="inputModeYearly">
                                            <i class="bx bx-calendar-event me-1"></i> Per Tahun (Tahunan)
                                        </label>
                                    </div> -->
                            @if($modeLocked)
                            <div class="form-text mt-1 text-warning">
                                <i class="bx bx-lock-alt me-1"></i>
                                Metode input terkunci karena item ini sudah memiliki proyeksi tersimpan.
                            </div>
                            @endif
                        </div>

                        @if($inputMode === 'yearly')
                        <div class="mb-3 p-3 bg-light rounded border">
                            <label class="form-label fw-bold text-dark fs-6">Proyeksi Tahunan</label>
                            <div x-data="{
                                raw: @entangle('yearlyProjection'),
                                display: '',
                                init() {
                                    this.display = this.format(this.raw);
                                    this.$watch('raw', v => {
                                        this.display = this.format(v);
                                    });
                                },
                                format(val) {
                                    if (val === null || val === undefined || val === '') return '';
                                    let clean = val.toString().replace(/[^0-9]/g, '');
                                    if (clean === '') return '';
                                    return new Intl.NumberFormat('id-ID').format(clean);
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
                                },
                                onBlur() {
                                    this.$wire.set('yearlyProjection', this.raw);
                                }
                            }" wire:key="yearly-proj-wrapper-{{ $selectedBudgetItemId }}">
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">Rp</span>
                                    <input type="text"
                                        class="form-control form-control-lg @error('yearlyProjection') is-invalid @enderror"
                                        x-model="display"
                                        @input="onInput"
                                        @blur="onBlur"
                                        placeholder="Masukkan total proyeksi pertahun...">
                                </div>
                            </div>
                            @error('yearlyProjection')
                            <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                            @enderror
                            <div class="form-text mt-2 text-muted">
                                <i class="bx bx-info-circle me-1"></i>
                                Nilai proyeksi tahunan akan disimpan secara utuh tanpa didistribusikan per bulan.
                            </div>
                        </div>
                        @else
                        <div style="max-height: 400px; overflow-y: auto; display: block;" class="border rounded p-1 mb-3 bg-white">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light sticky-top" style="z-index: 10;">
                                    <tr>
                                        <th style="width: 20%;">Bulan</th>
                                        <th style="width: 25%;" class="text-end">Rencana Anggaran (Rp)</th>
                                        <th style="width: 25%;" class="text-end">Realisasi (Rp)</th>
                                        <th style="width: 30%;" class="text-end">Jumlah Proyeksi (Rp)</th>
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
                                        $monthlyBudget=$selectedItem->monthlies->where('month', $m)->first()?->amount ?? 0;
                                        $realizationAmount = $selectedItem->realizations->where('month', $m)->sum('amount');
                                        $hasRealization = $selectedItem->realizations->where('month', $m)->count() > 0;
                                        $existingProj = $selectedItem->projections->where('month', $m)->first();
                                        $isPastMonth = $m < $currentMonth;
                                            $isLocked=$isPastMonth || $hasRealization;
                                            @endphp
                                            <tr wire:key="projection-row-{{ $m }}-{{ $selectedBudgetItemId }}">
                                            <td class="fw-semibold text-muted">
                                                {{ $monthNames[$m] }}
                                                @if($isPastMonth)
                                                <span class="d-block text-secondary small" style="font-size: 0.7rem;">
                                                    <i class="bx bx-history"></i> Terkunci (Lewat Bulan)
                                                </span>
                                                @elseif($hasRealization)
                                                <span class="d-block text-warning small" style="font-size: 0.7rem;">
                                                    <i class="bx bx-lock-alt"></i> Terkunci (Realisasi Ada)
                                                </span>
                                                @endif
                                            </td>
                                            <td class="text-end text-primary fw-semibold">
                                                Rp {{ number_format($monthlyBudget, 0, ',', '.') }}
                                            </td>
                                            <td class="text-end text-success fw-semibold">
                                                Rp {{ number_format($realizationAmount, 0, ',', '.') }}
                                            </td>
                                            <td>
                                                <div x-data="{
                                                    raw: @entangle('editingProjections.' . $m),
                                                    display: '',
                                                    init() {
                                                        this.display = this.format(this.raw);
                                                        this.$watch('raw', v => {
                                                            this.display = this.format(v);
                                                        });
                                                    },
                                                    format(val) {
                                                        if (val === null || val === undefined || val === '') return '';
                                                        let clean = val.toString().replace(/[^0-9]/g, '');
                                                        if (clean === '') return '';
                                                        return new Intl.NumberFormat('id-ID').format(clean);
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
                                                    },
                                                    onBlur() {
                                                        this.$wire.set('editingProjections.{{ $m }}', this.raw);
                                                    }
                                                }" wire:key="proj-wrapper-{{ $m }}-{{ $selectedBudgetItemId }}">
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">Rp</span>
                                                        <input type="text"
                                                            class="form-control form-control-sm text-end @error('editingProjections.'.$m) is-invalid @enderror"
                                                            x-model="display"
                                                            @input="onInput"
                                                            @blur="onBlur"
                                                            @disabled($isLocked)>
                                                    </div>
                                                </div>
                                                @if($isLocked && $existingProj)
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
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit"
                            class="btn btn-primary d-flex align-items-center gap-1"
                            @disabled($isOverBudget)
                            @if($isOverBudget) title="Total proyeksi melebihi total anggaran RKAP" @endif>
                            <i class="bx bx-save"></i> Simpan Proyeksi
                        </button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>