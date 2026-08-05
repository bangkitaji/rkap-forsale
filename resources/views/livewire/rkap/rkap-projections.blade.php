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
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 mb-4">
        <h4 class="mb-0"><span class="text-muted fw-light">RKAP /</span> Input Proyeksi</h4>
        <div class="d-flex align-items-center gap-2">
            <button type="button" wire:click="exportExcel" wire:loading.attr="disabled" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1">
                <i class="bx bx-download"></i>
                <span wire:loading.remove wire:target="exportExcel">Download Excel Proyeksi</span>
                <span wire:loading wire:target="exportExcel">Downloading...</span>
            </button>
            @can('rkap.projection.input')
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasRole('verifikator'))
            @if(!$this->isProjectionClosed)
            <a href="{{ route('rkap-projection-upload') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                <i class="bx bx-upload"></i> Upload Massal Proyeksi
            </a>
            @else
            <button type="button" class="btn btn-secondary btn-sm d-flex align-items-center gap-1" disabled title="Upload Massal Proyeksi Ditutup">
                <i class="bx bx-lock-alt"></i> Upload Massal Proyeksi
            </button>
            @endif
            @endif
            @endcan
            <div class="text-muted small border-start ps-2">
                <i class="bx bx-calendar me-1"></i> Periode Perencanaan: <strong>{{ $activePeriodTitle ?? '-' }}</strong>
            </div>
        </div>
    </div>

    {{-- Toast Notification --}}
    <div class="toast-container position-fixed top-0 end-0 p-3 rkap-z-1090">
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
            x-cloak>
            <div class="toast-header text-white" :class="'bg-' + toastType">
                <i class="bx me-2 text-white" :class="toastType === 'success' ? 'bx-check-circle' : 'bx-x-circle'"></i>
                <div class="me-auto fw-semibold" x-text="toastType === 'success' ? 'Berhasil' : 'Error'"></div>
                <button type="button" class="btn-close btn-close-white" @click="showToast = false" aria-label="Close"></button>
            </div>
            <div class="toast-body" x-text="toastMessage"></div>
        </div>
    </div>

    @if(Auth::user()->isAdmin() || Auth::user()->isVerifikator())
    <div class="row">
        <div class="col-12">
            <ul class="nav nav-pills flex-column flex-md-row mb-4">
                <li class="nav-item">
                    <button type="button" class="nav-link @if($activeTab === 'input') active @endif" wire:click="$set('activeTab', 'input')">
                        <i class="bx bx-edit me-1"></i> {{ __('Input Proyeksi') }}
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link @if($activeTab === 'summary') active @endif" wire:click="$set('activeTab', 'summary')">
                        <i class="bx bx-bar-chart-alt-2 me-1"></i> {{ __('Ringkasan Proyeksi') }}
                    </button>
                </li>
            </ul>
        </div>
    </div>
    @endif

    {{-- Alert Info / Guide --}}
    @if($activeTab === 'input')
    @if($this->isProjectionClosed)
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert">
        <span class="badge bg-warning rounded-pill"><i class="bx bx-lock-alt text-white"></i></span>
        <div>
            <strong>{{ __('Penginputan / Edit Proyeksi Ditutup!') }}</strong> {{ __('Pengaturan penginputan data proyeksi saat ini dalam posisi Close. Pengguna tidak dapat menambah, mengedit, atau mengunggah data proyeksi.') }}
        </div>
    </div>
    @endif
    <div class="alert alert-primary d-flex align-items-center gap-2 mb-4" role="alert">
        <span class="badge bg-primary rounded-pill"><i class="bx bx-info-circle text-white"></i></span>
        <div>
            Halaman ini digunakan untuk menginput <strong>{{ __('Proyeksi Realisasi Akhir Tahun') }}</strong> dari masing-masing item anggaran pada periode berjalan (<strong>{{ $activePeriodTitle ?? '-' }}</strong>). Proyeksi ini membantu evaluasi pemenuhan budget dan realisasi.
        </div>
    </div>
    @endif

    {{-- Filter Selector Card --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0 fw-bold">{{ $activeTab === 'summary' ? __('Ringkasan Proyeksi') : __('Input Proyeksi') }} {{ $activePeriodTitle ?? '' }}</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                {{-- Directorate Filter --}}
                <div class="{{ $activeTab === 'summary' ? 'col-md-3' : 'col-md-4' }}">
                    <label class="form-label fw-semibold">{{ __('Direktorat') }}</label>
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

                        <div class="dropdown-menu w-100 p-2 shadow-sm border mt-1 rkap-dropdown-scroll"
                            :class="{ 'show': open }">

                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light"><i class="bx bx-search"></i></span>
                                <input type="text"
                                    class="form-control"
                                    placeholder="{{ __('Cari direktorat...') }}"
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
                <div class="{{ $activeTab === 'summary' ? 'col-md-3' : 'col-md-4' }}">
                    <label class="form-label fw-semibold">{{ __('Departemen') }}</label>
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

                        <div class="dropdown-menu w-100 p-2 shadow-sm border mt-1 rkap-dropdown-scroll"
                            :class="{ 'show': open }">

                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light"><i class="bx bx-search"></i></span>
                                <input type="text"
                                    class="form-control"
                                    placeholder="{{ __('Cari departemen...') }}"
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
                <div class="{{ $activeTab === 'summary' ? 'col-md-3' : 'col-md-4' }}">
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

                        <div class="dropdown-menu w-100 p-2 shadow-sm border mt-1 rkap-dropdown-scroll"
                            :class="{ 'show': open }">

                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light"><i class="bx bx-search"></i></span>
                                <input type="text"
                                    class="form-control"
                                    placeholder="{{ __('Cari biro...') }}"
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

                {{-- Status Filter (Multi-Select) --}}
                @if($activeTab === 'summary')
                <div class="col-md-3">
                    <label class="form-label fw-semibold">{{ __('Status Pengisian') }}</label>
                    <div class="position-relative" x-data="{
                        open: false,
                        selected: @entangle('filterStatus').live,
                        options: [
                            { value: 'Selesai',      label: 'Selesai',      color: 'bg-label-success' },
                            { value: 'Sedang Diisi', label: 'Sedang Diisi', color: 'bg-label-warning' },
                            { value: 'Belum Diisi',  label: 'Belum Diisi',  color: 'bg-label-secondary' },
                        ],
                        toggle(value) {
                            if (this.selected.includes(value)) {
                                this.selected = this.selected.filter(v => v !== value);
                            } else {
                                this.selected = [...this.selected, value];
                            }
                        },
                        get label() {
                            if (!this.selected || this.selected.length === 0) return '-- Semua Status --';
                            if (this.selected.length === 1) return this.selected[0];
                            return this.selected.length + ' status dipilih';
                        }
                    }" @click.outside="open = false">

                        <button type="button"
                            class="form-select text-start d-flex align-items-center justify-content-between"
                            @click="open = !open">
                            <span x-text="label" class="text-truncate"></span>
                            <span x-show="selected && selected.length > 0"
                                class="badge bg-primary rounded-pill ms-2 flex-shrink-0"
                                x-text="selected.length"
                                x-cloak></span>
                        </button>

                        <div class="dropdown-menu w-100 p-2 shadow-sm border mt-1"
                            :class="{ 'show': open }" style="min-width: 200px;">

                            <div class="list-group list-group-flush">
                                {{-- Clear all --}}
                                <button type="button"
                                    class="list-group-item list-group-item-action py-1 px-2 border-0 rounded text-start text-muted small"
                                    @click="selected = []">
                                    <i class="bx bx-x me-1"></i> Hapus Semua
                                </button>

                                <div class="dropdown-divider"></div>

                                <template x-for="opt in options" :key="opt.value">
                                    <button type="button"
                                        class="list-group-item list-group-item-action py-2 px-2 border-0 rounded text-start d-flex align-items-center gap-2"
                                        @click.stop="toggle(opt.value)">
                                        <input type="checkbox"
                                            class="form-check-input flex-shrink-0 mt-0"
                                            style="pointer-events: none;"
                                            :checked="selected.includes(opt.value)">
                                        <span x-text="opt.label" class="small fw-semibold"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Dynamic Filter Summary Cards --}}
    @if($activePeriodId)
    <div class="row g-4 mb-4" wire:key="filtered-summary-cards-{{ $directorateId }}-{{ $departmentId }}-{{ $bureauId }}">
        <!-- Total Anggaran RKAP -->
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="avatar rounded-circle bg-label-primary p-2"><i class="bx bx-wallet fs-4"></i></span>
                        <span class="badge bg-label-primary">{{ __('RKAP') }}</span>
                    </div>
                    <h4 class="mb-1 fw-bold text-primary">Rp {{ number_format($filteredTotals['total_budget'], 0, ',', '.') }}</h4>
                    <p class="mb-0 text-muted small">{{ __('Total Anggaran RKAP') }}</p>
                </div>
            </div>
        </div>
        <!-- Total Realisasi YTD -->
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="avatar rounded-circle bg-label-success p-2"><i class="bx bx-check-circle fs-4"></i></span>
                        <span class="badge bg-label-success">{{ $filteredTotals['realization_percentage'] }}% {{ __('dari RKAP') }}</span>
                    </div>
                    <h4 class="mb-1 fw-bold text-success">Rp {{ number_format($filteredTotals['total_realization'], 0, ',', '.') }}</h4>
                    <p class="mb-0 text-muted small">{{ __('Total Realisasi YTD') }}</p>
                </div>
            </div>
        </div>
        <!-- Total Proyeksi -->
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="avatar rounded-circle bg-label-info p-2"><i class="bx bx-line-chart fs-4"></i></span>
                        <span class="badge bg-label-info">{{ $filteredTotals['projection_percentage'] }}% {{ __('dari RKAP') }}</span>
                    </div>
                    <h4 class="mb-1 fw-bold text-info">Rp {{ number_format($filteredTotals['total_projection'], 0, ',', '.') }}</h4>
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="mb-0 text-muted small">{{ __('Total Proyeksi (Akhir Tahun)') }}</p>
                        @if($filteredTotals['variance'] < 0)
                        <span class="small text-danger fw-semibold" title="{{ __('Proyeksi melebihi anggaran') }}">(+Rp {{ number_format(abs($filteredTotals['variance']), 0, ',', '.') }})</span>
                        @else
                        <span class="small text-muted" title="{{ __('Sisa anggaran proyeksi') }}">(Sisa Rp {{ number_format($filteredTotals['variance'], 0, ',', '.') }})</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Main Input Form --}}
    @if($activeTab === 'input')
        @if(!$activePeriodId)
    <div class="card py-5 text-center text-muted">
        <div class="card-body">
            <i class="bx bx-calendar-exclamation bx-lg d-block mb-3 text-danger"></i>
            <h5 class="fw-semibold text-danger">{{ __('Periode RKAP Tahun Ini Belum Difinalisasi') }}</h5>
            <p class="small mb-0">Proyeksi hanya dapat diinput pada periode RKAP tahun berjalan ({{ date('Y') }}) yang memiliki status <strong>Finalized</strong>.</p>
        </div>
    </div>
    @elseif(!$directorateId && !$departmentId && !$bureauId && !Auth::user()->isKepalaBiro() && !Auth::user()->isKepalaDepartemen() && !Auth::user()->isDireksi())
    <div class="card py-5 text-center text-muted">
        <div class="card-body">
            <i class="bx bx-pointer bx-lg d-block mb-3 text-secondary"></i>
            <h5 class="fw-semibold">{{ __('Silakan pilih Direktorat, Departemen, atau Biro terlebih dahulu') }}</h5>
            <p class="small mb-0">{{ __('Pilih salah satu filter di atas untuk memuat item anggaran yang akan diproyeksikan.') }}</p>
        </div>
    </div>
    @elseif(!$submissions || $submissions->isEmpty())
    <div class="card py-5 text-center text-muted">
        <div class="card-body">
            <i class="bx bx-info-circle bx-lg d-block mb-3 text-warning"></i>
            <h5 class="fw-semibold">{{ __('Tidak ditemukan pengajuan RKAP yang disetujui') }}</h5>
            <p class="small mb-0">Tidak ada pengajuan RKAP dengan status <strong>{{ __('Disetujui') }}</strong> untuk filter terpilih pada periode <strong>{{ $activePeriodTitle ?? '-' }}</strong>.</p>
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
            @php
                $bureauTotalItems = 0;
                $bureauFilledItems = 0;
                foreach($sub->workPlans as $wp) {
                    $bureauTotalItems += $wp->budgetItems->count();
                    $bureauFilledItems += $wp->budgetItems->filter(fn($bi) => $bi->projections->count() > 0 || (float)$bi->projection > 0)->count();
                }
                $bureauPercent = $bureauTotalItems > 0 ? round(($bureauFilledItems / $bureauTotalItems) * 100) : 0;
                $bureauBadgeClass = $bureauPercent === 100 ? 'bg-label-success' : ($bureauPercent > 0 ? 'bg-label-warning' : 'bg-label-secondary');
            @endphp
            <span class="badge {{ $bureauBadgeClass }} ms-2" title="{{ __('Progres pengisian proyeksi untuk biro ini') }}">
                {{ __('Proyeksi:') }} {{ $bureauFilledItems }}/{{ $bureauTotalItems }} ({{ $bureauPercent }}%)
            </span>
        </div>

        @foreach($sub->workPlans as $wp)
        <div class="card mb-4 border-start border-primary border-3">
            <div class="card-header bg-lighter py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bx bx-list-ul text-primary"></i>
                    <h6 class="mb-0 fw-bold text-primary">{{ $loop->iteration }}. {{ $wp->program_code }} — {{ $wp->program_name }}</h6>
                </div>
                @php
                    $wpTotalItems = $wp->budgetItems->count();
                    $wpFilledItems = $wp->budgetItems->filter(fn($bi) => $bi->projections->count() > 0 || (float)$bi->projection > 0)->count();
                    $wpPercent = $wpTotalItems > 0 ? round(($wpFilledItems / $wpTotalItems) * 100) : 0;
                    $wpBadgeClass = $wpPercent === 100 ? 'bg-label-success' : ($wpPercent > 0 ? 'bg-label-warning' : 'bg-label-secondary');
                @endphp
                <span class="badge {{ $wpBadgeClass }}" title="{{ __('Progres pengisian proyeksi program kerja ini') }}">
                    {{ __('Terisi:') }} {{ $wpFilledItems }}/{{ $wpTotalItems }} ({{ $wpPercent }}%)
                </span>
            </div>

            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Akun Belanja / COA') }}</th>
                                <th>Detail Belanja (Remarks)</th>
                                <th class="text-center text-nowrap">{{ __('Volume & Satuan') }}</th>
                                <th class="text-end text-nowrap">{{ __('Anggaran RKAP') }}</th>
                                <th class="text-end text-nowrap">{{ __('Realisasi YTD') }}</th>
                                <th class="text-center rkap-w-100">Status</th>
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
                                <td class="text-center">
                                    @php
                                        $isBiFilled = $bi->projections->count() > 0 || (float) $bi->projection > 0;
                                    @endphp
                                    @if($isBiFilled)
                                    <span class="badge bg-label-success"><i class="bx bx-check me-1"></i>{{ __('Sudah Input') }}</span>
                                    @else
                                    <span class="badge bg-label-secondary"><i class="bx bx-time-five me-1"></i>{{ __('Belum Input') }}</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    <div class="d-flex justify-content-end align-items-center gap-1">
                                        <span class="fw-bold text-dark me-1">Rp {{ number_format($bi->projection, 0, ',', '.') }}</span>
                                        <button type="button"
                                            class="btn btn-xs btn-icon btn-outline-info p-1"
                                            wire:click="viewHistory({{ $bi->id }})"
                                            title="Riwayat Perubahan Proyeksi">
                                            <i class="bx bx-history"></i>
                                        </button>
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
    @endif

    {{-- Summary Dashboard Tab --}}
    @if($activeTab === 'summary')
    <div class="row g-4 mb-4">
        <!-- Card 1: Total Biro -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="avatar rounded-circle bg-label-info p-2"><i class="bx bx-building fs-4"></i></span>
                    </div>
                    <h4 class="mb-1 fw-bold">{{ $summaryData['stats']['total_bureaus'] }}</h4>
                    <p class="mb-0 text-muted small">{{ __('Total Biro Terdaftar') }}</p>
                </div>
            </div>
        </div>
        <!-- Card 2: Total Item Anggaran -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="avatar rounded-circle bg-label-primary p-2"><i class="bx bx-list-ol fs-4"></i></span>
                    </div>
                    <h4 class="mb-1 fw-bold">{{ number_format($summaryData['stats']['total_items'], 0, ',', '.') }}</h4>
                    <p class="mb-0 text-muted small">{{ __('Total Item Anggaran') }}</p>
                </div>
            </div>
        </div>
        <!-- Card 3: Sudah Diproyeksikan -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="avatar rounded-circle bg-label-success p-2"><i class="bx bx-check-circle fs-4"></i></span>
                    </div>
                    <h4 class="mb-1 fw-bold text-success">
                        {{ number_format($summaryData['stats']['filled_items'], 0, ',', '.') }}
                        <span class="text-muted small fw-normal">/ {{ number_format($summaryData['stats']['total_items'], 0, ',', '.') }}</span>
                    </h4>
                    <p class="mb-0 text-muted small">{{ __('Sudah Diproyeksikan') }}</p>
                </div>
            </div>
        </div>
        <!-- Card 4: Persentase Pengisian -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="avatar rounded-circle bg-label-warning p-2"><i class="bx bx-pie-chart-alt fs-4"></i></span>
                    </div>
                    <h4 class="mb-1 fw-bold text-warning">{{ $summaryData['stats']['percentage'] }}%</h4>
                    <p class="mb-0 text-muted small">{{ __('Persentase Pengisian') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Details Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-0 fw-bold">{{ __('Progress Pengisian Proyeksi per Biro') }}</h5>
                <small class="text-muted">{{ __('Daftar unit kerja yang telah menyerahkan RKAP dan progres pengisian proyeksinya.') }}</small>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="rkap-w-50">No</th>
                        <th>Direktorat / Departemen</th>
                        <th>Biro (Unit Kerja)</th>
                        <th class="text-center">{{ __('Jumlah Item') }}</th>
                        <th class="text-center">{{ __('Terisi') }}</th>
                        <th class="text-center">{{ __('Belum Terisi') }}</th>
                        <th class="rkap-w-150">{{ __('Progres') }}</th>
                        <th class="text-center rkap-w-100">Status</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($summaryData['rows'] as $idx => $row)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>
                            <div class="text-dark fw-semibold small">{{ $row['directorate'] }}</div>
                            <div class="text-muted small">{{ $row['department'] }}</div>
                        </td>
                        <td>
                            <div class="text-primary fw-semibold small">{{ $row['bureau_code'] }}</div>
                            <div class="text-dark fw-bold small">{{ $row['bureau_name'] }}</div>
                        </td>
                        <td class="text-center fw-semibold">{{ $row['total_items'] }}</td>
                        <td class="text-center text-success fw-semibold">{{ $row['filled_items'] }}</td>
                        <td class="text-center text-muted">{{ $row['unfilled_items'] }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress w-100" style="height: 6px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $row['percentage'] }}%" aria-valuenow="{{ $row['percentage'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="small fw-semibold">{{ $row['percentage'] }}%</span>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $row['status_class'] }}">{{ $row['status'] }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bx bx-info-circle fs-4 d-block mb-2 text-warning"></i>
                            {{ __('Tidak ada data pengajuan RKAP yang terverifikasi.') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
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
                <div class="modal-header d-flex justify-content-between align-items-center">
                    <h5 class="modal-title mb-0">{{ __('Input Proyeksi Bulanan') }}</h5>
                    <div class="d-flex align-items-center gap-2">
                        @if($selectedBudgetItemId)
                        <button type="button" class="btn btn-sm btn-outline-info d-flex align-items-center gap-1"
                            wire:click="viewHistory({{ $selectedBudgetItemId }})">
                            <i class="bx bx-history"></i> {{ __('Lihat Riwayat') }}
                        </button>
                        @endif
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                @if($selectedBudgetItemId)
                @php
                $selectedItem = \App\Models\RkapBudgetItem::with(['workPlan.submission.bureau', 'monthlies', 'realizations', 'projections'])->find($selectedBudgetItemId);
                @endphp
                <form wire:submit.prevent="saveMonthlyProjections">
                    <div class="modal-body">
                        <div class="mb-3 p-2 bg-lighter rounded border d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted d-block small mb-1 fw-semibold">{{ __('Detail Item Anggaran') }}</span>
                                <span class="fw-bold text-dark fs-6">{{ $selectedItem->account_code }} — {{ $selectedItem->description }}</span>
                                @if($selectedItem->remarks)
                                <div class="text-muted small mt-1">Remarks: {{ $selectedItem->remarks }}</div>
                                @endif
                            </div>
                            <div class="text-end border-start ps-3 rkap-min-w-220">
                                <span class="text-muted d-block small mb-1 fw-semibold">Rencana Anggaran (Total)</span>
                                <span class="fw-bold text-primary fs-6">Rp {{ number_format($selectedItem->total_price, 0, ',', '.') }}</span>
                                <span class="text-muted d-block small mt-2 mb-1 fw-semibold">{{ __('Akumulasi Proyeksi') }}</span>
                                @php
                                $totalEditingProj = 0.00;
                                if ($inputMode === 'yearly') {
                                $totalEditingProj = (float)$yearlyProjection;
                                } else {
                                $_period = $selectedItem->workPlan->submission->period;
                                for ($__m = 1; $__m <= 12; $__m++) {
                                    $__hasRealization=$selectedItem->realizations->where('month', $__m)->count() > 0;
                                    $__realizationAmount = (float) $selectedItem->realizations->where('month', $__m)->sum('amount');
                                    $__isClosed = $_period && $_period->isMonthClosed($__m);
                                    if ($__hasRealization) {
                                    $totalEditingProj += $__realizationAmount;
                                    } elseif ($__isClosed) {
                                    // Closed without realization: keep the stored projection amount
                                    $totalEditingProj += (float) ($selectedItem->projections->where('month', $__m)->first()?->amount ?? 0.00);
                                    } else {
                                    $__val = $editingProjections[$__m] ?? 0;
                                    $totalEditingProj += is_numeric($__val) ? (float)$__val : 0.00;
                                    }
                                    }
                                    }
                                    $isOverBudget = $totalEditingProj > (float) $selectedItem->total_price;
                                    @endphp
                                    <span class="fw-bold fs-6 {{ $isOverBudget ? 'text-danger' : 'text-success' }}">
                                        Rp {{ number_format($totalEditingProj, 0, ',', '.') }}
                                    </span>
                            </div>
                        </div>

                        @if($this->isProjectionClosed)
                        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" role="alert">
                            <i class="bx bx-lock-alt fs-4"></i>
                            <div class="small fw-semibold">{{ __('Penginputan dan perubahan data proyeksi saat ini sedang ditutup oleh administrator. Data hanya dapat dilihat.') }}</div>
                        </div>
                        @endif

                        @error('editingProjections')
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" role="alert" wire:key="accumulation-error-{{ $selectedBudgetItemId }}">
                            <i class="bx bx-error-circle fs-4"></i>
                            <div class="small fw-semibold">{{ $message }}</div>
                        </div>
                        @enderror

                        {{-- Input Method Toggle Selector --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold d-block">{{ __('Metode Input Proyeksi') }}</label>
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
                            <label class="form-label fw-bold text-dark fs-6">{{ __('Proyeksi Tahunan') }}</label>
                            @php
                            $period = $selectedItem->workPlan->submission->period;
                            $hasClosedMonths = false;
                            for ($m = 1; $m <= 12; $m++) {
                                if ($period && $period->isMonthClosed($m)) {
                                $hasClosedMonths = true;
                                break;
                                }
                                }
                                @endphp
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
                                    let num = Math.round(parseFloat(val));
                                    if (isNaN(num)) return '';
                                    return new Intl.NumberFormat('id-ID').format(num);
                                },
                                onInput(e) {
                                    let cursor = e.target.selectionStart;
                                    let originalLength = e.target.value.length;
                                    
                                    let clean = e.target.value.replace(/[^-0-9]/g, '').replace(/(?!^)-/g, '');
                                    this.raw = (clean === '' || clean === '-') ? null : parseFloat(clean);
                                    this.display = clean === '-' ? '-' : this.format(clean);
                                    
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
                                                                                     @disabled($hasClosedMonths || $this->isProjectionClosed)
                                            placeholder="{{ __('Masukkan total proyeksi pertahun...') }}">
                                    </div>
                                </div>
                                @error('yearlyProjection')
                                <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                                @enderror
                                @if($hasClosedMonths)
                                <div class="form-text mt-2 text-danger">
                                    <i class="bx bx-lock-alt me-1"></i>
                                    Proyeksi tahunan terkunci karena terdapat bulan pada periode berjalan yang telah ditutup.
                                </div>
                                @endif
                                <div class="form-text mt-2 text-muted">
                                    <i class="bx bx-info-circle me-1"></i>
                                    Nilai proyeksi tahunan akan disimpan secara utuh tanpa didistribusikan per bulan.
                                </div>
                        </div>
                        @else
                        <div class="border rounded p-1 mb-3 bg-white rkap-timeline-scroll d-block">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light sticky-top rkap-z-10">
                                    <tr>
                                        <th class="rkap-w-20p">{{ __('Bulan') }}</th>
                                        <th class="text-end rkap-w-25p">{{ __('Rencana Anggaran') }}</th>
                                        <th class="text-end rkap-w-25p">{{ __('Realisasi') }}</th>
                                        <th class="text-end rkap-w-30p">{{ __('Proyeksi') }}</th>
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
                                        $period = $selectedItem->workPlan->submission->period;
                                        $isClosed = $period && $period->isMonthClosed($m);
                                        $isLocked = $hasRealization || $isClosed;
                                        @endphp
                                        <tr wire:key="projection-row-{{ $m }}-{{ $selectedBudgetItemId }}">
                                            <td class="fw-semibold text-muted">
                                                {{ $monthNames[$m] }}
                                                @if($hasRealization)
                                                <span class="d-block text-warning small rkap-font-07">
                                                    <i class="bx bx-lock-alt"></i> Terkunci (Realisasi Ada)
                                                </span>
                                                @elseif($isClosed)
                                                <span class="d-block text-danger small rkap-font-07">
                                                    <i class="bx bx-lock-alt"></i> Terkunci (Closing Periode)
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
                                                        let num = Math.round(parseFloat(val));
                                                        if (isNaN(num)) return '';
                                                        return new Intl.NumberFormat('id-ID').format(num);
                                                     },
                                                    onInput(e) {
                                                        let cursor = e.target.selectionStart;
                                                        let originalLength = e.target.value.length;
                                                        
                                                        let clean = e.target.value.replace(/[^-0-9]/g, '').replace(/(?!^)-/g, '');
                                                        this.raw = (clean === '' || clean === '-') ? null : parseFloat(clean);
                                                        this.display = clean === '-' ? '-' : this.format(clean);
                                                        
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
                                                            @disabled($isLocked || $this->isProjectionClosed)>
                                                    </div>
                                                </div>
                                                @if($isLocked && $existingProj)
                                                <div class="small text-muted text-end mt-1 rkap-font-07">
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

                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Catatan Perubahan (Opsional)') }}</label>
                            <textarea class="form-control" rows="2" wire:model="projectionNotes" placeholder="{{ __('Tambahkan catatan atau alasan penyesuaian proyeksi (jika ada)...') }}"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                        <button type="submit"
                            class="btn btn-primary d-flex align-items-center gap-1"
                            @disabled($this->isProjectionClosed || ($isOverBudget && \App\Models\Setting::get('rkap_allow_projection_exceed_budget', '0') !== '1'))
                            @if($this->isProjectionClosed) title="Penginputan proyeksi sedang ditutup" @elseif($isOverBudget) title="Total proyeksi melebihi total anggaran RKAP" @endif>
                            <i class="bx bx-save"></i> {{ __('Simpan Proyeksi') }}
                        </button>
                    </div>            </div>
                </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal Riwayat Track Back Proyeksi --}}
    <div class="modal fade"
        id="projectionHistoryModal"
        tabindex="-1"
        aria-hidden="true"
        x-data
        @open-projection-history-modal.window="
            let editEl = document.getElementById('projectionModal');
            if (editEl) {
                let editM = bootstrap.Modal.getInstance(editEl);
                if (editM) editM.hide();
            }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('projectionHistoryModal')).show();
        ">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <i class="bx bx-history text-primary fs-4"></i>
                        <span>{{ __('Riwayat Perubahan Proyeksi') }}</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($historyItemName)
                    <div class="alert alert-primary d-flex align-items-center mb-4">
                        <i class="bx bx-info-circle fs-4 me-2"></i>
                        <div>
                            <strong>{{ __('Item Anggaran') }}:</strong> {{ $historyItemName }}
                        </div>
                    </div>
                    @endif

                    @if(empty($historyLogs))
                    <div class="text-center py-5 text-muted">
                        <i class="bx bx-history fs-1 mb-2 d-block"></i>
                        <p class="mb-0">{{ __('Belum ada riwayat perubahan proyeksi untuk item ini.') }}</p>
                    </div>
                    @else
                    <div class="timeline p-2">
                        @foreach($historyLogs as $index => $log)
                        <div class="card mb-3 border shadow-none" x-data="{ expanded: false }">
                            <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center cursor-pointer" @click="expanded = !expanded">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge {{ $log['source'] === 'Upload Massal Excel' ? 'bg-info' : 'bg-primary' }}">
                                        {{ $log['source'] }}
                                    </span>
                                    <span class="fw-semibold text-dark">{{ $log['created_at'] }}</span>
                                    <span class="text-muted small">oleh <strong>{{ $log['user_name'] }}</strong></span>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="text-end">
                                        <span class="text-muted small me-1">Total:</span>
                                        <span class="text-decoration-line-through text-muted me-1">Rp {{ number_format($log['old_total'], 0, ',', '.') }}</span>
                                        <i class="bx bx-right-arrow-alt text-primary"></i>
                                        <span class="fw-bold text-primary">Rp {{ number_format($log['new_total'], 0, ',', '.') }}</span>
                                    </div>
                                    <button type="button" class="btn btn-xs btn-icon btn-outline-secondary">
                                        <i class="bx" :class="expanded ? 'bx-chevron-up' : 'bx-chevron-down'"></i>
                                    </button>
                                </div>
                            </div>
                            @if($log['notes'])
                            <div class="px-3 py-2 bg-light border-top text-muted small">
                                <i class="bx bx-comment-detail me-1 text-primary"></i>
                                <strong>{{ __('Catatan') }}:</strong> {{ $log['notes'] }}
                            </div>
                            @endif
                            <div class="card-body p-2" x-show="expanded" x-transition>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0 align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>{{ __('Bulan / Perincian') }}</th>
                                                <th class="text-end">{{ __('Sebelum Edit') }}</th>
                                                <th class="text-end">{{ __('Sesudah Edit') }}</th>
                                                <th class="text-end">{{ __('Selisih / Perubahan') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $monthFullNames = [
                                                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                                ];
                                                $changedMonths = [];
                                                for ($m = 1; $m <= 12; $m++) {
                                                    $oldV = (float)($log['old_monthly'][$m] ?? 0);
                                                    $newV = (float)($log['new_monthly'][$m] ?? 0);
                                                    if (abs($oldV - $newV) > 0.01) {
                                                        $changedMonths[$m] = [
                                                            'name' => $monthFullNames[$m],
                                                            'old'  => $oldV,
                                                            'new'  => $newV,
                                                            'diff' => $newV - $oldV,
                                                        ];
                                                    }
                                                }
                                                $totalDiff = (float)$log['new_total'] - (float)$log['old_total'];
                                            @endphp

                                            @if(!empty($changedMonths))
                                                @foreach($changedMonths as $mInfo)
                                                <tr>
                                                    <td class="fw-semibold text-dark">{{ $mInfo['name'] }}</td>
                                                    <td class="text-end text-muted">Rp {{ number_format($mInfo['old'], 0, ',', '.') }}</td>
                                                    <td class="text-end fw-bold text-primary">Rp {{ number_format($mInfo['new'], 0, ',', '.') }}</td>
                                                    <td class="text-end fw-bold {{ $mInfo['diff'] > 0 ? 'text-success' : ($mInfo['diff'] < 0 ? 'text-danger' : 'text-muted') }}">
                                                        {{ $mInfo['diff'] > 0 ? '+' : '' }}Rp {{ number_format($mInfo['diff'], 0, ',', '.') }}
                                                    </td>
                                                </tr>
                                                @endforeach
                                            @elseif($log['input_mode'] === 'yearly' || abs($totalDiff) > 0.01)
                                                <tr>
                                                    <td class="fw-semibold text-dark">{{ __('Proyeksi Tahunan') }}</td>
                                                    <td class="text-end text-muted">Rp {{ number_format($log['old_total'], 0, ',', '.') }}</td>
                                                    <td class="text-end fw-bold text-primary">Rp {{ number_format($log['new_total'], 0, ',', '.') }}</td>
                                                    <td class="text-end fw-bold {{ $totalDiff > 0 ? 'text-success' : ($totalDiff < 0 ? 'text-danger' : 'text-muted') }}">
                                                        {{ $totalDiff > 0 ? '+' : '' }}Rp {{ number_format($totalDiff, 0, ',', '.') }}
                                                    </td>
                                                </tr>
                                            @else
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted small py-2">
                                                        <i class="bx bx-info-circle me-1 text-warning"></i>
                                                        {{ __('Tidak ada rincian bulan yang mengalami perubahan nilai.') }}
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                        <tfoot class="table-light fw-bold">
                                            <tr>
                                                <td>{{ __('Total Akumulasi Proyeksi') }}</td>
                                                <td class="text-end text-muted">Rp {{ number_format($log['old_total'], 0, ',', '.') }}</td>
                                                <td class="text-end text-primary">Rp {{ number_format($log['new_total'], 0, ',', '.') }}</td>
                                                <td class="text-end {{ $totalDiff > 0 ? 'text-success' : ($totalDiff < 0 ? 'text-danger' : 'text-muted') }}">
                                                    {{ $totalDiff > 0 ? '+' : '' }}Rp {{ number_format($totalDiff, 0, ',', '.') }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    $wire.on('open-projection-modal', () => {
        let el = document.getElementById('projectionModal');
        if (el) {
            let m = bootstrap.Modal.getOrCreateInstance(el);
            m.show();
        }
    });

    $wire.on('close-projection-modal', () => {
        let el = document.getElementById('projectionModal');
        if (el) {
            let m = bootstrap.Modal.getInstance(el);
            if (m) m.hide();
        }
    });

    $wire.on('open-projection-history-modal', () => {
        let editModalEl = document.getElementById('projectionModal');
        if (editModalEl) {
            let editM = bootstrap.Modal.getInstance(editModalEl);
            if (editM) editM.hide();
        }

        let historyModalEl = document.getElementById('projectionHistoryModal');
        if (historyModalEl) {
            let historyM = bootstrap.Modal.getOrCreateInstance(historyModalEl);
            historyM.show();
        }
    });
</script>
@endscript