<div>
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Settings /</span> Report Group
    </h4>

    <div class="row">
        <div class="col-12">
            <ul class="nav nav-pills flex-column flex-md-row mb-4">
                <li class="nav-item">
                    <button class="nav-link @if($activeTab === 'groups') active @endif" wire:click="switchTab('groups')">
                        <i class="bx bx-receipt me-1"></i> Report Groups
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link @if($activeTab === 'mapping') active @endif" wire:click="switchTab('mapping')">
                        <i class="bx bx-link-alt me-1"></i> COA Group Mappings
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link @if($activeTab === 'cashflow-mapping') active @endif" wire:click="switchTab('cashflow-mapping')">
                        <i class="bx bx-wallet me-1"></i> Cashflow Mappings
                    </button>
                </li>
            </ul>
        </div>
    </div>

    @if ($activeTab === 'groups')
    <!-- Report Groups Tab -->
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
                <h5 class="mb-0">Daftar Report Group</h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm w-auto" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <div class="input-group input-group-sm w-auto">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search Report Group...">
                    </div>
                    <button wire:click="create()" class="btn btn-primary btn-sm">
                        <i class="bx bx-plus me-1"></i> Add Report Group
                    </button>
                </div>
            </div>

            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th class="rkap-w-100">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($reportGroups as $group)
                        <tr>
                            <td><strong>{{ $group->code }}</strong></td>
                            <td>{{ $group->name }}</td>
                            <td>
                                <span class="badge @if($group->type === 'PL') bg-label-info @else bg-label-primary @endif">
                                    {{ $group->type }}
                                </span>
                            </td>
                            <td class="text-wrap rkap-mw-400">
                                {{ $group->description ?? '-' }}
                            </td>
                            <td>
                                <button wire:click="edit({{ $group->id }})" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect">
                                    <i class="bx bx-edit-alt"></i>
                                </button>
                                <button wire:click="delete({{ $group->id }})" wire:confirm="Apakah Anda yakin ingin menghapus Report Group ini?" class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center">No Report Group records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $reportGroups->links() }}
            </div>
        </div>
    </div>

    <!-- Modal -->
    @if($isModalOpen)
    <div class="modal fade show rkap-modal-show" tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isEditMode ? 'Edit Report Group' : 'Add New Report Group' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal()"></button>
                </div>
                <form wire:submit.prevent="store">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="group-code" class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" id="group-code" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder="e.g. PL0001, BS0001" autofocus>
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="group-type" class="form-label">Type <span class="text-danger">*</span></label>
                            <select id="group-type" class="form-select @error('type') is-invalid @enderror" wire:model="type">
                                <option value="PL">Profit & Loss (PL)</option>
                                <option value="BS">Balance Sheet (BS)</option>
                                <option value="CF">Cash Flow (CF)</option>
                            </select>
                            @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="group-name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" id="group-name" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder="e.g. Revenue, Direct Cost, Aset Lancar">
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

    @elseif ($activeTab === 'mapping')
    <!-- COA Group Mappings Tab -->
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
                <h5 class="mb-0">Pemetaan COA Group ke Report Group</h5>
                <div class="d-flex flex-wrap gap-2">
                    <select class="form-select form-select-sm w-auto" wire:model.live="perPage">
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <select class="form-select form-select-sm w-auto" wire:model.live="filterReportGroup">
                        <option value="">All Report Groups</option>
                        <option value="unmapped">Unmapped</option>
                        @foreach($allReportGroups as $rg)
                        <option value="{{ $rg->id }}">[{{ $rg->type }}] {{ $rg->name }}</option>
                        @endforeach
                    </select>
                    <div class="input-group input-group-sm w-auto">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="searchMapping" placeholder="Search COA Group...">
                    </div>
                </div>
            </div>

            <!-- Bulk Action Bar -->
            @if (count($selectedCoaGroups) > 0)
            <div class="d-flex align-items-center gap-2 p-3 mb-4 bg-label-primary rounded border border-primary">
                <span class="fw-medium"><i class="bx bx-check-square me-1"></i> {{ count($selectedCoaGroups) }} COA Group terpilih</span>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <select class="form-select form-select-sm w-auto" wire:model="bulkReportGroupId">
                        <option value="">-- Remove Mapping (Unmap) --</option>
                        @foreach($allReportGroups as $rg)
                        <option value="{{ $rg->id }}">Map to: [{{ $rg->type }}] {{ $rg->name }}</option>
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
                                    @if(count($selectedCoaGroups)> 0 && count($selectedCoaGroups) === $coaGroups->total()) checked @endif>
                            </th>
                            <th>COA Group Code</th>
                            <th>COA Group Name</th>
                            <th>Current Report Group Mapping</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($coaGroups as $coaGroup)
                        <tr>
                            <td>
                                <input class="form-check-input" type="checkbox" value="{{ $coaGroup->id }}" wire:model.live="selectedCoaGroups">
                            </td>
                            <td><strong>{{ $coaGroup->code }}</strong></td>
                            <td>{{ $coaGroup->name }}</td>
                            <td class="rkap-w-300">
                                <select class="form-select form-select-sm" wire:change="mapSingleGroup({{ $coaGroup->id }}, $event.target.value)">
                                    <option value="">-- Unmapped --</option>
                                    @foreach($allReportGroups as $rg)
                                    <option value="{{ $rg->id }}" @selected($coaGroup->report_group_id == $rg->id)>
                                        [{{ $rg->type }}] {{ $rg->name }} ({{ $rg->code }})
                                    </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center">No COA Group records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $coaGroups->links() }}
            </div>
        </div>
    </div>

    @elseif ($activeTab === 'cashflow-mapping')
    <!-- Cashflow Mappings Tab -->
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
                <h5 class="mb-0">Pemetaan Cashflow Group ke Report Group</h5>
                <div class="d-flex flex-wrap gap-2">
                    <select class="form-select form-select-sm w-auto" wire:model.live="perPage">
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <select class="form-select form-select-sm w-auto" wire:model.live="filterCashflowReportGroup">
                        <option value="">All Report Groups</option>
                        <option value="unmapped">Unmapped</option>
                        @foreach($allReportGroups as $rg)
                        <option value="{{ $rg->id }}">[{{ $rg->type }}] {{ $rg->name }}</option>
                        @endforeach
                    </select>
                    <div class="input-group input-group-sm w-auto">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="searchCashflow" placeholder="Search Cashflow Group...">
                    </div>
                </div>
            </div>

            <!-- Bulk Action Bar -->
            @if (count($selectedCashflowGroups) > 0)
            <div class="d-flex align-items-center gap-2 p-3 mb-4 bg-label-primary rounded border border-primary">
                <span class="fw-medium"><i class="bx bx-check-square me-1"></i> {{ count($selectedCashflowGroups) }} Cashflow Group terpilih</span>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <select class="form-select form-select-sm w-auto" wire:model="bulkCashflowReportGroupId">
                        <option value="">-- Remove Mapping (Unmap) --</option>
                        @foreach($allReportGroups as $rg)
                        <option value="{{ $rg->id }}">Map to: [{{ $rg->type }}] {{ $rg->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-primary" wire:click="applyBulkCashflowMapping">
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
                                <input class="form-check-input" type="checkbox" wire:click="toggleSelectAllCashflow($event.target.checked)"
                                    @if(count($selectedCashflowGroups)> 0 && count($selectedCashflowGroups) === $cashflowGroups->total()) checked @endif>
                            </th>
                            <th>Cashflow Group Code</th>
                            <th>Cashflow Group Name</th>
                            <th>Current Report Group Mapping</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($cashflowGroups as $cg)
                        <tr>
                            <td>
                                <input class="form-check-input" type="checkbox" value="{{ $cg->id }}" wire:model.live="selectedCashflowGroups">
                            </td>
                            <td><strong>{{ $cg->code }}</strong></td>
                            <td>{{ $cg->name }}</td>
                            <td class="rkap-w-300">
                                <select class="form-select form-select-sm" wire:change="mapSingleCashflowGroup({{ $cg->id }}, $event.target.value)">
                                    <option value="">-- Unmapped --</option>
                                    @foreach($allReportGroups as $rg)
                                    <option value="{{ $rg->id }}" @selected($cg->report_group_id == $rg->id)>
                                        [{{ $rg->type }}] {{ $rg->name }} ({{ $rg->code }})
                                    </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center">No Cashflow Group records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $cashflowGroups->links() }}
            </div>
        </div>
    </div>
    @endif
</div>