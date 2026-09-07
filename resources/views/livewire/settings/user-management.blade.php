<div>
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">{{ __('Settings /') }}</span> {{ __('User Management') }}
    </h4>
    <div class="row">
        <div class="col-md-12">
            <div class="nav-align-top">
                <ul class="nav nav-pills flex-column flex-md-row mb-6 gap-md-0 gap-2">
                    <li class="nav-item">
                        <a class="nav-link active" href="javascript:void(0);"><i class="icon-base bx bx-user icon-sm me-1_5"></i> {{ __('Users') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('settings/manage-roles') }}"><i class="icon-base bx bx-group icon-sm me-1_5"></i> {{ __('Roles') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('settings/manage-permissions') }}"><i class="icon-base bx bx-link-alt icon-sm me-1_5"></i> {{ __('Permissions') }}</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header border-bottom mb-3">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <button type="button" class="nav-link @if($activeTab === 'users') active @endif" wire:click="$set('activeTab', 'users')" role="tab" aria-selected="true">
                        <i class="bx bx-user me-1"></i> {{ __('Users') }}
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link @if($activeTab === 'roles') active @endif" wire:click="$set('activeTab', 'roles')" role="tab" aria-selected="false">
                        <i class="bx bx-check-shield me-1"></i> {{ __('Roles') }}
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link @if($activeTab === 'permissions') active @endif" wire:click="$set('activeTab', 'permissions')" role="tab" aria-selected="false">
                        <i class="bx bx-key me-1"></i> {{ __('Permissions') }}
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
