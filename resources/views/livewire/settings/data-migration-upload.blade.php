<div class="container-xxl flex-grow-1 container-p-y">
  <div class="row">
    <div class="col-12">
      <div class="card mb-4">
        <h5 class="card-header">Upload Data Migration RKAP</h5>
        <div class="card-body">
          <p class="text-muted mb-3">
            Upload file Excel (.xlsx, .xls) atau CSV sesuai template untuk import data RKAP Submission.
          </p>

          <div class="alert alert-info mb-4" role="alert">
            <strong>Template:</strong>
            <a href="{{ asset('rkap_migration_template.csv') }}" class="alert-link" download>
              Download rkap_migration_template.csv
            </a>
          </div>

          <form wire:submit.prevent="uploadAndImport" class="mb-4">
            <div class="mb-3">
              <label class="form-label">File Excel / CSV</label>
              <input type="file" class="form-control" wire:model="file"
                wire:loading.attr="disabled" wire:target="file, uploadAndImport"
                accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" />
              @error('file')
              <div class="text-danger mt-1">{{ $message }}</div>
              @enderror
            </div>

            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="file, uploadAndImport">
              <span wire:loading.remove wire:target="file, uploadAndImport">
                Upload & Import
              </span>
              <span wire:loading wire:target="file, uploadAndImport">
                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                Processing...
              </span>
            </button>
          </form>

          @if (!empty($errorsList))
          <div class="alert alert-danger">
            <h6 class="alert-heading mb-2">Validasi gagal</h6>
            <ul class="mb-0 ps-3">
              @foreach ($errorsList as $err)
              <li>{{ $err }}</li>
              @endforeach
            </ul>
          </div>
          @endif

          @if ($imported)
          <div class="alert alert-success">
            <h6 class="alert-heading mb-2">Import berhasil</h6>
            <ul class="mb-0 ps-3">
              <li>Submissions: {{ $importSummary['submissions'] ?? 0 }}</li>
              <li>Work Plans: {{ $importSummary['work_plans'] ?? 0 }}</li>
              <li>Budget Items: {{ $importSummary['budget_items'] ?? 0 }}</li>
              <li>Monthly Distributions: {{ $importSummary['monthlies'] ?? 0 }}</li>
              <li>Cash Out Plans: {{ $importSummary['cash_outs'] ?? 0 }}</li>
            </ul>
          </div>
          @endif

          <div class="border rounded p-3 bg-lighter">
            <p class="mb-2 fw-semibold">Catatan format kolom minimum:</p>
            <code>submission_key, rkap_period_id, bureau_id, created_by, work_plan_key, work_plan_id, budget_item_key, coa_id, bi_quantity, unit_price, m1..m12, co1..co12</code>
            <p class="text-muted mt-2 mb-0"><small>Kolom opsional: status, current_version, notes, activity_id, wp_description, output_target, wp_unit, wp_quantity, sort_order, bi_unit, remarks</small></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>