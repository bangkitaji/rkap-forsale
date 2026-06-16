<div>
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Account /</span> My Profile & Settings
    </h4>

    <div class="row">
        <div class="col-md-12">
            <!-- Navigation Tabs -->
            <ul class="nav nav-pills flex-column flex-md-row mb-4 gap-2">
                <li class="nav-item">
                    <button class="nav-link @if($activeTab === 'profile') active @endif" wire:click="setTab('profile')">
                        <i class="bx bx-user me-1.5"></i> Profile Info
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link @if($activeTab === 'security') active @endif" wire:click="setTab('security')">
                        <i class="bx bx-lock-alt me-1.5"></i> Security
                    </button>
                </li>
            </ul>

            <!-- Content Area -->
            @if($activeTab === 'profile')
                <div class="card mb-4 border">
                    <h5 class="card-header border-bottom py-3">Profile Details</h5>
                    <div class="card-body pt-3">
                        @if (session()->has('message_profile'))
                            <div class="alert alert-success alert-dismissible" role="alert">
                                {{ session('message_profile') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <form wire:submit.prevent="saveProfile" novalidate>
                            <div class="row g-3">
                                <!-- Editable fields -->
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input class="form-control @error('name') is-invalid @enderror" type="text" id="name" wire:model="name" />
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">E-mail</label>
                                    <input class="form-control @error('email') is-invalid @enderror" type="email" id="email" wire:model="email" />
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <hr class="my-2" />
                                    <h6 class="fw-semibold text-muted mb-3">Organization & Role Details (Read-only)</h6>
                                </div>

                                <!-- Read-only Metadata fields -->
                                <div class="col-md-6">
                                    <label class="form-label text-muted">Directorate</label>
                                    <input class="form-control bg-light" type="text" value="{{ $directorate_name }}" readonly />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted">Department</label>
                                    <input class="form-control bg-light" type="text" value="{{ $department_name }}" readonly />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted">Bureau</label>
                                    <input class="form-control bg-light" type="text" value="{{ $bureau_name }}" readonly />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted">Job Position</label>
                                    <input class="form-control bg-light" type="text" value="{{ $position }}" readonly />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted">System Role</label>
                                    <div>
                                        <span class="badge bg-label-primary px-3 py-2 fs-6">{{ $role_name }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary me-2">Save Profile Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if($activeTab === 'security')
                <div class="card mb-4 border">
                    <h5 class="card-header border-bottom py-3">Security & Password</h5>
                    <div class="card-body pt-3">
                        @if (session()->has('message_security'))
                            <div class="alert alert-success alert-dismissible" role="alert">
                                {{ session('message_security') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <form wire:submit.prevent="savePassword" novalidate>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label" for="current_password">Current Password</label>
                                        <input type="password" id="current_password" wire:model="current_password" class="form-control @error('current_password') is-invalid @enderror" placeholder="••••••••" />
                                        @error('current_password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="new_password">New Password</label>
                                        <input type="password" id="new_password" wire:model="new_password" class="form-control @error('new_password') is-invalid @enderror" placeholder="••••••••" />
                                        @error('new_password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="new_password_confirmation">Confirm New Password</label>
                                        <input type="password" id="new_password_confirmation" wire:model="new_password_confirmation" class="form-control" placeholder="••••••••" />
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">Save Password</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
