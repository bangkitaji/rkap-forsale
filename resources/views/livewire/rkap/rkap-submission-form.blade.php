<div x-data="{ isDirty: false, isSubmitting: false }"
    @input="isDirty = true"
    @change="isDirty = true"
    @form-saved.window="isDirty = false"
    @beforeunload.window="if(isDirty && !isSubmitting) { $event.returnValue = 'Ada perubahan yang belum disimpan.'; return 'Ada perubahan yang belum disimpan.'; }">
    <style>
        .bg-group-alt {
            background-color: #fdeff2 !important;
        }

        .bg-group-alt td {
            background-color: inherit !important;
        }

        .table-group-header {
            font-weight: 600;
        }
    </style>
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

            @if(!empty($wp['work_plan_id']) && !empty($wp['activity_id']))
            {{-- Budget Items — wrapped in Alpine for modal state --}}
            <div
                x-data="{
                    dropdownOpen: false,
                    modalKey: null,
                    openModal(key) { this.modalKey = key; },
                    closeModal() { this.modalKey = null; }
                }"
                @coa-dropdown-open.window="dropdownOpen = true"
                @coa-dropdown-close.window="dropdownOpen = false"
                @keydown.escape.window="closeModal()">

                {{-- Table --}}
                <div class="table-responsive" :style="dropdownOpen ? 'overflow: visible;' : ''">
                    <table class="table table-sm table-bordered align-middle mb-2">
                        <thead class="table-primary text-white fw-semibold">
                            <tr>
                                <th style="width:30%">Uraian & Detail Belanja <span class="text-warning">*</span></th>
                                <th style="width:12%">Satuan</th>
                                <th style="width:8%" class="text-center">Vol <span class="text-warning">*</span></th>
                                <th style="width:140px" class="text-end">Harga Satuan (Rp) <span class="text-warning">*</span></th>
                                <th style="width:160px" class="text-end"></i>Total (Rp)</th>
                                <th style="width:120px" class="text-center">Detail</th>
                                <th style="width:2%" class="text-center"><i class="bx bx-menu"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                            $groups = [];
                            $currentGroup = null;
                            foreach ($wp['budget_items'] as $biIdx => $bi) {
                            $coaId = $bi['coa_id'] ?? null;
                            if ($coaId !== null && $currentGroup !== null && $currentGroup['coa_id'] === $coaId) {
                            $currentGroup['items'][] = ['index' => $biIdx, 'item' => $bi];
                            } else {
                            if ($currentGroup !== null) {
                            $groups[] = $currentGroup;
                            }
                            $currentGroup = [
                            'coa_id' => $coaId,
                            'items' => [
                            ['index' => $biIdx, 'item' => $bi]
                            ]
                            ];
                            }
                            }
                            if ($currentGroup !== null) {
                            $groups[] = $currentGroup;
                            }
                            @endphp

                            @foreach($groups as $gIdx => $group)
                            @php
                            $itemCount = count($group['items']);
                            $firstIdx = $group['items'][0]['index'];
                            $firstBi = $group['items'][0]['item'];
                            $selectedCoa = $coaOptions->firstWhere('id', $firstBi['coa_id']);
                            $filteredCoas = $this->getCoaOptionsForIndex($wpIdx);
                            $filteredCoasOrdered = $filteredCoas;
                            if ($firstBi['coa_id'] ?? null) {
                            $filteredCoasOrdered = $filteredCoas->sortBy(fn($c) => ($c->id === $firstBi['coa_id']) ? 0 : 1)->values();
                            }
                            $searchLabel = '';
                            if ($selectedCoa) {
                            $searchLabel = $selectedCoa->code . ' — ' . $selectedCoa->title;
                            } elseif (!empty($firstBi['account_code']) || !empty($firstBi['description'])) {
                            $searchLabel = trim(($firstBi['account_code'] ?? '') . (!empty($firstBi['description']) ? ' — ' . ($firstBi['description'] ?? '') : ''));
                            }
                            @endphp

                            <tr wire:key="wp-{{ $wpIdx }}-group-{{ $gIdx }}-coa" class="{{ $gIdx % 2 == 1 ? 'bg-group-alt' : '' }}">
                                <td colspan="6"
                                    wire:key="coa-cell-{{ $wpIdx }}-g{{ $gIdx }}-{{ $firstBi['coa_id'] ?? 'none' }}-{{ md5($searchLabel) }}"
                                    x-data="{
                                            open: false,
                                            search: @js($searchLabel),
                                            currentLabel: @js($searchLabel),
                                        }"
                                    x-effect="if (!open && search !== currentLabel) search = currentLabel"
                                    :style="open ? 'position: relative; z-index: 1060;' : ''"
                                    @click.outside="open = false; $dispatch('coa-dropdown-close')"
                                    class="border-bottom-0">
                                    @php
                                        $groupSubtotal = collect($group['items'])->sum(fn($info) =>
                                            ($info['item']['quantity'] ?? 0) * ($info['item']['unit_price'] ?? 0)
                                        );
                                        // Previous period lookup
                                        $prevWpId  = $wp['work_plan_id'] ?? null;
                                        $prevCode  = $selectedCoa?->code ?? ($firstBi['account_code'] ?? null);
                                        $prevAmount = ($prevWpId && $prevCode && !empty($prevData['map'][$prevWpId][$prevCode]))
                                            ? $prevData['map'][$prevWpId][$prevCode]
                                            : null;
                                        $prevPeriod = $prevData['period'] ?? null;
                                    @endphp
                                    <div class="position-relative">
                                        <div class="input-group input-group-sm">
                                            <input
                                                type="text"
                                                class="form-control form-control-sm @error('workPlans.'.$wpIdx.'.budget_items.'.$firstIdx.'.coa_id') is-invalid @enderror"
                                                placeholder="Cari akun/belanja..."
                                                x-model="search"
                                                @focus="open = true; $dispatch('coa-dropdown-open')"
                                                @input="open = true; $dispatch('coa-dropdown-open')"
                                                autocomplete="off">
                                            @if($firstBi['coa_id'])
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                wire:click="updateGroupCoa({{ $wpIdx }}, {{ $firstIdx }}, null)"
                                                @click="search = ''; currentLabel = ''; open = false; $dispatch('coa-dropdown-close'); isDirty = true;"
                                                title="Hapus pilihan">
                                                <i class="bx bx-x"></i>
                                            </button>
                                            @endif
                                            {{-- COA Group Subtotal --}}
                                            <span class="input-group-text px-2 fw-semibold text-nowrap"
                                                  style="font-size:0.78rem; background:#f0f4ff; border-color:#c9d4f5; color:#2563eb;"
                                                  title="Sub-total akun ini">
                                                <i class="bx bx-sum me-1" style="font-size:0.85rem;"></i>
                                                Rp {{ number_format($groupSubtotal, 0, ',', '.') }}
                                            </span>
                                            {{-- Previous Period Amount --}}
                                            @if($prevAmount !== null && $prevPeriod)
                                            <span class="input-group-text px-2 text-nowrap"
                                                  style="font-size:0.72rem; background:#fffbeb; border-color:#fcd34d; color:#92400e;"
                                                  title="Realisasi periode sebelumnya: {{ $prevPeriod }}">
                                                <i class="bx bx-history me-1" style="font-size:0.8rem;"></i>
                                                {{ $prevPeriod }}: Rp {{ number_format($prevAmount, 0, ',', '.') }}
                                            </span>
                                            @endif
                                        </div>
                                        @error('workPlans.'.$wpIdx.'.budget_items.'.$firstIdx.'.coa_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <select
                                            wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $firstIdx }}.coa_id"
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
                                                class="px-3 py-2 cursor-pointer dropdown-item small {{ ($firstBi['coa_id'] ?? null) == $coa->id ? 'bg-primary text-white' : '' }}"
                                                x-show="'{{ strtolower($coa->code . ' ' . $coa->title) }}'.includes(search.toLowerCase())"
                                                @click="
                                                        $wire.call('updateGroupCoa', {{ $wpIdx }}, {{ $firstIdx }}, {{ $coa->id }});
                                                        currentLabel = '{{ $coa->code }} — {{ $coa->title }}';
                                                        search = currentLabel;
                                                        open = false;
                                                        $dispatch('coa-dropdown-close');
                                                        isDirty = true;
                                                    ">
                                                <span class="fw-semibold text-primary">{{ $coa->code }}</span>
                                                <span class="ms-1">{{ $coa->title }}</span>
                                            </div>
                                            @empty
                                            <div class="px-3 py-2 text-muted small">Tidak ada data COA.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </td>

                                <td rowspan="{{ 1 + $itemCount }}" class="text-center align-middle border-bottom-0">
                                    @php
                                    $totalItemsCount = count($wp['budget_items']);
                                    $indices = array_column($group['items'], 'index');
                                    $indicesJson = json_encode($indices);
                                    @endphp
                                    <button type="button"
                                        wire:click="removeGroup({{ $wpIdx }}, {{ $indicesJson }})"
                                        @click="isDirty = true"
                                        class="btn btn-sm btn-icon btn-text-danger rounded-pill"
                                        title="Hapus grup akun belanja"
                                        @if($totalItemsCount <=$itemCount) disabled @endif>
                                        <i class="bx bx-minus-circle fs-4"></i>
                                    </button>
                                </td>
                            </tr>

                            @foreach($group['items'] as $itemIdx => $itemInfo)
                            @php
                            $biIdx = $itemInfo['index'];
                            $bi = $itemInfo['item'];
                            $biTotal = ($bi['quantity'] ?? 0) * ($bi['unit_price'] ?? 0);
                            $monthlyAllocated = array_sum($bi['monthly_distribution'] ?? []);
                            $monthlyRemainder = $biTotal - $monthlyAllocated;
                            $selectedMonths = $bi['distribution_months'] ?? [];
                            $cashOutAllocated = array_sum($bi['cash_out_distribution'] ?? []);
                            $cashOutRemainder = $biTotal - $cashOutAllocated;
                            $selectedCashOutMonths = $bi['cash_out_months'] ?? [];
                            $modalKey = 'wp' . $wpIdx . '-bi' . $biIdx;
                            @endphp
                            <tr wire:key="wp-{{ $wpIdx }}-bi-{{ $biIdx }}-detail" class="{{ $gIdx % 2 == 1 ? 'bg-group-alt' : '' }}">
                                <td class="border-top-0">
                                    <input type="text" class="form-control form-control-sm"
                                        wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.remarks"
                                        placeholder="Detail Belanja / Ket...">
                                </td>
                                <td class="border-top-0" style="position: relative;">
                                    <div
                                        x-data="{
                                            open: false,
                                            search: '{{ $bi['unit'] ?? '' }}',
                                        }"
                                        class="position-relative"
                                        @click.outside="open = false"
                                        wire:key="wp-{{ $wpIdx }}-bi-{{ $biIdx }}-unit-{{ $bi['unit'] ?? 'empty' }}">

                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            placeholder="Satuan..."
                                            x-model="search"
                                            @focus="open = true; $dispatch('coa-dropdown-open');"
                                            @input="open = true; $dispatch('coa-dropdown-open'); $wire.set('workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.unit', search);"
                                            autocomplete="off">

                                        <div
                                            x-show="open"
                                            x-cloak
                                            class="position-absolute bg-white border rounded shadow-sm w-100 mt-1"
                                            style="z-index: 1055; max-height: 180px; overflow-y: auto;">
                                            @foreach($this->satuanOptions as $satuanOpt)
                                            <div
                                                class="px-2 py-1_5 cursor-pointer dropdown-item small {{ ($bi['unit'] ?? '') == $satuanOpt->name ? 'bg-primary text-white' : '' }}"
                                                x-show="'{{ strtolower($satuanOpt->name) }}'.includes(search.toLowerCase())"
                                                @click="
                                                    $wire.set('workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.unit', '{{ $satuanOpt->name }}');
                                                    search = '{{ $satuanOpt->name }}';
                                                    open = false;
                                                    $dispatch('coa-dropdown-close');
                                                    isDirty = true;
                                                ">
                                                {{ $satuanOpt->name }}
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </td>
                                <td class="border-top-0">
                                    <input type="number"
                                        class="form-control form-control-sm @error('workPlans.'.$wpIdx.'.budget_items.'.$biIdx.'.quantity') is-invalid @enderror"
                                        wire:model.live.debounce.500ms="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.quantity"
                                        min="1">
                                </td>
                                <td class="border-top-0">
                                    <input type="number"
                                        class="form-control form-control-sm @error('workPlans.'.$wpIdx.'.budget_items.'.$biIdx.'.unit_price') is-invalid @enderror"
                                        wire:model.live.debounce.500ms="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.unit_price"
                                        min="0" step="1000"
                                        oninput="this.value = this.value.replace(/^0+(?=\d)/, '')"
                                        onblur="if (this.value === '' || this.value === null) { this.value = 0; this.dispatchEvent(new Event('input')); }">
                                </td>
                                <td class="text-end text-nowrap border-top-0">
                                    <div class="fw-semibold text-primary">Rp {{ number_format($biTotal, 0, ',', '.') }}</div>
                                </td>
                                <td class="text-center text-nowrap border-top-0">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <button type="button"
                                            class="btn btn-sm btn-icon btn-outline-primary"
                                            title="More Details"
                                            @click="openModal('{{ $modalKey }}')"
                                            @disabled($biTotal <=0)>
                                            <i class="bx bx-detail"></i>
                                        </button>

                                        @if($itemCount > 1)
                                        <button type="button"
                                            wire:click="removeBudgetItem({{ $wpIdx }}, {{ $biIdx }})"
                                            @click="isDirty = true"
                                            class="btn btn-sm btn-icon btn-outline-danger"
                                            title="Hapus detail rincian ini">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                        @endif

                                        @if($itemIdx === $itemCount - 1)
                                        <button type="button"
                                            wire:click="duplicateBudgetItem({{ $wpIdx }}, {{ $biIdx }})"
                                            @click="isDirty = true"
                                            class="btn btn-sm btn-icon btn-outline-success"
                                            title="Tambah detail rincian untuk akun ini">
                                            <i class="bx bx-plus"></i>
                                        </button>
                                        @endif
                                    </div>

                                    @if(!empty($selectedMonths) || !empty($selectedCashOutMonths))
                                    <div class="mt-1">
                                        @if(!empty($selectedMonths))
                                        @if(abs($monthlyRemainder) < 0.01)
                                            <span class="badge bg-success rounded-pill" style="font-size:0.6rem;"><i class="bx bx-check"></i> Dist</span>
                                            @else
                                            <span class="badge bg-warning rounded-pill" style="font-size:0.6rem;">Dist</span>
                                            @endif
                                            @endif
                                            @if(!empty($selectedCashOutMonths))
                                            @if(abs($cashOutRemainder) < 0.01)
                                                <span class="badge bg-success rounded-pill" style="font-size:0.6rem;"><i class="bx bx-check"></i> Kas</span>
                                                @else
                                                <span class="badge bg-warning rounded-pill" style="font-size:0.6rem;">Kas</span>
                                                @endif
                                                @endif
                                    </div>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>{{-- end .table-responsive --}}

                {{-- MODALS — outside the overflow container --}}
                @foreach($wp['budget_items'] as $biIdx => $bi)
                @php
                $biTotal = ($bi['quantity'] ?? 0) * ($bi['unit_price'] ?? 0);
                $monthlyAllocated = array_sum($bi['monthly_distribution'] ?? []);
                $monthlyRemainder = $biTotal - $monthlyAllocated;
                $selectedMonths = $bi['distribution_months'] ?? [];
                $cashOutAllocated = array_sum($bi['cash_out_distribution'] ?? []);
                $cashOutRemainder = $biTotal - $cashOutAllocated;
                $selectedCashOutMonths = $bi['cash_out_months'] ?? [];
                $allMonths = array_unique(array_merge($selectedMonths, $selectedCashOutMonths));
                sort($allMonths);
                $modalKey = 'wp' . $wpIdx . '-bi' . $biIdx;
                @endphp
                <div
                    wire:key="modal-{{ $wpIdx }}-{{ $biIdx }}"
                    x-show="modalKey === '{{ $modalKey }}'"
                    x-cloak
                    class="position-fixed top-0 start-0 w-100 h-100 overflow-y-auto py-3 px-2"
                    :class="modalKey === '{{ $modalKey }}' ? 'd-flex align-items-start justify-content-center' : 'd-none'"
                    style="z-index: 1080; background: rgba(0,0,0,0.5);">
                    <div
                        x-show="modalKey === '{{ $modalKey }}'"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-4"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-4"
                        class="bg-white rounded-3 shadow-lg"
                        style="width: 740px; max-width: 96vw; max-height: calc(100vh - 3rem); display: flex; flex-direction: column;"
                        @click.stop>
                        {{-- Modal Header --}}
                        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom flex-shrink-0">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bx bx-detail text-primary fs-5"></i>
                                <h6 class="mb-0 fw-bold">Detail Pengajuan</h6>
                                <span class="badge bg-label-secondary rounded-pill small">Item {{ $biIdx + 1 }}</span>
                            </div>
                            <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" @click="closeModal()">
                                <i class="bx bx-x fs-5"></i>
                            </button>
                        </div>
                        {{-- Modal Body --}}
                        <div class="px-4 py-3" style="overflow-y: auto; max-height: calc(100vh - 12rem);">
                            <div class="alert alert-primary d-flex justify-content-between align-items-center py-2 mb-4">
                                <span class="small fw-semibold">Total Item</span>
                                <span class="fw-bold fs-6">Rp {{ number_format($biTotal, 0, ',', '.') }}</span>
                            </div>
                            {{-- Distribusi Beban --}}
                            <div class="mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bx bx-calendar text-primary"></i>
                                        <span class="fw-semibold text-primary small">Distribusi Bulanan</span>
                                        @if(!empty($selectedMonths))
                                        <span class="badge bg-label-primary rounded-pill">{{ count($selectedMonths) }} bulan</span>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        @if($biTotal > 0)
                                        @if(abs($monthlyRemainder) < 0.01 && !empty($selectedMonths))
                                            <span class="badge bg-success rounded-pill small"><i class="bx bx-check me-1"></i>Lengkap</span>
                                            @elseif($monthlyRemainder < 0)
                                                <span class="badge bg-danger rounded-pill small">Lebih Rp {{ number_format(abs($monthlyRemainder), 0, ',', '.') }}</span>
                                                @elseif(!empty($selectedMonths))
                                                <span class="badge bg-warning rounded-pill small">Sisa Rp {{ number_format($monthlyRemainder, 0, ',', '.') }}</span>
                                                @endif
                                                @endif
                                                <button type="button"
                                                    wire:click="distributeEvenly({{ $wpIdx }}, {{ $biIdx }})"
                                                    class="btn btn-xs btn-outline-primary py-0 px-2"
                                                    style="font-size:0.72rem;"
                                                    @if($biTotal <=0) disabled @endif>
                                                    <i class="bx bx-equalizer me-1"></i>Bagi Rata
                                                </button>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="form-label small text-muted mb-0">Pilih Bulan Distribusi Beban:</label>
                                    <button type="button"
                                        wire:click="selectAllMonths({{ $wpIdx }}, {{ $biIdx }})"
                                        class="btn btn-xs btn-link p-0 text-decoration-none"
                                        style="font-size: 0.72rem;">
                                        {{ count($selectedMonths) === 12 ? 'Deselect All' : 'Select All' }}
                                    </button>
                                </div>
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    @foreach($monthLabels as $monthNum => $monthLabel)
                                    <button type="button"
                                        wire:click="toggleMonth({{ $wpIdx }}, {{ $biIdx }}, {{ $monthNum }})"
                                        class="btn btn-sm {{ in_array($monthNum, $selectedMonths) ? 'btn-primary' : 'btn-outline-secondary' }}"
                                        style="min-width: 52px; font-size: 0.75rem; padding: 0.2rem 0.4rem;">
                                        {{ $monthLabel }}
                                    </button>
                                    @endforeach
                                </div>
                                @error('workPlans.'.$wpIdx.'.budget_items.'.$biIdx.'.monthly')
                                <div class="alert alert-danger small py-2 mb-2"><i class="bx bx-error-circle me-1"></i>{{ $message }}</div>
                                @enderror
                            </div>
                            {{-- Rencana Kas Keluar --}}
                            <div class="mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bx bx-wallet text-primary"></i>
                                        <span class="fw-semibold text-primary small">Rencana Kas Keluar</span>
                                        @if(!empty($selectedCashOutMonths))
                                        <span class="badge bg-label-primary rounded-pill">{{ count($selectedCashOutMonths) }} bulan</span>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        @if($biTotal > 0)
                                        @if(abs($cashOutRemainder) < 0.01 && !empty($selectedCashOutMonths))
                                            <span class="badge bg-success rounded-pill small"><i class="bx bx-check me-1"></i>Lengkap</span>
                                            @elseif($cashOutRemainder < 0)
                                                <span class="badge bg-danger rounded-pill small">Lebih Rp {{ number_format(abs($cashOutRemainder), 0, ',', '.') }}</span>
                                                @elseif(!empty($selectedCashOutMonths))
                                                <span class="badge bg-warning rounded-pill small">Sisa Rp {{ number_format($cashOutRemainder, 0, ',', '.') }}</span>
                                                @endif
                                                @endif
                                                <button type="button"
                                                    wire:click="distributeCashOutEvenly({{ $wpIdx }}, {{ $biIdx }})"
                                                    class="btn btn-xs btn-outline-primary py-0 px-2"
                                                    style="font-size:0.72rem;"
                                                    @if($biTotal <=0) disabled @endif>
                                                    <i class="bx bx-equalizer me-1"></i>Bagi Rata
                                                </button>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="form-label small text-muted mb-0">Pilih Bulan Pembayaran:</label>
                                    <button type="button"
                                        wire:click="selectAllCashOutMonths({{ $wpIdx }}, {{ $biIdx }})"
                                        class="btn btn-xs btn-link p-0 text-decoration-none"
                                        style="font-size: 0.72rem;">
                                        {{ count($selectedCashOutMonths) === 12 ? 'Deselect All' : 'Select All' }}
                                    </button>
                                </div>
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    @foreach($monthLabels as $monthNum => $monthLabel)
                                    <button type="button"
                                        wire:click="toggleCashOutMonth({{ $wpIdx }}, {{ $biIdx }}, {{ $monthNum }})"
                                        class="btn btn-sm {{ in_array($monthNum, $selectedCashOutMonths) ? 'btn-primary' : 'btn-outline-secondary' }}"
                                        style="min-width: 52px; font-size: 0.75rem; padding: 0.2rem 0.4rem;">
                                        {{ $monthLabel }}
                                    </button>
                                    @endforeach
                                </div>
                                @error('workPlans.'.$wpIdx.'.budget_items.'.$biIdx.'.cash_out')
                                <div class="alert alert-danger small py-2 mb-2"><i class="bx bx-error-circle me-1"></i>{{ $message }}</div>
                                @enderror
                            </div>
                            {{-- 3-column summary table --}}
                            @if(!empty($allMonths))
                            <div class="border rounded-2 table-responsive">
                                <table class="table table-sm table-bordered mb-0" style="min-width: 600px;">
                                    <thead class="table-primary">
                                        <tr>
                                            <th class="text-center" style="width:110px;">Bulan</th>
                                            <th class="text-end">Distribusi Beban (Rp)</th>
                                            <th class="text-end">Rencana Kas Keluar (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($monthLabels as $monthNum => $monthLabel)
                                        @php
                                        $isDistribMonth = in_array($monthNum, $selectedMonths);
                                        $isCashOutMonth = in_array($monthNum, $selectedCashOutMonths);
                                        @endphp
                                        @if($isDistribMonth || $isCashOutMonth)
                                        <tr>
                                            <td class="text-center fw-semibold small">{{ $monthLabel }}</td>
                                            <td class="text-end">
                                                @if($isDistribMonth)
                                                <div class="input-group input-group-sm justify-content-end">
                                                    <span class="input-group-text" style="font-size:0.7rem;padding:0.15rem 0.4rem;">Rp</span>
                                                    <input type="number"
                                                        class="form-control form-control-sm text-end"
                                                        wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.monthly_distribution.{{ $monthNum }}"
                                                        min="0" step="1000" placeholder="0"
                                                        style="font-size:0.8rem;max-width:180px;">
                                                </div>
                                                @else
                                                <span class="text-muted small">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                @if($isCashOutMonth)
                                                <div class="input-group input-group-sm justify-content-end">
                                                    <span class="input-group-text" style="font-size:0.7rem;padding:0.15rem 0.4rem;">Rp</span>
                                                    <input type="number"
                                                        class="form-control form-control-sm text-end"
                                                        wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.cash_out_distribution.{{ $monthNum }}"
                                                        min="0" step="1000" placeholder="0"
                                                        style="font-size:0.8rem;max-width:180px;">
                                                </div>
                                                @else
                                                <span class="text-muted small">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endif
                                        @endforeach
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th class="text-center small">Total</th>
                                            <th class="text-end small {{ abs($monthlyRemainder) < 0.01 ? 'text-success' : ($monthlyRemainder < 0 ? 'text-danger' : 'text-warning') }}">
                                                Rp {{ number_format($monthlyAllocated, 0, ',', '.') }}
                                            </th>
                                            <th class="text-end small {{ abs($cashOutRemainder) < 0.01 ? 'text-success' : ($cashOutRemainder < 0 ? 'text-danger' : 'text-warning') }}">
                                                Rp {{ number_format($cashOutAllocated, 0, ',', '.') }}
                                            </th>
                                        </tr>
                                        <tr>
                                            <th class="text-center small text-muted">Sisa</th>
                                            <th class="text-end small {{ abs($monthlyRemainder) < 0.01 ? 'text-success' : ($monthlyRemainder < 0 ? 'text-danger' : 'text-warning') }}">
                                                Rp {{ number_format($monthlyRemainder, 0, ',', '.') }}
                                            </th>
                                            <th class="text-end small {{ abs($cashOutRemainder) < 0.01 ? 'text-success' : ($cashOutRemainder < 0 ? 'text-danger' : 'text-warning') }}">
                                                Rp {{ number_format($cashOutRemainder, 0, ',', '.') }}
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            @else
                            <div class="text-center text-muted small py-3 border rounded-2">
                                <i class="bx bx-info-circle me-1"></i>Belum ada bulan yang dipilih. Pilih bulan di atas untuk memulai distribusi.
                            </div>
                            @endif
                        </div>{{-- end modal body --}}
                        {{-- Modal Footer --}}
                        <div class="px-4 py-3 border-top d-flex justify-content-end flex-shrink-0">
                            <button type="button" class="btn btn-primary" @click="closeModal()">
                                <i class="bx bx-check me-1"></i>Selesai
                            </button>
                        </div>
                    </div>
                </div>{{-- end modal overlay --}}
                @endforeach

                <button type="button" wire:click="addBudgetItem({{ $wpIdx }})" class="btn btn-sm btn-label-secondary mt-2">
                    <i class="bx bx-plus me-1"></i> Tambah Item Belanja
                </button>

            </div>{{-- end Alpine x-data budget section --}}
            @else
            <div class="alert alert-info d-flex align-items-center mb-0 mt-3">
                <i class="bx bx-info-circle me-2 fs-4"></i>
                <div>
                    Silakan pilih <strong>Program Kerja</strong> dan <strong>Nama Kegiatan</strong> terlebih dahulu untuk mengisi detail anggaran belanja.
                </div>
            </div>
            @endif
        </div>{{-- end card-body --}}
    </div>{{-- end card --}}
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
                <button wire:click="saveDraft()" @click="isSubmitting = true" wire:loading.attr="disabled" class="btn btn-label-primary">
                    <span wire:loading.remove wire:target="saveDraft"><i class="bx bx-save me-1"></i> Simpan Draft</span>
                    <span wire:loading wire:target="saveDraft"><span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...</span>
                </button>
                <button wire:click="submitForReview()" @click="isSubmitting = true" wire:loading.attr="disabled"
                    wire:confirm="Yakin mengajukan RKAP ini untuk review? Pastikan data sudah lengkap."
                    class="btn btn-primary">
                    <span wire:loading.remove wire:target="submitForReview"><i class="bx bx-send me-1"></i> Ajukan untuk Review</span>
                    <span wire:loading wire:target="submitForReview"><span class="spinner-border spinner-border-sm me-1"></span> Mengajukan...</span>
                </button>
            </div>
        </div>
    </div>
</div>