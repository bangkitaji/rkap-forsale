<div>
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Master Data /</span> Activity
    </h4>

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

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0">Activities</h5>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <select class="form-select form-select-sm w-auto" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <div class="input-group input-group-sm w-auto">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search activities...') }}">
                    </div>
                    {{-- Filter: Unmapped --}}
                    <div class="form-check form-switch mb-0 d-flex align-items-center gap-1">
                        <input class="form-check-input" type="checkbox" id="onlyUnmapped"
                            wire:model.live="onlyUnmapped" style="cursor:pointer">
                        <label class="form-check-label small fw-semibold text-nowrap" for="onlyUnmapped"
                            style="cursor:pointer">
                            <i class="bx bx-unlink me-1 text-warning"></i>Belum mapping COA
                        </label>
                    </div>
                    @can('masterdata.activity.manage')
                    <button wire:click="create()" class="btn btn-primary btn-sm">
                        <i class="bx bx-plus me-1"></i> Add Activity
                    </button>
                    <button wire:click="openUploadModal()" class="btn btn-info btn-sm">
                        <i class="bx bx-upload me-1"></i> Import Excel
                    </button>
                    @endcan
                </div>
            </div>

            @if($onlyUnmapped)
            <div class="alert alert-warning py-2 px-3 mb-3 d-flex align-items-center gap-2 small">
                <i class="bx bx-filter-alt flex-shrink-0"></i>
                Menampilkan activity yang <strong class="ms-1">{{ __('belum memiliki mapping COA') }}</strong>.
                <button wire:click="$set('onlyUnmapped', false)" class="btn btn-xs btn-link p-0 ms-2 text-warning">
                    Tampilkan semua
                </button>
            </div>
            @endif

            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th wire:click="sort('work_plan')" class="rkap-cursor-pointer rkap-user-select-none text-nowrap">
                                Work Plan
                                @if($sortBy === 'work_plan')
                                <i class="bx bx-chevron-{{ $sortDir === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                @else
                                <i class="bx bx-sort ms-1 text-muted opacity-50"></i>
                                @endif
                            </th>
                            <th wire:click="sort('code')" class="rkap-cursor-pointer rkap-user-select-none text-nowrap">
                                Activity
                                @if($sortBy === 'code')
                                <i class="bx bx-chevron-{{ $sortDir === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                @else
                                <i class="bx bx-sort ms-1 text-muted opacity-50"></i>
                                @endif
                            </th>
                            <th>{{ __('Mapped COAs') }}</th>
                            <th>Description</th>
                            @can('masterdata.activity.manage')
                            <th>Actions</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($activities as $activity)
                        <tr>
                            <td>
                                @if($activity->workPlan)
                                    <div class="fw-semibold">{{ $activity->workPlan->code }}</div>
                                    <div class="text-muted small rkap-ws-normal" style="max-width: 200px;">{{ $activity->workPlan->title }}</div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-bold">{{ $activity->code }}</div>
                                <div class="text-muted small rkap-ws-normal" style="max-width: 250px;">{{ $activity->title }}</div>
                            </td>
                            <td>
                                @if($activity->coas->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-1 rkap-ws-normal" style="max-width: 250px;">
                                        @foreach($activity->coas as $coa)
                                            <span class="badge bg-label-primary" title="{{ $coa->title }}">
                                                {{ $coa->code }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="badge bg-label-warning text-warning">
                                        <i class="bx bx-unlink me-1"></i>Belum dipetakan
                                    </span>
                                @endif
                            </td>
                            <td>{{ Str::limit($activity->description, 50) }}</td>
                            @can('masterdata.activity.manage')
                            <td>
                                {{-- Map to COA button --}}
                                <button wire:click="openCoaMapping({{ $activity->id }})"
                                    class="btn btn-sm btn-icon btn-text-primary rounded-pill waves-effect"
                                    title="Mapping COA">
                                    <i class="bx bx-link-alt"></i>
                                </button>
                                <button wire:click="edit({{ $activity->id }})" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect" title="Edit">
                                    <i class="bx bx-edit-alt"></i>
                                </button>
                                <button wire:click="delete({{ $activity->id }})" wire:confirm="Are you sure you want to delete this activity?" class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect" title="Delete">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </td>
                            @endcan
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->can('masterdata.activity.manage') ? 5 : 4 }}" class="text-center">{{ __('No activities found.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $activities->links() }}
            </div>
        </div>
    </div>

    <!-- Add / Edit Activity Modal -->
    @if($isModalOpen)
    <div class="modal fade show rkap-modal-show" tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isEditMode ? 'Edit Activity' : 'Add New Activity' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal()"></button>
                </div>
                <form wire:submit.prevent="store">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="work_plan_id" class="form-label">Work Plan</label>
                            @php
                                $selectedWp = $work_plan_id
                                    ? $workPlans->firstWhere('id', $work_plan_id)
                                    : null;
                                $selectedWpLabel = $selectedWp
                                    ? $selectedWp->code . ' — ' . $selectedWp->title
                                    : '';
                            @endphp
                            <div x-data="{
                                    open: false,
                                    search: @js($selectedWpLabel),
                                    currentLabel: @js($selectedWpLabel),
                                }"
                                class="position-relative"
                                @click.outside="open = false"
                                x-effect="if (!open && search !== currentLabel) search = currentLabel">

                                <div class="input-group">
                                    <input type="text"
                                        id="work_plan_id_search"
                                        class="form-control @error('work_plan_id') is-invalid @enderror"
                                        placeholder="{{ __('Cari program kerja...') }}"
                                        x-model="search"
                                        @focus="open = true"
                                        @input="open = true"
                                        autocomplete="off">
                                    @if($work_plan_id)
                                    <button type="button" class="btn btn-outline-secondary"
                                        wire:click="$set('work_plan_id', null)"
                                        @click="search = ''; currentLabel = ''; open = false"
                                        title="{{ __('Hapus pilihan') }}">
                                        <i class="bx bx-x"></i>
                                    </button>
                                    @endif
                                </div>

                                @error('work_plan_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

                                {{-- Hidden select as Livewire binding --}}
                                <select wire:model="work_plan_id" id="work_plan_id" class="d-none">
                                    <option value=""></option>
                                    @if($work_plan_id)
                                        @if($selectedWp)
                                        <option value="{{ $selectedWp->id }}" selected>{{ $selectedWp->code }} — {{ $selectedWp->title }}</option>
                                        @endif
                                    @endif
                                </select>

                                {{-- Dropdown options --}}
                                <div x-show="open" x-cloak
                                    class="position-absolute bg-white border rounded shadow-sm w-100 mt-1"
                                    style="z-index: 1055; max-height: 240px; overflow-y: auto;">
                                    @forelse($workPlans as $wp)
                                    <div
                                        class="px-3 py-2 cursor-pointer dropdown-item small {{ $work_plan_id == $wp->id ? 'bg-primary text-white' : '' }}"
                                        x-show="search === '' || '{{ strtolower($wp->code . ' ' . $wp->title) }}'.includes(search.toLowerCase())"
                                        @click="
                                            $wire.set('work_plan_id', {{ $wp->id }});
                                            search = '{{ addslashes($wp->code . ' — ' . $wp->title) }}';
                                            currentLabel = search;
                                            open = false;
                                        ">
                                        <span class="fw-semibold text-primary">{{ $wp->code }}</span>
                                        <span class="ms-1 text-muted">{{ $wp->title }}</span>
                                    </div>
                                    @empty
                                    <div class="px-3 py-2 text-muted small">{{ __('Tidak ada data program kerja.') }}</div>
                                    @endforelse
                                    <div class="px-3 py-2 text-muted small border-top">
                                        <i class="bx bx-info-circle me-1"></i>Ketik untuk menyaring program kerja.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="code" class="form-label">Code</label>
                            <input type="text" id="code" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder="e.g. 2000000001" autofocus>
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if(!$isEditMode)
                            <div class="form-text text-muted">
                                <i class="bx bx-info-circle me-1"></i>Kode digenerate otomatis. Anda dapat mengubahnya jika diperlukan.
                            </div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" id="title" class="form-control @error('title') is-invalid @enderror" wire:model="title" placeholder="Activity Title">
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea id="description" class="form-control @error('description') is-invalid @enderror" wire:model="description" rows="3" placeholder="Activity Description"></textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" wire:click="closeModal()">Close</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- COA Mapping Modal -->
    @if($isCoaMappingOpen)
    @php
        $mappingActivity = $mappingActivityId ? $activities->firstWhere('id', $mappingActivityId) ?? \App\Models\Activity::with('coas','workPlan')->find($mappingActivityId) : null;
        $mappedCoaIds = array_flip(array_map('intval', $selectedCoaIds));
        $filteredCoas = $allCoas->filter(function($c) use ($coaSearch) {
            if (empty($coaSearch)) return true;
            $needle = strtolower($coaSearch);
            return str_contains(strtolower($c->code), $needle) || str_contains(strtolower($c->title), $needle);
        });
        $mappedCoasList   = $filteredCoas->filter(fn($c) => isset($mappedCoaIds[$c->id]));
        $unmappedCoasList = $filteredCoas->reject(fn($c) => isset($mappedCoaIds[$c->id]));
    @endphp
    <div class="modal fade show rkap-modal-show" tabindex="-1" aria-modal="true" role="dialog" style="overflow-y:auto">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" x-data="{ isDirty: false }" @change="isDirty = true">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0">
                            <i class="bx bx-link-alt me-2 text-primary"></i>Mapping COA
                        </h5>
                        @if($mappingActivity)
                        <div class="small text-muted mt-1">
                            <span class="fw-semibold text-body">{{ $mappingActivity->code }}</span>
                            — {{ Str::limit($mappingActivity->title, 60) }}
                            @if($mappingActivity->workPlan)
                            <span class="badge bg-label-secondary ms-1">{{ $mappingActivity->workPlan->code }}</span>
                            @endif
                        </div>
                        @endif
                    </div>
                    <button type="button" class="btn-close" wire:click="closeCoaMapping()"></button>
                </div>

                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    {{-- Search & stats bar --}}
                    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                        <div class="input-group input-group-sm flex-grow-1" style="min-width:200px">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bx bx-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-0"
                                wire:model.live.debounce.200ms="coaSearch"
                                placeholder="{{ __('Cari COA (kode / judul)...') }}"
                                autocomplete="off">
                            @if(!empty($coaSearch))
                            <button class="btn btn-outline-secondary border" wire:click="$set('coaSearch','')" type="button">
                                <i class="bx bx-x"></i>
                            </button>
                            @endif
                        </div>
                        <span class="badge bg-success rounded-pill">{{ count($selectedCoaIds) }} dipilih</span>
                        <button type="button" wire:click="selectAllMappingCoas" class="btn btn-sm btn-outline-primary">
                            <i class="bx bx-select-multiple me-1"></i>Pilih Semua
                        </button>
                        @if(!empty($selectedCoaIds))
                        <button type="button" wire:click="deselectAllMappingCoas" class="btn btn-sm btn-outline-danger">
                            <i class="bx bx-minus-circle me-1"></i>Kosongkan
                        </button>
                        @endif
                    </div>

                    {{-- Mapped COAs (pinned top) --}}
                    @if($mappedCoasList->isNotEmpty())
                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success">
                                <i class="bx bx-check me-1"></i>Dipetakan ({{ $mappedCoasList->count() }})
                            </span>
                            <div class="flex-grow-1 border-bottom"></div>
                        </div>
                        <div class="rounded-2 overflow-hidden border border-success border-opacity-25">
                            @foreach($mappedCoasList as $coa)
                            <label for="map_coa_{{ $coa->id }}"
                                class="d-flex align-items-center gap-3 px-3 py-2 cursor-pointer bg-success bg-opacity-10
                                       {{ !$loop->last ? 'border-bottom border-success border-opacity-10' : '' }}">
                                <input type="checkbox" class="form-check-input flex-shrink-0 mt-0 rkap-checkbox-11"
                                    value="{{ $coa->id }}"
                                    wire:model.live="selectedCoaIds"
                                    id="map_coa_{{ $coa->id }}">
                                <div class="flex-grow-1 min-width-0">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="badge bg-success fw-semibold rkap-font-075">{{ $coa->code }}</span>
                                        <span class="fw-semibold small text-success-emphasis">{{ $coa->title }}</span>
                                    </div>
                                    @if($coa->description)
                                    <div class="text-muted mt-1 rkap-font-075">{{ Str::limit($coa->description, 80) }}</div>
                                    @endif
                                </div>
                                <i class="bx bx-check-circle text-success fs-5 flex-shrink-0"></i>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Unmapped COAs --}}
                    @if($unmappedCoasList->isNotEmpty())
                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-label-secondary text-muted">
                                Belum dipetakan ({{ $unmappedCoasList->count() }})
                            </span>
                            <div class="flex-grow-1 border-bottom"></div>
                        </div>
                        <div class="rounded-2 overflow-hidden border">
                            @foreach($unmappedCoasList as $coa)
                            <label for="map_coa_{{ $coa->id }}"
                                class="d-flex align-items-center gap-3 px-3 py-2 cursor-pointer rkap-transition-12
                                       {{ !$loop->last ? 'border-bottom' : '' }}">
                                <input type="checkbox" class="form-check-input flex-shrink-0 mt-0 rkap-checkbox-11"
                                    value="{{ $coa->id }}"
                                    wire:model.live="selectedCoaIds"
                                    id="map_coa_{{ $coa->id }}">
                                <div class="flex-grow-1 min-width-0">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="badge bg-label-secondary fw-semibold rkap-font-075">{{ $coa->code }}</span>
                                        <span class="small">{{ $coa->title }}</span>
                                    </div>
                                    @if($coa->description)
                                    <div class="text-muted mt-1 rkap-font-075">{{ Str::limit($coa->description, 80) }}</div>
                                    @endif
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if($filteredCoas->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bx bx-search-alt bx-lg d-block mb-2 opacity-50"></i>
                        <small>Tidak ada COA ditemukan{{ !empty($coaSearch) ? ' untuk "' . $coaSearch . '"' : '' }}.</small>
                    </div>
                    @endif
                </div>

                <div class="modal-footer d-flex justify-content-between">
                    <span class="text-muted small" x-show="isDirty">
                        <i class="bx bx-error-circle text-warning me-1"></i>{{ __('Ada perubahan yang belum disimpan.') }}
                    </span>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" class="btn btn-label-secondary" wire:click="closeCoaMapping()">{{ __('Batal') }}</button>
                        <button type="button" class="btn btn-primary" wire:click="saveCoaMapping" @click="isDirty = false">
                            <span wire:loading.remove wire:target="saveCoaMapping">
                                <i class="bx bx-save me-1"></i>{{ __('Simpan Mapping') }}
                            </span>
                            <span wire:loading wire:target="saveCoaMapping">
                                <span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Import Excel Modal -->
    @if($isUploadModalOpen)
    <div class="modal fade show rkap-modal-show" tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Import Activities from Excel') }}</h5>
                    <button type="button" class="btn-close" wire:click="closeUploadModal()"></button>
                </div>
                <form wire:submit.prevent="importExcel">
                    <div class="modal-body">
                        <p class="mb-3">Upload an Excel file to import activities. <a href="{{ route('download-activity-template') }}" class="btn btn-sm btn-link p-0">{{ __('Download template') }}</a></p>

                        <div class="mb-3">
                            <label for="uploadedFile" class="form-label">{{ __('Excel File') }}</label>
                            <input type="file" id="uploadedFile" class="form-control @error('uploadedFile') is-invalid @enderror" wire:model="uploadedFile" accept=".xlsx,.xls,.csv">
                            @error('uploadedFile') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        @if($importMessage)
                        <div class="alert alert-{{ $importStatus === 'success' ? 'success' : 'danger' }} alert-dismissible" role="alert">
                            <pre class="mb-0 small text-wrap">{{ $importMessage }}</pre>
                        </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" wire:click="closeUploadModal()">Close</button>
                        <button type="submit" class="btn btn-primary" @if($uploadedFile===null) disabled @endif>
                            <i class="bx bx-upload me-1"></i> Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

</div>