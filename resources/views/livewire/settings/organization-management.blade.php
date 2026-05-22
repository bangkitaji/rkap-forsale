<div>
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Settings /</span> Manajemen Organisasi
    </h4>

    <div class="card">
        <div class="card-header border-bottom mb-3">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <button type="button" class="nav-link @if($activeTab === 'directorates') active @endif"
                        wire:click="$set('activeTab', 'directorates')" role="tab">
                        <i class="bx bx-building me-1"></i> Direktorat
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link @if($activeTab === 'departments') active @endif"
                        wire:click="$set('activeTab', 'departments')" role="tab">
                        <i class="bx bx-sitemap me-1"></i> Departemen
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link @if($activeTab === 'bureaus') active @endif"
                        wire:click="$set('activeTab', 'bureaus')" role="tab">
                        <i class="bx bx-folder me-1"></i> Biro
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content p-0 border-0">
                <div class="tab-pane fade show active" role="tabpanel">
                    @if($activeTab === 'directorates')
                        <livewire:settings.directorates-tab />
                    @elseif($activeTab === 'departments')
                        <livewire:settings.departments-tab />
                    @elseif($activeTab === 'bureaus')
                        <livewire:settings.bureaus-tab />
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
