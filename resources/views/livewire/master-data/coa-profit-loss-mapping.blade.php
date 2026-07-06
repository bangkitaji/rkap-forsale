<div>
    <!-- Floating Auto-dismiss Toast Notification -->
    <div x-data="{ show: false, message: '', type: 'success' }"
        x-on:flash-message.window="message = $event.detail.message; type = $event.detail.type; show = true; setTimeout(() => show = false, 4000)"
        class="position-fixed top-0 end-0 p-3 rkap-z-9999">

        <div x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-[-20px]"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-[-20px]"
            class="toast align-items-center border-0 show shadow-lg"
            :class="type === 'success' ? 'bg-success text-white' : 'bg-danger text-white'"
            role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i :class="type === 'success' ? 'bx bx-check-circle fs-4' : 'bx bx-error-circle fs-4'"></i>
                    <span x-text="message"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" x-on:click="show = false" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Master Data /</span> Pemetaan Profit and Loss
    </h4>

    <!-- Statistics Quick Filters -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="card rkap-card-border-shadow-primary h-100 rkap-cursor-pointer rkap-hover-shadow"
                wire:click="$set('filterProfitLossGroup', '')">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2 pb-1">
                        <div class="avatar me-3">
                            <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-spreadsheet fs-4"></i></span>
                        </div>
                        <h4 class="ms-1 mb-0">{{ $stats['total'] }}</h4>
                    </div>
                    <p class="mb-0 text-muted small">Total Akun COA</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card rkap-card-border-shadow-success h-100 rkap-cursor-pointer rkap-hover-shadow"
                wire:click="$set('filterProfitLossGroup', 'mapped')">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2 pb-1">
                        <div class="avatar me-3">
                            <span class="avatar-initial rounded bg-label-success"><i class="bx bx-check-double fs-4"></i></span>
                        </div>
                        <h4 class="ms-1 mb-0">{{ $stats['mapped'] }}</h4>
                    </div>
                    <p class="mb-0 text-muted small">Sudah Dipetakan (P&L)</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card rkap-card-border-shadow-warning h-100 rkap-cursor-pointer rkap-hover-shadow"
                wire:click="$set('filterProfitLossGroup', 'unmapped')">
                <div class="card-body text-nowrap">
                    <div class="d-flex align-items-center mb-2 pb-1">
                        <div class="avatar me-3">
                            <span class="avatar-initial rounded bg-label-warning"><i class="bx bx-help-circle fs-4"></i></span>
                        </div>
                        <h4 class="ms-1 mb-0">{{ $stats['unmapped'] }}</h4>
                    </div>
                    <p class="mb-0 text-muted small">Belum Dipetakan (Butuh Perhatian)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="card">
        <div class="card-body">

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

            <!-- Filters & Actions Header -->
            <div class="row g-3 mb-4 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted">Cari COA</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Cari kode atau judul COA...">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Filter COA Group</label>
                    <select class="form-select form-select-sm" wire:model.live="filterCoaGroup">
                        <option value="">Semua Group</option>
                        @foreach($coaGroups as $g)
                        <option value="{{ $g->id }}">{{ $g->name }} ({{ $g->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <div class="w-100">
                        <label class="form-label small text-muted">Filter Kategori Profit & Loss</label>
                        <select class="form-select form-select-sm"
                            wire:key="filter-pl-group"
                            wire:model.live="filterProfitLossGroup">
                            <option value="">Semua Status / Kategori</option>
                            <option value="unmapped">Belum Dipetakan</option>
                            <option value="mapped">Sudah Dipetakan (Semua)</option>
                            @foreach(collect($coaCategories)->groupBy('group') as $groupName => $groupCats)
                            <optgroup label="{{ $groupName }}">
                                @foreach($groupCats as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->label }}</option>
                                @endforeach
                            </optgroup>
                            @endforeach
                        </select>
                    </div>
                    @if($search !== '' || $filterCoaGroup !== '' || $filterProfitLossGroup !== '')
                    <div class="align-self-end">
                        <button class="btn btn-sm btn-outline-danger" wire:click="resetFilters" title="Reset Filter">
                            <i class="bx bx-refresh fs-5"></i>
                        </button>
                    </div>
                    @endif
                </div>
                <div class="col-md-3 text-end d-flex justify-content-end gap-2">
                    <div class="d-flex align-items-center gap-1">
                        <span class="text-muted small me-1">Baris:</span>
                        <select class="form-select form-select-sm w-auto" wire:model.live="perPage">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions Bar -->
            @if(count($selectedCoas) > 0)
            <div class="alert alert-primary d-flex align-items-center justify-content-between p-3 mb-3 fade show shadow-sm" role="alert">
                <div class="d-flex align-items-center gap-2">
                    <i class="bx bx-check-square fs-4"></i>
                    <span>Terpilih <strong>{{ count($selectedCoas) }}</strong> akun COA. Pilih Kategori P&L untuk pemetaan massal:</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <select class="form-select form-select-sm w-auto bg-white"
                        x-data
                        @change="$wire.bulkMap($event.target.value); $event.target.value = '';">
                        <option value="">-- Pilih Kategori --</option>
                        <option value="__reset__">-- Hapus Pemetaan (Reset) --</option>
                        @foreach(collect($coaCategories)->groupBy('group') as $groupName => $groupCats)
                        <optgroup label="{{ $groupName }}">
                            @foreach($groupCats as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->label }}</option>
                            @endforeach
                        </optgroup>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-secondary" wire:click="$set('selectedCoas', []); $set('selectAll', false);">Batal</button>
                </div>
            </div>
            @endif

            <!-- Table -->
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th class="text-center rkap-v-align-middle rkap-w-5p">
                                <input class="form-check-input" type="checkbox" wire:model.live="selectAll">
                            </th>
                            <th wire:click="sort('code')" class="rkap-cursor-pointer rkap-user-select-none text-nowrap rkap-w-15p">
                                Kode COA
                                @if($sortBy === 'code')
                                <i class="bx bx-chevron-{{ $sortDir === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                @else
                                <i class="bx bx-sort ms-1 text-muted opacity-50"></i>
                                @endif
                            </th>
                            <th wire:click="sort('title')" class="rkap-cursor-pointer rkap-user-select-none text-nowrap rkap-w-25p">
                                Judul Akun COA
                                @if($sortBy === 'title')
                                <i class="bx bx-chevron-{{ $sortDir === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                @else
                                <i class="bx bx-sort ms-1 text-muted opacity-50"></i>
                                @endif
                            </th>
                            <th class="rkap-w-15p">COA Group</th>
                            <th class="rkap-w-20p">Kategori P&L Saat Ini</th>
                            <th class="rkap-w-20p">Ubah Pemetaan Profit & Loss</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($coas as $coa)
                        <tr wire:key="coa-row-{{ $coa->id }}" class="{{ in_array((string)$coa->id, $selectedCoas) ? 'table-primary bg-label-primary' : '' }} rkap-transition-15">
                            <td class="text-center rkap-v-align-middle">
                                <input class="form-check-input" type="checkbox" value="{{ $coa->id }}" wire:model.live="selectedCoas">
                            </td>
                            <td><strong>{{ $coa->code }}</strong></td>
                            <td class="text-wrap"><strong>{{ $coa->title }}</strong></td>
                            <td>
                                @if($coa->coaGroup)
                                <span class="badge bg-label-info fw-semibold">{{ $coa->coaGroup->name }}</span>
                                @else
                                <span class="badge bg-label-secondary text-muted">Belum Dipetakan</span>
                                @endif
                            </td>
                            <td>
                                @if($coa->coaCategory)
                                <span class="badge bg-label-{{ $coa->coaCategory->color }} fw-semibold">
                                    {{ $coa->coaCategory->label }}
                                </span>
                                @else
                                <span class="badge bg-label-secondary text-muted rkap-color-secondary-muted">Belum Dipetakan</span>
                                @endif
                            </td>
                            <td>
                                <select
                                    wire:key="select-coa-{{ $coa->id }}"
                                    wire:model.live="mappings.{{ $coa->id }}"
                                    class="form-select form-select-sm">
                                    <option value="">-- Belum Dipetakan --</option>
                                    @foreach(collect($coaCategories)->groupBy('group') as $groupName => $groupCats)
                                    <optgroup label="{{ $groupName }}">
                                        @foreach($groupCats as $cat)
                                        <option value="{{ $cat->id }}">
                                            {{ $cat->label }}
                                        </option>
                                        @endforeach
                                    </optgroup>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bx bx-spreadsheet fs-1 mb-2 d-block opacity-50"></i>
                                Tidak ada data COA ditemukan.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-4">
                {{ $coas->links() }}
            </div>

        </div>
    </div>
</div>