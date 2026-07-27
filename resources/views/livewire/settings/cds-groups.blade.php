<div>
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Settings /</span> CDS Group Reference
    </h4>

    <div class="row">
        <div class="col-12">
            <ul class="nav nav-pills flex-column flex-md-row mb-4">
                <li class="nav-item">
                    <button class="nav-link @if($activeTab === 'groups') active @endif" wire:click="switchTab('groups')">
                        <i class="bx bx-receipt me-1"></i> CDS Groups
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link @if($activeTab === 'mapping') active @endif" wire:click="switchTab('mapping')">
                        <i class="bx bx-link-alt me-1"></i> COA Group Mappings
                    </button>
                </li>
            </ul>
        </div>
    </div>

    @if ($activeTab === 'groups')
    <!-- CDS Groups CRUD Tab -->
    <div class="card">
        <div class="card-body">

            @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible mb-4" role="alert">
                {{ session('message') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">Reference CDS Group</h5>
                <div class="d-flex gap-2">
                    <div class="input-group input-group-sm w-auto">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search CDS Group...">
                    </div>
                    <button wire:click="create()" class="btn btn-primary btn-sm">
                        <i class="bx bx-plus me-1"></i> Add CDS Group
                    </button>
                </div>
            </div>

            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th class="rkap-w-100">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($cdsGroups as $group)
                        <tr>
                            <td><span class="badge bg-label-primary fw-semibold">{{ $group->code }}</span></td>
                            <td><strong>{{ $group->name }}</strong></td>
                            <td>
                                <button wire:click="edit({{ $group->id }})" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect">
                                    <i class="bx bx-edit-alt"></i>
                                </button>
                                <button wire:click="delete({{ $group->id }})" wire:confirm="Apakah Anda yakin ingin menghapus CDS Group ini?" class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">No CDS groups found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $cdsGroups->links() }}
            </div>
        </div>
    </div>

    <!-- Modal Form -->
    <div class="modal fade @if($isModalOpen) show d-block @endif" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-labelledby="cdsGroupModalLabel" aria-hidden="true" @if(!$isModalOpen) aria-hidden="true" @endif>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cdsGroupModalLabel">
                        {{ $isEditMode ? 'Edit CDS Group' : 'Add CDS Group' }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeModal()" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="save">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" id="code" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder="Enter code" {{ $isEditMode ? 'disabled' : '' }}>
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12 mb-3">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" id="name" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder="Enter name">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
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
    @else
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
                <h5 class="mb-0">{{ __('Pemetaan CDS Group ke COA Group & Cash Flow Group') }}</h5>
                <div class="d-flex flex-wrap gap-2">
                    <div class="input-group input-group-sm w-auto">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="searchMapping" placeholder="{{ __('Search CDS Group...') }}">
                    </div>
                </div>
            </div>

            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>CDS Group Name</th>
                            <th>{{ __('Mapped COA Groups') }}</th>
                            <th>{{ __('Mapped Cash Flow Groups') }}</th>
                            <th class="rkap-w-100">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($mappedCdsGroups as $cdsGroup)
                        <tr wire:key="mapped-cds-group-row-{{ $cdsGroup->id }}">
                            <td><span class="badge bg-label-primary fw-semibold">{{ $cdsGroup->code }}</span></td>
                            <td><strong>{{ $cdsGroup->name }}</strong></td>
                            <td>
                                <span class="badge bg-label-info">{{ $cdsGroup->coaGroups->count() }} COA Groups</span>
                            </td>
                            <td>
                                <span class="badge bg-label-success">{{ $cdsGroup->cashflowGroups->count() }} Cash Flow Groups</span>
                            </td>
                            <td>
                                <button wire:click="openMappingModal({{ $cdsGroup->id }})" class="btn btn-sm btn-primary">
                                    <i class="bx bx-link-alt me-1"></i> Manage Mappings
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center">No CDS Group records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $mappedCdsGroups->links() }}
            </div>
        </div>
    </div>
    @endif

    <!-- Mapping Modal -->
    <div class="modal fade @if($isMappingModalOpen) show d-block @endif" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-labelledby="mappingModalLabel" aria-hidden="true" @if(!$isMappingModalOpen) aria-hidden="true" @endif>
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mappingModalLabel">
                        Mapping untuk: {{ $mappingCdsGroupName }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeMappingModal()" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="saveMapping">
                    <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">COA Groups</label>
                                <div class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                                    @foreach($allCoaGroups as $coa)
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" value="{{ $coa->id }}" id="coa-{{ $coa->id }}" wire:model="selectedMappingCoaGroups">
                                        <label class="form-check-label" for="coa-{{ $coa->id }}">
                                            <strong>{{ $coa->code }}</strong> - {{ $coa->name }}
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cash Flow Groups</label>
                                <div class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                                    @foreach($allCashflowGroups as $cfg)
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" value="{{ $cfg->id }}" id="cfg-{{ $cfg->id }}" wire:model="selectedMappingCashflowGroups">
                                        <label class="form-check-label" for="cfg-{{ $cfg->id }}">
                                            <strong>{{ $cfg->code }}</strong> - {{ $cfg->name }}
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" wire:click="closeMappingModal()">Close</button>
                        <button type="submit" class="btn btn-primary">Save Mappings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
