<div>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card mb-4 border">
                <div class="card-header border-bottom py-3">
                    <h5 class="card-title mb-0"><i class="bx bx-lock-alt me-2 text-primary"></i>Change Password</h5>
                    <small class="text-muted">Manage your password to secure your account</small>
                </div>
                <div class="card-body pt-3">
                    @if (session()->has('message'))
                        <div class="alert alert-success alert-dismissible" role="alert">
                            {{ session('message') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <form wire:submit.prevent="changePassword" novalidate>
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

                        <div class="mb-4">
                            <label class="form-label" for="new_password_confirmation">Confirm New Password</label>
                            <input type="password" id="new_password_confirmation" wire:model="new_password_confirmation" class="form-control" placeholder="••••••••" />
                        </div>

                        <button type="submit" class="btn btn-primary d-grid w-100">Save Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
