<div>
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Master Data /</span> COA Groups
    </h4>

    <!-- Custom Tabs -->
    <div class="row">
        <div class="col-12">
            <ul class="nav nav-pills flex-column flex-md-row mb-3 gap-2">
                <li class="nav-item">
                    <button type="button" class="nav-link @if($activeTab === 'groups') active @endif" wire:click="switchTab('groups')">
                        <i class="bx bx-group me-1"></i> Manage Groups
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link @if($activeTab === 'mapping') active @endif" wire:click="switchTab('mapping')">
                        <i class="bx bx-link me-1"></i> COA Mapping
                    </button>
                </li>
            </ul>
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

            <!-- ------------------------------------------------------------- -->
            <!-- TAB 1: COA GROUPS CRUD -->
            <!-- ------------------------------------------------------------- -->
            @if($activeTab === 'groups')
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">COA Groups Directory</h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm w-auto" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <div class="input-group input-group-sm w-auto">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="searchGroup" placeholder="Search Groups...">
                    </div>
                    <button wire:click="createGroup()" class="btn btn-primary btn-sm">
                        <i class="bx bx-plus me-1"></i> Add Group
                    </button>
                </div>
            </div>

            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th wire:click="sortGroup('code')" style="cursor:pointer; user-select:none;">
                                Code
                                @if($sortByGroup === 'code')
                                    <i class="bx bx-chevron-{{ $sortDirGroup === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                @else
                                    <i class="bx bx-sort ms-1 text-muted opacity-50"></i>
                                @endif
                            </th>
                            <th wire:click="sortGroup('name')" style="cursor:pointer; user-select:none;">
                                Group Name
                                @if($sortByGroup === 'name')
                                    <i class="bx bx-chevron-{{ $sortDirGroup === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                @else
                                    <i class="bx bx-sort ms-1 text-muted opacity-50"></i>
                                @endif
                            </th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($groups as $group)
                        <tr>
                            <td><span class="badge bg-label-info fw-bold">{{ $group->code }}</span></td>
                            <td><strong>{{ $group->name }}</strong></td>
                            <td class="text-wrap" style="max-width: 350px;">
                                {{ $group->description ?? '-' }}
                            </td>
                            <td>
                                <button wire:click="editGroup({{ $group->id }})" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect">
                                    <i class="bx bx-edit-alt"></i>
                                </button>
                                <button wire:click="deleteGroup({{ $group->id }})" wire:confirm="Are you sure you want to delete this COA Group? It will unmap all COAs mapped to it." class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-3">No COA Group records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $groups->links() }}
            </div>
            @endif

            <!-- ------------------------------------------------------------- -->
            <!-- TAB 2: COA MAPPING -->
            <!-- ------------------------------------------------------------- -->
            @if($activeTab === 'mapping')
            <div class="row mb-4 align-items-center g-3">
                <div class="col-md-3">
                    <label class="form-label" for="filter-group">Filter by Group</label>
                    <select id="filter-group" class="form-select form-select-sm" wire:model.live="filterGroupId">
                        <option value="all">All COAs</option>
                        <option value="unmapped">Unmapped COAs</option>
                        @foreach($allGroups as $g)
                            <option value="{{ $g->id }}">{{ $g->name }} ({{ $g->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="search-coa">Search COA</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" id="search-coa" class="form-control" wire:model.live.debounce.300ms="searchCoa" placeholder="Code, title...">
                    </div>
                </div>
                <div class="col-md-6 text-md-end mt-md-4">
                    <select class="form-select form-select-sm d-inline-block w-auto me-2" wire:model.live="perPage">
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
            </div>

            <!-- Bulk Actions Section -->
            <div class="card bg-lighter mb-4 border-0">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <span class="badge bg-primary me-2">{{ count($selectedCoas) }}</span>
                            <span class="text-muted">COA(s) selected</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label class="mb-0 text-muted me-2" for="bulk-target-group">Bulk Map to:</label>
                            <select id="bulk-target-group" class="form-select form-select-sm w-auto d-inline-block @error('targetGroupId') is-invalid @enderror" wire:model="targetGroupId">
                                <option value="">Select Group...</option>
                                @foreach($allGroups as $g)
                                    <option value="{{ $g->id }}">{{ $g->name }} ({{ $g->code }})</option>
                                @endforeach
                            </select>
                            <button class="btn btn-primary btn-sm" wire:click="mapSelected" @if(empty($selectedCoas) || !$targetGroupId) disabled @endif>
                                Apply Mapping
                            </button>
                            <button class="btn btn-outline-danger btn-sm" wire:click="unmapSelected" @if(empty($selectedCoas)) disabled @endif>
                                Clear Mapping
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" class="form-check-input" 
                                       wire:click="selectAllCoas([{{ implode(',', $coas->pluck('id')->toArray()) }}])"
                                       @if(count($coas) > 0 && collect($coas->pluck('id'))->every(fn($id) => in_array($id, $selectedCoas))) checked @endif>
                            </th>
                            <th>COA Code</th>
                            <th>COA Title</th>
                            <th>Mapped Group</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($coas as $coa)
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input" value="{{ $coa->id }}" wire:model.live="selectedCoas">
                            </td>
                            <td><strong>{{ $coa->code }}</strong></td>
                            <td class="text-wrap" style="max-width: 250px;">{{ $coa->title }}</td>
                            <td>
                                @if($coa->coaGroup)
                                    <span class="badge bg-label-success fw-semibold">
                                        <i class="bx bx-group me-1" style="font-size: 0.75rem;"></i>
                                        {{ $coa->coaGroup->name }}
                                    </span>
                                @else
                                    <span class="badge bg-label-secondary text-muted">Unmapped</span>
                                @endif
                            </td>
                            <td class="text-wrap" style="max-width: 250px;">
                                {{ $coa->description ?? '-' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-3">No COA records found matching criteria.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $coas->links() }}
            </div>
            @endif

        </div>
    </div>

    <!-- ------------------------------------------------------------- -->
    <!-- COA GROUP ADD/EDIT MODAL -->
    <!-- ------------------------------------------------------------- -->
    @if($isModalOpen)
    <div class="modal fade show" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isEditMode ? 'Edit COA Group' : 'Add New COA Group' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal()"></button>
                </div>
                <form wire:submit.prevent="storeGroup">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="group-code" class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" id="group-code" class="form-control @error('groupCode') is-invalid @enderror" wire:model="groupCode" placeholder="e.g. 520000 or CAPEX" autofocus>
                            @error('groupCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="group-name" class="form-label">Group Name <span class="text-danger">*</span></label>
                            <input type="text" id="group-name" class="form-control @error('groupName') is-invalid @enderror" wire:model="groupName" placeholder="e.g. Beban Operasional">
                            @error('groupName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="group-description" class="form-label">Description</label>
                            <textarea id="group-description" class="form-control @error('groupDescription') is-invalid @enderror" wire:model="groupDescription" rows="3" placeholder="Optional description"></textarea>
                            @error('groupDescription') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
</div>
