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
        <h5 class="mb-0">Users</h5>
        <button wire:click="create()" class="btn btn-primary btn-sm">
            <i class="bx bx-plus me-1"></i> Add User
        </button>
    </div>

    <div class="table-responsive text-nowrap">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Organisasi</th>
                    <th>Roles</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody class="table-border-bottom-0">
                @forelse($users as $user)
                <tr>
                    <td><strong>{{ $user->name }}</strong></td>
                    <td>{{ $user->email }}</td>
                    <td>
                        @if($user->bureau)
                            <span class="badge bg-label-info">Biro</span>
                            {{ $user->bureau->name }}
                        @elseif($user->department)
                            <span class="badge bg-label-warning">Departemen</span>
                            {{ $user->department->name }}
                        @elseif($user->directorate)
                            <span class="badge bg-label-success">Direktorat</span>
                            {{ $user->directorate->name }}
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @foreach($user->roles as $role)
                            <span class="badge bg-label-primary m-1">{{ $role->name }}</span>
                        @endforeach
                    </td>
                    <td>
                        <button wire:click="edit({{ $user->id }})" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect">
                            <i class="bx bx-edit-alt"></i>
                        </button>
                        <button wire:click="delete({{ $user->id }})" wire:confirm="Are you sure you want to delete this user?" class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect">
                            <i class="bx bx-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center">No users found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal -->
    @if($isModalOpen)
    <div class="modal fade show" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isEditMode ? 'Edit User' : 'Add New User' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal()"></button>
                </div>
                <form wire:submit.prevent="store">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" id="name" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder="John Doe" autofocus>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" class="form-control @error('email') is-invalid @enderror" wire:model="email" placeholder="john@example.com">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password @if($isEditMode) <small class="text-muted">(leave blank to keep current)</small> @endif</label>
                            <input type="password" id="password" class="form-control @error('password') is-invalid @enderror" wire:model="password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;">
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Organisasi</label>
                            <select class="form-select mb-2" wire:model.live="organization_type">
                                <option value="">-- Pilih Tipe Organisasi --</option>
                                <option value="bureau">Biro</option>
                                <option value="department">Departemen</option>
                                <option value="directorate">Direktorat</option>
                            </select>

                            @if($organization_type === 'bureau')
                                <select class="form-select @error('bureau_id') is-invalid @enderror" wire:model="bureau_id">
                                    <option value="">-- Pilih Biro --</option>
                                    @foreach($bureaus as $bureau)
                                        <option value="{{ $bureau->id }}">{{ $bureau->name }}</option>
                                    @endforeach
                                </select>
                                @error('bureau_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @elseif($organization_type === 'department')
                                <select class="form-select @error('department_id') is-invalid @enderror" wire:model="department_id">
                                    <option value="">-- Pilih Departemen --</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                                    @endforeach
                                </select>
                                @error('department_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @elseif($organization_type === 'directorate')
                                <select class="form-select @error('directorate_id') is-invalid @enderror" wire:model="directorate_id">
                                    <option value="">-- Pilih Direktorat --</option>
                                    @foreach($directorates as $directorate)
                                        <option value="{{ $directorate->id }}">{{ $directorate->name }}</option>
                                    @endforeach
                                </select>
                                @error('directorate_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="position" class="form-label">Jabatan / Posisi</label>
                            <input type="text" id="position" class="form-control @error('position') is-invalid @enderror" wire:model="position" placeholder="contoh: Manager, Staff">
                            @error('position') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Roles</label>
                            <div class="row">
                                @foreach($roles as $role)
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="{{ $role->name }}" id="role_{{ $role->id }}" wire:model.live="userRoles">
                                        <label class="form-check-label" for="role_{{ $role->id }}">
                                            {{ $role->name }}
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
