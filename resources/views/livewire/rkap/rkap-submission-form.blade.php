<div>
    <div class="py-3 mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <span class="text-muted fw-light">RKAP /
                    <a href="{{ route('rkap-submissions') }}" class="text-muted text-decoration-none">Pengajuan</a> /
                </span>
                {{ $submissionId ? 'Edit RKAP' : 'Buat RKAP' }}
            </h4>
            <div class="text-muted small">
                <i class="bx bx-calendar me-1"></i> {{ $period->title ?? '' }}
            </div>
        </div>
    </div>

    @if (session()->has('message'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('message') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Grand Total Banner --}}
    <div class="card mb-4 bg-primary text-white">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <div class="small opacity-75">Total Anggaran Keseluruhan</div>
                <div class="fs-3 fw-bold">Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</div>
            </div>
            <i class="bx bx-money bx-lg opacity-50"></i>
        </div>
    </div>

    {{-- Notes --}}
    <div class="card mb-4">
        <div class="card-header"><strong>Catatan Umum</strong></div>
        <div class="card-body">
            <textarea class="form-control" wire:model.live="notes" rows="2" placeholder="Catatan atau keterangan umum untuk pengajuan ini..."></textarea>
        </div>
    </div>

    {{-- Work Plans --}}
    @foreach($workPlans as $wpIdx => $wp)

    {{-- Resolve display labels --}}
    @php
    $selectedWorkPlan = $workPlanOptions->firstWhere('id', $wp['work_plan_id']);
    $selectedActivity = $selectedWorkPlan
    ? $selectedWorkPlan->activities->firstWhere('id', $wp['activity_id'])
    : null;
    @endphp

    <div class="card mb-3 border-start border-primary border-3" wire:key="wp-card-{{ $wpIdx }}">
        {{-- ===== Card Header: Program Kerja searchable select ===== --}}
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2 flex-grow-1">
                    <i class="bx bx-list-ul text-primary flex-shrink-0"></i>
                    <strong class="text-nowrap">Program Kerja {{ $wpIdx + 1 }}</strong>

                    {{-- Searchable Program Kerja select (Alpine.js) --}}
                    <div
                        x-data="{
                            open: false,
                            search: '{{ $selectedWorkPlan ? $selectedWorkPlan->code . " — " . $selectedWorkPlan->title : "" }}',
                            get filtered() {
                                const q = this.search.toLowerCase();
                                return $refs.options ? [...$refs.options.querySelectorAll('option')].filter(o => o.value && o.text.toLowerCase().includes(q)) : [];
                            }
                        }"
                        class="position-relative flex-grow-1"
                        style="max-width: 420px;"
                        wire:key="wp-{{ $wpIdx }}-wp-select-{{ $wp['work_plan_id'] ?? 'null' }}">
                        {{-- Trigger input --}}
                        <div class="input-group input-group-sm">
                            <input
                                type="text"
                                class="form-control form-control-sm @error(" workPlans.$wpIdx.work_plan_id") is-invalid @enderror"
                                placeholder="Cari program kerja..."
                                x-model="search"
                                @focus="open = true"
                                @click.outside="open = false"
                                @input="open = true"
                                value="{{ $selectedWorkPlan ? $selectedWorkPlan->code . ' — ' . $selectedWorkPlan->title : '' }}"
                                autocomplete="off"
                                id="wp-search-{{ $wpIdx }}">
                            @if($wp['work_plan_id'])
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                wire:click="$set('workPlans.{{ $wpIdx }}.work_plan_id', null)"
                                @click="search = ''"
                                title="Hapus pilihan">
                                <i class="bx bx-x"></i>
                            </button>
                            @endif
                        </div>
                        @error("workPlans.$wpIdx.work_plan_id")
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror

                        {{-- Hidden select for Livewire binding --}}
                        <select
                            x-ref="options"
                            wire:model.live="workPlans.{{ $wpIdx }}.work_plan_id"
                            class="d-none"
                            id="wp-select-{{ $wpIdx }}">
                            <option value=""></option>
                            @foreach($workPlanOptions as $wpo)
                            <option value="{{ $wpo->id }}">{{ $wpo->code }} — {{ $wpo->title }}</option>
                            @endforeach
                        </select>

                        {{-- Dropdown list --}}
                        <div
                            x-show="open"
                            x-cloak
                            class="position-absolute bg-white border rounded shadow-sm w-100 mt-1"
                            style="z-index: 1050; max-height: 220px; overflow-y: auto;">
                            @forelse($workPlanOptions as $wpo)
                            <div
                                class="px-3 py-2 cursor-pointer dropdown-item small {{ $wp['work_plan_id'] == $wpo->id ? 'bg-primary text-white' : '' }}"
                                x-show="'{{ strtolower($wpo->code . ' ' . $wpo->title) }}'.includes(search.toLowerCase())"
                                @click="
                                        $wire.set('workPlans.{{ $wpIdx }}.work_plan_id', {{ $wpo->id }});
                                        search = '{{ $wpo->code }} — {{ $wpo->title }}';
                                        open = false;
                                    ">
                                <span class="fw-semibold text-primary">{{ $wpo->code }}</span>
                                <span class="ms-1">{{ $wpo->title }}</span>
                            </div>
                            @empty
                            <div class="px-3 py-2 text-muted small">Tidak ada data program kerja.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Subtotal + delete --}}
                <div class="d-flex align-items-center gap-3 flex-shrink-0">
                    <span class="text-muted small">
                        Subtotal: <strong class="text-dark">Rp {{ number_format(collect($wp['budget_items'])->sum(fn($bi) => ($bi['quantity'] ?? 0) * ($bi['unit_price'] ?? 0)), 0, ',', '.') }}</strong>
                    </span>
                    @if(count($workPlans) > 1)
                    <button type="button" wire:click="removeWorkPlan({{ $wpIdx }})" class="btn btn-sm btn-text-danger rounded-pill" title="Hapus program kerja">
                        <i class="bx bx-trash"></i>
                    </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="row g-3 mb-4">

                {{-- ===== Nama Kegiatan: searchable select filtered by selected work_plan_id ===== --}}
                <div class="col-md-12">
                    <label class="form-label small">Nama Kegiatan <span class="text-danger">*</span></label>
                    @if(!$wp['work_plan_id'])
                    <div class="form-control form-control-sm bg-light text-muted">
                        <i class="bx bx-info-circle me-1"></i> Pilih Program Kerja terlebih dahulu
                    </div>
                    @else
                    @php
                    $activities = $this->getActivitiesForIndex($wpIdx);
                    @endphp
                    <div
                        x-data="{
                                open: false,
                                search: '{{ $selectedActivity ? $selectedActivity->code . " — " . $selectedActivity->title : "" }}',
                            }"
                        class="position-relative"
                        @click.outside="open = false"
                        wire:key="wp-{{ $wpIdx }}-act-select-{{ $wp['work_plan_id'] ?? 'null' }}-{{ $wp['activity_id'] ?? 'null' }}">
                        {{-- Trigger input --}}
                        <div class="input-group input-group-sm">
                            <input
                                type="text"
                                class="form-control form-control-sm @error('workPlans.'.$wpIdx.'.activity_id') is-invalid @enderror"
                                placeholder="Cari kegiatan..."
                                x-model="search"
                                @focus="open = true"
                                @input="open = true"
                                autocomplete="off"
                                id="act-search-{{ $wpIdx }}">
                            @if($wp['activity_id'])
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                wire:click="$set('workPlans.{{ $wpIdx }}.activity_id', null)"
                                @click="search = ''"
                                title="Hapus pilihan">
                                <i class="bx bx-x"></i>
                            </button>
                            @endif
                        </div>
                        @error('workPlans.'.$wpIdx.'.activity_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror

                        {{-- Hidden select for Livewire binding --}}
                        <select
                            wire:model.live="workPlans.{{ $wpIdx }}.activity_id"
                            class="d-none"
                            id="act-select-{{ $wpIdx }}">
                            <option value=""></option>
                            @foreach($activities as $act)
                            <option value="{{ $act->id }}">{{ $act->code }} — {{ $act->title }}</option>
                            @endforeach
                        </select>

                        {{-- Dropdown list --}}
                        <div
                            x-show="open"
                            x-cloak
                            class="position-absolute bg-white border rounded shadow-sm w-100 mt-1"
                            style="z-index: 1050; max-height: 220px; overflow-y: auto;">
                            @forelse($activities as $act)
                            <div
                                class="px-3 py-2 cursor-pointer dropdown-item small {{ $wp['activity_id'] == $act->id ? 'bg-primary text-white' : '' }}"
                                x-show="'{{ strtolower($act->code . ' ' . $act->title) }}'.includes(search.toLowerCase())"
                                @click="
                                            $wire.set('workPlans.{{ $wpIdx }}.activity_id', {{ $act->id }});
                                            search = '{{ $act->code }} — {{ $act->title }}';
                                            open = false;
                                        ">
                                <span class="fw-semibold text-primary">{{ $act->code }}</span>
                                <span class="ms-1">{{ $act->title }}</span>
                            </div>
                            @empty
                            <div class="px-3 py-2 text-muted small">Tidak ada kegiatan untuk program ini.</div>
                            @endforelse
                        </div>
                    </div>
                    @endif
                </div>

                <div class="col-md-12">
                    <label class="form-label small">Deskripsi / Tujuan</label>
                    <textarea class="form-control form-control-sm" wire:model.live="workPlans.{{ $wpIdx }}.description" rows="2" placeholder="Deskripsi kegiatan..."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Target Output</label>
                    <input type="text" class="form-control form-control-sm" wire:model.live="workPlans.{{ $wpIdx }}.output_target" placeholder="Misal: 1 sistem, 100 user">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Satuan</label>
                    <input type="text" class="form-control form-control-sm" wire:model.live="workPlans.{{ $wpIdx }}.unit" placeholder="Paket, Unit, ...">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Volume</label>
                    <input type="number" class="form-control form-control-sm" wire:model.live="workPlans.{{ $wpIdx }}.quantity" min="1">
                </div>
            </div>

            {{-- Budget Items --}}
            <div class="table-responsive"
                x-data="{ dropdownOpen: false }"
                @coa-dropdown-open.window="dropdownOpen = true"
                @coa-dropdown-close.window="dropdownOpen = false"
                :style="dropdownOpen ? 'overflow: visible;' : ''">
                <table class="table table-sm table-bordered align-middle mb-2">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40%">Uraian & Detail Belanja <span class="text-danger">*</span></th>
                            <th style="width:80px">Satuan</th>
                            <th style="width:70px">Vol <span class="text-danger">*</span></th>
                            <th style="width:140px">Harga Satuan (Rp) <span class="text-danger">*</span></th>
                            <th style="width:140px">Total (Rp)</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($wp['budget_items'] as $biIdx => $bi)
                        @php
                            $biTotal = ($bi['quantity'] ?? 0) * ($bi['unit_price'] ?? 0);
                            $monthlyAllocated = array_sum($bi['monthly_distribution'] ?? []);
                            $monthlyRemainder = $biTotal - $monthlyAllocated;
                            $selectedMonths = $bi['distribution_months'] ?? [];
                        @endphp
                        <tr wire:key="wp-{{ $wpIdx }}-bi-{{ $biIdx }}">
                            @php
                            $selectedCoa = $coaOptions->firstWhere('id', $bi['coa_id']);
                            $filteredCoas = $this->getCoaOptionsForIndex($wpIdx);

                            // Ensure selected COA is always on top for the dropdown list.
                            $filteredCoasOrdered = $filteredCoas;
                            if ($bi['coa_id'] ?? null) {
                            $filteredCoasOrdered = $filteredCoas
                            ->sortBy(fn($c) => ($c->id === $bi['coa_id']) ? 0 : 1)
                            ->values();
                            }

                            $searchLabel = '';
                            if ($selectedCoa) {
                            $searchLabel = $selectedCoa->code . ' — ' . $selectedCoa->title;
                            } elseif (!empty($bi['account_code']) || !empty($bi['description'])) {
                            $searchLabel = trim(($bi['account_code'] ?? '') . (!empty($bi['description']) ? ' — ' . ($bi['description'] ?? '') : ''));
                            }
                            @endphp
                            <td
                                wire:key="coa-cell-{{ $wpIdx }}-{{ $biIdx }}-{{ $bi['coa_id'] ?? 'none' }}-{{ md5($searchLabel) }}"
                                x-data="{
                                    open: false,
                                    search: @js($searchLabel),
                                    currentLabel: @js($searchLabel),
                                }"
                                x-effect="if (!open && search !== currentLabel) search = currentLabel"
                                :style="open ? 'position: relative; z-index: 1060;' : ''"
                                @click.outside="open = false; $dispatch('coa-dropdown-close')">
                                <div class="position-relative">
                                    <div class="input-group input-group-sm">
                                        <input
                                            type="text"
                                            class="form-control form-control-sm @error('workPlans.'.$wpIdx.'.budget_items.'.$biIdx.'.coa_id') is-invalid @enderror"
                                            placeholder="Cari akun/belanja..."
                                            x-model="search"
                                            @focus="open = true; $dispatch('coa-dropdown-open')"
                                            @input="open = true; $dispatch('coa-dropdown-open')"
                                            autocomplete="off">
                                        @if($bi['coa_id'])
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                            wire:click="$set('workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.coa_id', null)"
                                            @click="search = ''; currentLabel = ''; open = false; $dispatch('coa-dropdown-close')"
                                            title="Hapus pilihan">
                                            <i class="bx bx-x"></i>
                                        </button>
                                        @endif
                                    </div>
                                    @error('workPlans.'.$wpIdx.'.budget_items.'.$biIdx.'.coa_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror

                                    <select
                                        wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.coa_id"
                                        class="d-none">
                                        <option value=""></option>
                                        @foreach($filteredCoasOrdered as $coa)
                                        <option value="{{ $coa->id }}">{{ $coa->code }} — {{ $coa->title }}</option>
                                        @endforeach
                                    </select>

                                    <div
                                        x-show="open"
                                        x-cloak
                                        class="position-absolute bg-white border rounded shadow-sm w-100 mt-1"
                                        style="z-index: 1050; max-height: 220px; overflow-y: auto;">
                                        @forelse($filteredCoasOrdered as $coa)
                                        <div
                                            class="px-3 py-2 cursor-pointer dropdown-item small {{ ($bi['coa_id'] ?? null) == $coa->id ? 'bg-primary text-white' : '' }}"
                                            x-show="'{{ strtolower($coa->code . ' ' . $coa->title) }}'.includes(search.toLowerCase())"
                                            @click="
                                                    $wire.set('workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.coa_id', {{ $coa->id }});
                                                    currentLabel = '{{ $coa->code }} — {{ $coa->title }}';
                                                    search = currentLabel;
                                                    open = false;
                                                    $dispatch('coa-dropdown-close');
                                                ">
                                            <span class="fw-semibold text-primary">{{ $coa->code }}</span>
                                            <span class="ms-1">{{ $coa->title }}</span>
                                        </div>
                                        @empty
                                        <div class="px-3 py-2 text-muted small">Tidak ada data COA.</div>
                                        @endforelse
                                    </div>
                                </div>
                                <input type="text" class="form-control form-control-sm mt-2" wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.remarks" placeholder="Detail Belanja / Ket...">
                            </td>
                            <td><input type="text" class="form-control form-control-sm" wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.unit" placeholder="Bh, Paket"></td>
                            <td>
                                <input type="number" class="form-control form-control-sm @error('workPlans.'.$wpIdx.'.budget_items.'.$biIdx.'.quantity') is-invalid @enderror"
                                    wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.quantity" min="1">
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm @error('workPlans.'.$wpIdx.'.budget_items.'.$biIdx.'.unit_price') is-invalid @enderror"
                                    wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.unit_price" min="0" step="1000">
                            </td>
                            <td class="text-end text-nowrap fw-semibold text-primary">
                                Rp {{ number_format($biTotal, 0, ',', '.') }}
                            </td>
                            <td>
                                @if(count($wp['budget_items']) > 1)
                                <button type="button" wire:click="removeBudgetItem({{ $wpIdx }}, {{ $biIdx }})" class="btn btn-sm btn-icon btn-text-danger rounded-pill">
                                    <i class="bx bx-minus-circle"></i>
                                </button>
                                @endif
                            </td>
                        </tr>

                        {{-- Monthly Distribution Panel --}}
                        <tr wire:key="wp-{{ $wpIdx }}-bi-{{ $biIdx }}-monthly">
                            <td colspan="6" class="p-0 border-top-0">
                                <div x-data="{ expanded: {{ !empty($selectedMonths) ? 'true' : 'false' }} }" class="bg-light">
                                    {{-- Toggle bar --}}
                                    <div class="d-flex align-items-center justify-content-between px-3 py-2 cursor-pointer border-bottom"
                                         @click="expanded = !expanded"
                                         style="user-select: none;">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bx bx-calendar text-primary"></i>
                                            <span class="small fw-semibold text-primary">Distribusi Bulanan</span>
                                            @if(!empty($selectedMonths))
                                                <span class="badge bg-label-primary rounded-pill">{{ count($selectedMonths) }} bulan</span>
                                            @endif
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            @if($biTotal > 0)
                                                @if(abs($monthlyRemainder) < 0.01 && !empty($selectedMonths))
                                                    <span class="badge bg-success rounded-pill small"><i class="bx bx-check me-1"></i>Lengkap</span>
                                                @elseif($monthlyRemainder < 0)
                                                    <span class="badge bg-danger rounded-pill small">Lebih Rp {{ number_format(abs($monthlyRemainder), 0, ',', '.') }}</span>
                                                @elseif(!empty($selectedMonths))
                                                    <span class="badge bg-warning rounded-pill small">Sisa Rp {{ number_format($monthlyRemainder, 0, ',', '.') }}</span>
                                                @endif
                                            @endif
                                            <i class="bx" :class="expanded ? 'bx-chevron-up' : 'bx-chevron-down'"></i>
                                        </div>
                                    </div>

                                    {{-- Expandable content --}}
                                    <div x-show="expanded" x-collapse>
                                        <div class="px-3 py-3">
                                            @error('workPlans.'.$wpIdx.'.budget_items.'.$biIdx.'.monthly')
                                            <div class="alert alert-danger small py-2 mb-3">
                                                <i class="bx bx-error-circle me-1"></i>{{ $message }}
                                            </div>
                                            @enderror

                                            {{-- Month toggles --}}
                                            <div class="mb-3">
                                                <label class="form-label small text-muted mb-2">Pilih Bulan:</label>
                                                <div class="d-flex flex-wrap gap-1">
                                                    @foreach($monthLabels as $monthNum => $monthLabel)
                                                    <button type="button"
                                                        wire:click="toggleMonth({{ $wpIdx }}, {{ $biIdx }}, {{ $monthNum }})"
                                                        class="btn btn-sm {{ in_array($monthNum, $selectedMonths) ? 'btn-primary' : 'btn-outline-secondary' }}"
                                                        style="min-width: 52px; font-size: 0.75rem; padding: 0.2rem 0.4rem;">
                                                        {{ $monthLabel }}
                                                    </button>
                                                    @endforeach
                                                </div>
                                            </div>

                                            @if(!empty($selectedMonths))
                                            {{-- Action buttons --}}
                                            <div class="mb-3">
                                                <button type="button"
                                                    wire:click="distributeEvenly({{ $wpIdx }}, {{ $biIdx }})"
                                                    class="btn btn-sm btn-outline-primary"
                                                    @if($biTotal <= 0) disabled @endif>
                                                    <i class="bx bx-equalizer me-1"></i> Bagi Rata
                                                </button>
                                            </div>

                                            {{-- Monthly amount inputs --}}
                                            <div class="row g-2">
                                                @foreach($monthLabels as $monthNum => $monthLabel)
                                                    @if(in_array($monthNum, $selectedMonths))
                                                    <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                                        <label class="form-label small text-muted mb-1">{{ $monthLabel }}</label>
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text" style="font-size: 0.7rem; padding: 0.15rem 0.4rem;">Rp</span>
                                                            <input type="number"
                                                                class="form-control form-control-sm"
                                                                wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.monthly_distribution.{{ $monthNum }}"
                                                                min="0"
                                                                step="1000"
                                                                placeholder="0"
                                                                style="font-size: 0.8rem;">
                                                        </div>
                                                    </div>
                                                    @endif
                                                @endforeach
                                            </div>

                                            {{-- Remaining indicator --}}
                                            <div class="mt-3 d-flex justify-content-between align-items-center small border-top pt-2">
                                                <span class="text-muted">
                                                    Total Item: <strong>Rp {{ number_format($biTotal, 0, ',', '.') }}</strong>
                                                </span>
                                                <span class="text-muted">
                                                    Teralokasi: <strong>Rp {{ number_format($monthlyAllocated, 0, ',', '.') }}</strong>
                                                </span>
                                                <span class="{{ abs($monthlyRemainder) < 0.01 ? 'text-success' : ($monthlyRemainder < 0 ? 'text-danger' : 'text-warning') }} fw-semibold">
                                                    Sisa: Rp {{ number_format($monthlyRemainder, 0, ',', '.') }}
                                                </span>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" wire:click="addBudgetItem({{ $wpIdx }})" class="btn btn-sm btn-label-secondary">
                <i class="bx bx-plus me-1"></i> Tambah Item Belanja
            </button>
        </div>
    </div>
    @endforeach

    <div class="d-flex gap-2 mb-4">
        <button type="button" wire:click="addWorkPlan()" class="btn btn-label-primary">
            <i class="bx bx-plus me-1"></i> Tambah Program Kerja
        </button>
    </div>

    {{-- Action Buttons --}}
    <div class="card">
        <div class="card-body d-flex justify-content-between align-items-center">
            <a href="{{ route('rkap-submissions') }}" class="btn btn-label-secondary">
                <i class="bx bx-arrow-back me-1"></i> Kembali
            </a>
            <div class="d-flex gap-2">
                <button wire:click="saveDraft()" wire:loading.attr="disabled" class="btn btn-label-primary">
                    <span wire:loading.remove wire:target="saveDraft"><i class="bx bx-save me-1"></i> Simpan Draft</span>
                    <span wire:loading wire:target="saveDraft"><span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...</span>
                </button>
                <button wire:click="submitForReview()" wire:loading.attr="disabled"
                    wire:confirm="Yakin mengajukan RKAP ini untuk review? Pastikan data sudah lengkap."
                    class="btn btn-primary">
                    <span wire:loading.remove wire:target="submitForReview"><i class="bx bx-send me-1"></i> Ajukan untuk Review</span>
                    <span wire:loading wire:target="submitForReview"><span class="spinner-border spinner-border-sm me-1"></span> Mengajukan...</span>
                </button>
            </div>
        </div>
    </div>
</div>