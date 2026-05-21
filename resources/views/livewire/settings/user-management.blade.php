<div>
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Settings /</span> User Management
    </h4>

    <div class="card">
        <div class="card-header border-bottom mb-3">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <button type="button" class="nav-link @if($activeTab === 'users') active @endif" wire:click="$set('activeTab', 'users')" role="tab" aria-selected="true">
                        <i class="bx bx-user me-1"></i> Users
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link @if($activeTab === 'roles') active @endif" wire:click="$set('activeTab', 'roles')" role="tab" aria-selected="false">
                        <i class="bx bx-check-shield me-1"></i> Roles
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link @if($activeTab === 'permissions') active @endif" wire:click="$set('activeTab', 'permissions')" role="tab" aria-selected="false">
                        <i class="bx bx-key me-1"></i> Permissions
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content p-0 border-0">
                <div class="tab-pane fade show active" role="tabpanel">
                    @if($activeTab === 'users')
                        <livewire:settings.users-tab />
                    @elseif($activeTab === 'roles')
                        <livewire:settings.roles-tab />
                    @elseif($activeTab === 'permissions')
                        <livewire:settings.permissions-tab />
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
