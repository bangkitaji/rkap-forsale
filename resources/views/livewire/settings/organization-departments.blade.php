<div>
  <div class="d-flex justify-content-between align-items-start mb-4">
    <h4 class="py-2 mb-0">
      <span class="text-muted fw-light">Settings /</span> Manajemen Organisasi
    </h4>
  </div>
  <div class="row">
    <div class="col-md-12">
      <div class="nav-align-top">
        <ul class="nav nav-pills flex-column flex-md-row mb-6 gap-md-0 gap-2">
          <li class="nav-item">
            <a class="nav-link" href="{{ route('settings-organization-directorates') }}"><i class="icon-base bx bx-building icon-sm me-1_5"></i> Direktorat</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="javascript:void(0);"><i class="icon-base bx bx-sitemap icon-sm me-1_5"></i> Departemen</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="{{ route('settings-organization-bureaus') }}"><i class="icon-base bx bx-folder icon-sm me-1_5"></i> Biro</a>
          </li>
        </ul>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-body p-4">
      <livewire:settings.departments-tab />
    </div>
  </div>
</div>
