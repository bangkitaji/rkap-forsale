<div>
  <h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Master Data /</span> Activity ↔ COA Mapping
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

      <div class="d-flex justify-content-between align-items-center mb-4 gap-3 flex-wrap">
        <div class="position-relative" style="min-width: 60%;">
          <label class="form-label fw-semibold">Activity</label>
          <input
            type="text"
            class="form-control"
            wire:model.live.debounce.300ms="activitySearch"
            placeholder="Search activity by code/title..."
            autocomplete="off" />

          @if (!empty($activitySearch))
          <div class="dropdown-menu w-100 position-absolute mt-1 z-3 show" style="max-height: 260px; overflow:auto; top: 100%;">
            @forelse($activities as $activity)
            <button
              type="button"
              class="dropdown-item"
              wire:click="selectActivity({{ $activity->id }})">
              <strong>{{ $activity->code }}</strong> - {{ $activity->title }}
            </button>
            @empty
            <div class="px-3 py-2 text-muted small">No activity found.</div>
            @endforelse
          </div>
          @endif

          <div class="mt-2 d-flex align-items-left gap-2 flex-wrap">
            <span class="text-muted small">
              Selected:
              @if ($selectedActivity)
              <span class="badge bg-label-primary">
                <strong>{{ $selectedActivity->code }}</strong> - {{ $selectedActivity->title }}
              </span>
              @else
              <strong>None</strong>
              @endif
            </span>

            @if (!empty($activityId))
            <button
              type="button"
              class="btn btn-outline-secondary btn-xs"
              wire:click="clearActivity">
              Clear
            </button>
            @endif
          </div>
        </div>

        <div class="d-flex gap-2">
          <button wire:click="save" class="btn btn-primary btn-sm" @disabled(empty($activityId))>
            <i class="bx bx-save me-1"></i> Save Mapping
          </button>
          <button wire:click="openUploadModal()" class="btn btn-info btn-sm">
            <i class="bx bx-upload me-1"></i> Import Excel
          </button>
        </div>
      </div>

      <div class="mb-3">
        <div class="flex-grow-1" style="max-width: 40%;">
          <div class="input-group">
            <span class="input-group-text"><i class="bx bx-search"></i></span>
            <input
              type="text"
              class="form-control"
              wire:model.live.debounce.300ms="coaSearch"
              placeholder="Search COA by code/title/description...">
          </div>
        </div>

        <div class="text-muted small mt-2">
          Selected COAs: <strong>{{ count($selected ?? []) }}</strong>
        </div>
      </div>

      <div class="table-responsive text-nowrap">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th style="width: 60px;">Select</th>
              <th>COA Code</th>
              <th>Title</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody class="table-border-bottom-0">
            @forelse($coas as $coa)
            <tr>
              <td>
                <input
                  type="checkbox"
                  class="form-check-input"
                  value="{{ $coa->id }}"
                  wire:model.live="selectedCoaIds"
                  id="coa_{{ $coa->id }}">
              </td>
              <td><strong>{{ $coa->code }}</strong></td>
              <td>{{ $coa->title }}</td>
              <td class="text-wrap" style="max-width: 300px;">
                {{ $coa->description ?? '-' }}
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="4" class="text-center">No COA records found.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">
        {{ $coas->links() }}
      </div>
    </div>
  </div>

  {{-- Upload Modal --}}
  @if($isUploadModalOpen)
  <div class="modal fade show" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Import Activity-COA Mappings from Excel</h5>
          <button type="button" class="btn-close" wire:click="closeUploadModal()"></button>
        </div>
        <form wire:submit.prevent="importExcel">
          <div class="modal-body">
            <p class="mb-3">Upload an Excel file to import activity-COA mappings. <a href="{{ route('download-activity-coa-mapping-template') }}" class="btn btn-sm btn-link p-0">Download template</a></p>

            <div class="mb-3">
              <label for="uploadedFile" class="form-label">Excel File</label>
              <input type="file" id="uploadedFile" class="form-control @error('uploadedFile') is-invalid @enderror" wire:model="uploadedFile" accept=".xlsx,.xls,.csv">
              @error('uploadedFile') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            @if($importMessage)
            <div class="alert alert-{{ $importStatus === 'success' ? 'success' : 'danger' }} alert-dismissible" role="alert">
              <pre class="mb-0" style="font-size: 0.875rem; white-space: pre-wrap;">{{ $importMessage }}</pre>
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