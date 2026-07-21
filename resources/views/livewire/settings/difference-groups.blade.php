<div>
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Settings /</span> Difference Group Reference
    </h4>

    <div class="row">
        <div class="col-12">
            <ul class="nav nav-pills flex-column flex-md-row mb-4">
                <li class="nav-item">
                    <button class="nav-link @if($activeTab === 'groups') active @endif" wire:click="switchTab('groups')">
                        <i class="bx bx-receipt me-1"></i> Difference Groups
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link @if($activeTab === 'mapping') active @endif" wire:click="switchTab('mapping')">
                        <i class="bx bx-link-alt me-1"></i> COA Mappings
                    </button>
                </li>
            </ul>
        </div>
    </div>

    @if ($activeTab === 'groups')
    <!-- Difference Groups CRUD Tab -->
    <div class="card">
        <div class="card-body">

            @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible mb-4" role="alert">
                {{ session('message') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible mb-4" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">Reference Difference Group</h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm w-auto" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <div class="input-group input-group-sm w-auto">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search Difference Group...">
                    </div>
                    <button wire:click="create()" class="btn btn-primary btn-sm">
                        <i class="bx bx-plus me-1"></i> Add Difference Group
                    </button>
                </div>
            </div>

            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>{{ __('COA Terpetak') }}</th>
                            <th class="rkap-w-100">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($differenceGroups as $group)
                        <tr>
                            <td><span class="badge bg-label-primary fw-semibold">{{ $group->code }}</span></td>
                            <td><strong>{{ $group->name }}</strong></td>
                            <td class="text-wrap rkap-mw-400">
                                {{ $group->description ?? '-' }}
                            </td>
                            <td class="rkap-w-300 align-middle">
                                <div class="d-flex align-items-center flex-wrap gap-1">
                                    @forelse($group->coas as $coa)
                                    <span class="badge bg-label-secondary" title="{{ $coa->title }}">{{ $coa->code }}</span>
                                    @empty
                                    <span class="text-muted small">-- Unmapped --</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <button wire:click="edit({{ $group->id }})" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect">
                                    <i class="bx bx-edit-alt"></i>
                                </button>
                                <button wire:click="delete({{ $group->id }})" wire:confirm="Apakah Anda yakin ingin menghapus Group Difference ini?" class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center">No Difference Group records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $differenceGroups->links() }}
            </div>

        </div>
    </div>

    {{-- CRUD Modal --}}
    @if($isModalOpen)
    <div class="modal fade show rkap-modal-show" tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isEditMode ? 'Edit Difference Group' : 'Add New Difference Group' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal()"></button>
                </div>
                <form wire:submit.prevent="store">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="group-code" class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" id="group-code" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder="e.g. 1006" autofocus>
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="group-name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" id="group-name" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder="e.g. Uang Muka Jangka Panjang">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="group-description" class="form-label">Description</label>
                            <textarea id="group-description" class="form-control @error('description') is-invalid @enderror" wire:model="description" rows="3" placeholder="Optional description"></textarea>
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

    @else
    <!-- COA Mappings Tab -->
    <div class="card">
        <div class="card-body">

            @if (session()->has('mapping_message'))
            <div class="alert alert-success alert-dismissible mb-4" role="alert">
                {{ session('mapping_message') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            @if (session()->has('mapping_error'))
            <div class="alert alert-danger alert-dismissible mb-4" role="alert">
                {{ session('mapping_error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <h5 class="mb-0">{{ __('Pemetaan COA ke Difference Group') }}</h5>
                <div class="d-flex flex-wrap gap-2">
                    <select class="form-select form-select-sm w-auto" wire:model.live="perPage">
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <select class="form-select form-select-sm w-auto" wire:model.live="filterDifferenceGroup">
                        <option value="">All Difference Groups</option>
                        <option value="unmapped">Unmapped</option>
                        @foreach($allDifferenceGroups as $dg)
                        <option value="{{ $dg->id }}">{{ $dg->code }} - {{ $dg->name }}</option>
                        @endforeach
                    </select>
                    <div class="input-group input-group-sm w-auto">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="searchMapping" placeholder="{{ __('Search COA...') }}">
                    </div>
                </div>
            </div>

            <!-- Bulk Action Bar -->
            @if (count($selectedCoas) > 0)
            <div class="d-flex align-items-center gap-2 p-3 mb-4 bg-label-primary rounded border border-primary">
                <span class="fw-medium"><i class="bx bx-check-square me-1"></i> {{ count($selectedCoas) }} COA terpilih</span>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <select class="form-select form-select-sm w-auto" wire:model="bulkDifferenceGroupId">
                        <option value="">-- Remove Mapping (Unmap) --</option>
                        @foreach($allDifferenceGroups as $dg)
                        <option value="{{ $dg->id }}">Map to: {{ $dg->code }} - {{ $dg->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-primary" wire:click="applyBulkMapping">
                        Apply Bulk Mapping
                    </button>
                </div>
            </div>
            @endif

            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th class="rkap-w-40">
                                <input class="form-check-input" type="checkbox" wire:click="toggleSelectAll($event.target.checked)"
                                    @if(count($selectedCoas)> 0 && count($selectedCoas) === $coas->total()) checked @endif>
                            </th>
                            <th>COA Code</th>
                            <th>COA Title</th>
                            <th>{{ __('COA Group') }}</th>
                            <th>{{ __('Current Difference Group Mapping') }}</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($coas as $coa)
                        <tr wire:key="coa-row-{{ $coa->id }}">
                            <td>
                                <input class="form-check-input" type="checkbox" value="{{ $coa->id }}" wire:model.live="selectedCoas">
                            </td>
                            <td><strong>{{ $coa->code }}</strong></td>
                            <td class="text-wrap rkap-mw-250">{{ $coa->title }}</td>
                            <td>
                                @if($coa->coaGroup)
                                <span class="badge bg-label-info fw-semibold">{{ $coa->coaGroup->name }}</span>
                                @else
                                <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="rkap-w-300 align-middle">
                                <div class="d-flex align-items-center flex-wrap gap-1">
                                    @forelse($coa->differenceGroups as $dg)
                                    <span class="badge bg-label-primary">{{ $dg->code }}</span>
                                    @empty
                                    <span class="text-muted small">-- Unmapped --</span>
                                    @endforelse

                                    <button class="btn btn-xs btn-outline-primary ms-auto" wire:click="openMappingModal({{ $coa->id }})">
                                        <i class="bx bx-edit-alt small"></i> Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center">No COA records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $coas->links() }}
            </div>
        </div>
    </div>
    @endif

    {{-- Mapping Modal --}}
    @if($isMappingModalOpen && $selectedCoaForMapping)
    <div class="modal fade show rkap-modal-show" tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Mapping COA: {{ $selectedCoaForMapping->code }}</h5>
                    <button type="button" class="btn-close" wire:click="closeMappingModal()"></button>
                </div>
                <form wire:submit.prevent="saveSingleCoaMapping">
                    <div class="modal-body">
                        <p class="text-muted mb-3">{{ $selectedCoaForMapping->title }}</p>
                        
                        <label class="form-label fw-semibold mb-2">Pilih Difference Group:</label>
                        <div class="row">
                            @foreach($allDifferenceGroups as $dg)
                            <div class="col-12 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="dg-chk-{{ $dg->id }}" value="{{ $dg->id }}" wire:model="tempMappedGroups">
                                    <label class="form-check-label" for="dg-chk-{{ $dg->id }}">
                                        <span class="badge bg-label-primary me-1">{{ $dg->code }}</span> - {{ $dg->name }}
                                    </label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" wire:click="closeMappingModal()">Close</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>