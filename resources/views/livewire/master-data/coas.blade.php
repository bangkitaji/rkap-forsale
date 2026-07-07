<div>
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Master Data /</span> COA
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

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">Chart of Accounts (COA)</h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm w-auto" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <div class="input-group input-group-sm w-auto">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search COA...">
                    </div>
                    <button wire:click="create()" class="btn btn-primary btn-sm">
                        <i class="bx bx-plus me-1"></i> Add COA
                    </button>
                    <button wire:click="openUploadModal()" class="btn btn-info btn-sm">
                        <i class="bx bx-upload me-1"></i> Import Excel
                    </button>
                </div>
            </div>

            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th wire:click="sort('code')" class="rkap-cursor-pointer rkap-user-select-none text-nowrap">
                                Code
                                @if($sortBy === 'code')
                                <i class="bx bx-chevron-{{ $sortDir === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                @else
                                <i class="bx bx-sort ms-1 text-muted opacity-50"></i>
                                @endif
                            </th>
                            <th wire:click="sort('title')" class="rkap-cursor-pointer rkap-user-select-none text-nowrap">
                                Title
                                @if($sortBy === 'title')
                                <i class="bx bx-chevron-{{ $sortDir === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                @else
                                <i class="bx bx-sort ms-1 text-muted opacity-50"></i>
                                @endif
                            </th>
                            <th>Group</th>
                            <th wire:click="sort('cf_type')" class="rkap-cursor-pointer rkap-user-select-none text-nowrap">
                                CF Type
                                @if($sortBy === 'cf_type')
                                <i class="bx bx-chevron-{{ $sortDir === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                @else
                                <i class="bx bx-sort ms-1 text-muted opacity-50"></i>
                                @endif
                            </th>
                            <th wire:click="sort('description')" class="rkap-cursor-pointer rkap-user-select-none text-nowrap">
                                Description
                                @if($sortBy === 'description')
                                <i class="bx bx-chevron-{{ $sortDir === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                @else
                                <i class="bx bx-sort ms-1 text-muted opacity-50"></i>
                                @endif
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($coas as $coa)
                        <tr>
                            <td><strong>{{ $coa->code }}</strong></td>
                            <td>{{ $coa->title }}</td>
                            <td>
                                @if($coa->coaGroup)
                                <span class="badge bg-label-info fw-semibold">{{ $coa->coaGroup->name }}</span>
                                @else
                                <span class="badge bg-label-secondary text-muted">Unmapped</span>
                                @endif
                            </td>
                            <td>
                                @if($coa->cf_type === 'CASH IN')
                                <span class="badge bg-label-success fw-semibold">CASH IN</span>
                                @elseif($coa->cf_type === 'CASH OUT')
                                <span class="badge bg-label-danger fw-semibold">CASH OUT</span>
                                @elseif($coa->cf_type === 'NO CASHFLOW')
                                <span class="badge bg-label-warning fw-semibold">NO CASHFLOW</span>
                                @else
                                <span class="badge bg-label-secondary text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-wrap rkap-mw-300">
                                {{ $coa->description ?? '-' }}
                            </td>
                            <td>
                                <button wire:click="edit({{ $coa->id }})" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect">
                                    <i class="bx bx-edit-alt"></i>
                                </button>
                                <button wire:click="delete({{ $coa->id }})" wire:confirm="Are you sure you want to delete this COA?" class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center">No COA records found.</td>
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

    {{-- Modal --}}
    @if($isModalOpen)
    <div class="modal fade show rkap-modal-show" tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isEditMode ? 'Edit COA' : 'Add New COA' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal()"></button>
                </div>
                <form wire:submit.prevent="store">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="coa-code" class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" id="coa-code" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder="e.g. 1-1001" autofocus>
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="coa-title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" id="coa-title" class="form-control @error('title') is-invalid @enderror" wire:model="title" placeholder="Account Title">
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="coa-group" class="form-label">COA Group</label>
                            <select id="coa-group" class="form-select @error('coaGroupId') is-invalid @enderror" wire:model="coaGroupId">
                                <option value="">Select Group...</option>
                                @foreach($coaGroups as $g)
                                <option value="{{ $g->id }}">{{ $g->name }} ({{ $g->code }})</option>
                                @endforeach
                            </select>
                            @error('coaGroupId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="coa-cf-type" class="form-label">CF Type</label>
                            <select id="coa-cf-type" class="form-select @error('cfType') is-invalid @enderror" wire:model="cfType">
                                <option value="">Select CF Type...</option>
                                <option value="CASH IN">CASH IN</option>
                                <option value="CASH OUT">CASH OUT</option>
                                <option value="NO CASHFLOW">NO CASHFLOW</option>
                            </select>
                            @error('cfType') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="coa-description" class="form-label">Description</label>
                            <textarea id="coa-description" class="form-control @error('description') is-invalid @enderror" wire:model="description" rows="3" placeholder="Optional description"></textarea>
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

    {{-- Upload Modal --}}
    @if($isUploadModalOpen)
    <div class="modal fade show rkap-modal-show" tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import COAs from Excel</h5>
                    <button type="button" class="btn-close" wire:click="closeUploadModal()"></button>
                </div>
                <form wire:submit.prevent="importExcel">
                    <div class="modal-body">
                        <p class="mb-3">Upload an Excel file to import COAs. <a href="{{ route('download-coa-template') }}" class="btn btn-sm btn-link p-0">Download template</a></p>

                        <div class="mb-3">
                            <label for="uploadedFile" class="form-label">Excel File</label>
                            <input type="file" id="uploadedFile" class="form-control @error('uploadedFile') is-invalid @enderror" wire:model="uploadedFile" accept=".xlsx,.xls,.csv">
                            @error('uploadedFile') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        @if($importMessage)
                        <div class="alert alert-{{ $importStatus === 'success' ? 'success' : 'danger' }} alert-dismissible" role="alert">
                            <pre class="mb-0 rkap-font-0875 rkap-ws-pre-wrap">{{ $importMessage }}</pre>
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