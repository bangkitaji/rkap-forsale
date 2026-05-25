<div>
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
        <h5 class="mb-0">Roles</h5>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm w-auto" wire:model.live="perPage">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <div class="input-group input-group-sm w-auto">
                <span class="input-group-text"><i class="bx bx-search"></i></span>
                <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search roles...">
            </div>
            <button wire:click="create()" class="btn btn-primary btn-sm">
                <i class="bx bx-plus me-1"></i> Add Role
            </button>
        </div>
    </div>

    <div class="table-responsive text-nowrap">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Permissions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody class="table-border-bottom-0">
                @forelse($roles as $role)
                <tr>
                    <td>{{ $role->id }}</td>
                    <td><strong>{{ $role->name }}</strong></td>
                    <td class="text-wrap">
                        @foreach($role->permissions as $perm)
                            <span class="badge bg-label-info m-1">{{ $perm->name }}</span>
                        @endforeach
                    </td>
                    <td>
                        <button wire:click="edit({{ $role->id }})" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect">
                            <i class="bx bx-edit-alt"></i>
                        </button>
                        <button wire:click="delete({{ $role->id }})" wire:confirm="Are you sure you want to delete this role?" class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect">
                            <i class="bx bx-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center">No roles found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $roles->links() }}
    </div>

    <!-- Modal -->
    @if($isModalOpen)
    <div class="modal fade show" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isEditMode ? 'Edit Role' : 'Add New Role' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal()"></button>
                </div>
                <form wire:submit.prevent="store">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="roleName" class="form-label">Role Name</label>
                            <input type="text" id="roleName" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder="e.g. editor" autofocus>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Permissions</label>
                            <div class="row">
                                @foreach($permissions as $permission)
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="{{ $permission->name }}" id="perm_{{ $permission->id }}" wire:model.live="rolePermissions">
                                        <label class="form-check-label" for="perm_{{ $permission->id }}">
                                            {{ $permission->name }}
                                        </label>
                                    </div>
                                </div>
                                @endforeach
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
    @endif
</div>
