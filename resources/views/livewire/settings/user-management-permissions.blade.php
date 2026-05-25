<div>
  <div class="d-flex justify-content-between align-items-start mb-4">
    <h4 class="py-3 mb-0">
      <span class="text-muted fw-light">Settings /</span> User Management
    </h4>

    <!-- <div class="btn-group" role="group" aria-label="User management navigation">
      <a href="{{ route('settings-user-management-users') }}" class="btn btn-sm btn-outline-primary @if(request()->routeIs('settings-user-management-users')) active @endif">
        <i class="bx bx-user me-1"></i> Users
      </a>
      <a href="{{ route('settings-user-management-roles') }}" class="btn btn-sm btn-outline-primary @if(request()->routeIs('settings-user-management-roles')) active @endif">
        <i class="bx bx-check-shield me-1"></i> Roles
      </a>
      <a href="{{ route('settings-user-management-permissions') }}" class="btn btn-sm btn-outline-primary @if(request()->routeIs('settings-user-management-permissions')) active @endif">
        <i class="bx bx-key me-1"></i> Permissions
      </a>
    </div> -->
  </div>
  <div class="row">
    <div class="col-md-12">
      <div class="nav-align-top">
        <ul class="nav nav-pills flex-column flex-md-row mb-6 gap-md-0 gap-2">
          <li class="nav-item">
            <a class="nav-link" href="{{ route('settings-user-management-users') }}"><i class="icon-base bx bx-user icon-sm me-1_5"></i> Users</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="{{ route('settings-user-management-roles') }}"><i class="icon-base bx bx-check-shield icon-sm me-1_5"></i> Roles</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="javascript:void(0);"><i class="icon-base bx bx-key icon-sm me-1_5"></i> Permissions</a>
          </li>
        </ul>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-body p-4">
      <livewire:settings.permissions-tab />
    </div>
  </div>
</div>