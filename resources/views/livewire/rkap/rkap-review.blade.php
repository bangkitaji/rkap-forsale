<div>
  <style>
    /* Custom CSS Tooltip styling */
    .has-tooltip {
      position: relative;
      cursor: help;
    }

    .custom-tooltip-content {
      visibility: hidden;
      width: 520px;
      background-color: #2f3349;
      color: #ffffff;
      text-align: left;
      border-radius: 6px;
      padding: 10px;
      position: absolute;
      z-index: 1080;
      top: 110%;
      /* Position below the element */
      bottom: auto;
      left: 50%;
      transform: translateX(-50%);
      opacity: 0;
      transition: opacity 0.2s ease-in-out;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
      font-size: 0.72rem;
      line-height: 1.4;
      pointer-events: none;
      /* Make sure it doesn't block mouse movements */
      font-weight: normal;
    }

    .custom-tooltip-content::after {
      content: "";
      position: absolute;
      bottom: 100%;
      /* At the top of the tooltip */
      top: auto;
      left: 50%;
      margin-left: -5px;
      border-width: 5px;
      border-style: solid;
      border-color: transparent transparent #2f3349 transparent;
    }

    .has-tooltip:hover .custom-tooltip-content {
      visibility: visible;
      opacity: 1;
    }

    .tooltip-align-right {
      right: 0 !important;
      left: auto !important;
      transform: none !important;
    }

    .tooltip-align-right::after {
      left: auto !important;
      right: 15px !important;
      margin-left: 0 !important;
    }

    .table-responsive,
    .card,
    .card-header {
      overflow: visible !important;
    }

    .activity-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      transition: all 0.2s ease-in-out;
    }

    .activity-card:hover {
      box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
      border-color: #cbd5e1;
    }
  </style>
  <div class="d-flex justify-content-between align-items-center py-3 mb-4">
    <h4 class="mb-0">
      <span class="text-muted fw-light">RKAP / <a href="{{ route('rkap-submissions') }}"
          class="text-muted text-decoration-none">Pengajuan</a> /</span>
      Review RKAP
    </h4>
    <div>
      <span class="badge bg-{{ $submission->status_color }} fs-6">{{ $submission->status_label }}</span>
    </div>
  </div>

  @if (session()->has('message'))
    <div class="alert alert-success alert-dismissible" role="alert">
      {{ session('message') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="row">
    <!-- Main Content: RKAP Details -->
    <div class="col-xl-9 col-lg-8">
      <!-- Header Info -->
      <div class="card mb-4">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="text-muted small">Biro Pengaju</label>
              <div class="fw-semibold">{{ $submission->bureau->name ?? '-' }}</div>
              <div class="text-muted small">{{ $submission->bureau->department->directorate->name ?? '-' }}</div>
            </div>
            <div class="col-sm-3">
              <label class="text-muted small">Periode</label>
              <div class="fw-semibold">{{ $submission->period->title ?? '-' }}</div>
            </div>
            <div class="col-sm-3">
              <label class="text-muted small">Total Anggaran</label>
              <div class="fw-bold text-primary fs-5 has-tooltip">
                Rp {{ number_format($submission->total_budget, 0, ',', '.') }}
                <span class="custom-tooltip-content tooltip-align-right">
                  @php
                    $prevPeriod = $prevData['period'] ?? '-';
                    $prevTotal = $prevData['total_budget'] ?? 0;
                    $prevRealization = $prevData['total_realization'] ?? 0;
                    $prevProjection = $prevData['total_projection'] ?? 0;
                  @endphp
                  @if ($prevTotal > 0)
                    <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya
                      ({{ $prevPeriod }})</div>
                    <div class="row text-center">
                      <div class="col-4 border-end">
                        <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                        <div class="fw-bold text-white" style="font-size: 0.75rem;">Rp
                          {{ number_format($prevTotal, 0, ',', '.') }}</div>
                      </div>
                      <div class="col-4 border-end">
                        <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                        <div class="fw-bold text-white text-success" style="font-size: 0.75rem;">Rp
                          {{ number_format($prevRealization, 0, ',', '.') }}</div>
                      </div>
                      <div class="col-4">
                        <div class="text-white-50 small" style="font-size: 0.65rem;">Proyeksi</div>
                        <div class="fw-bold text-white text-warning" style="font-size: 0.75rem;">Rp
                          {{ number_format($prevProjection, 0, ',', '.') }}</div>
                      </div>
                    </div>
                  @else
                    <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
                  @endif
                </span>
              </div>
            </div>
          </div>
          @if ($submission->notes)
            <hr class="my-3">
            <label class="text-muted small">Catatan Pengajuan</label>
            <p class="mb-0">{{ $submission->notes }}</p>
          @endif
        </div>
      </div>

      <!-- Work Plans -->
      <h5 class="mb-3">Rincian Program Kerja</h5>
      @php
        $groupedWorkPlans = $submission->workPlans->groupBy('work_plan_id');
      @endphp
      @foreach ($groupedWorkPlans as $wpId => $wpGroup)
        @php
          $firstWp = $wpGroup->first();
          $workPlan = $firstWp->workPlan;
          $programCode = $workPlan ? $workPlan->code : ($firstWp->program_code ?: '-');
          $programName = $workPlan ? $workPlan->title : ($firstWp->program_name ?: 'Program Tanpa Nama');
          $wpGroupSubtotal = $wpGroup->sum(function ($wp) {
              return $wp->budgetItems->sum('total_price');
          });
        @endphp
        <div class="card mb-4 border-start border-primary border-3">
          <div class="card-header border-bottom py-3">
            <div class="d-flex align-items-center justify-content-between">
              <div class="d-flex flex-column">
                <div class="d-flex align-items-center gap-2">
                  <i class="bx bx-list-ul text-primary flex-shrink-0"></i>
                  <strong class="text-nowrap">Program Kerja {{ $loop->iteration }}</strong>
                </div>
                <div class="fw-semibold text-dark fs-5 mt-1">{{ $programCode }} — {{ $programName }}</div>
              </div>
              <div class="text-end">
                <span class="text-muted small d-block">Subtotal Program</span>
                <strong class="text-primary fs-5 has-tooltip">
                  Rp {{ number_format($wpGroupSubtotal, 0, ',', '.') }}
                  <span class="custom-tooltip-content tooltip-align-right">
                    @php
                      $prevProgramData =
                          $wpId && isset($prevData['map']['programs'][$wpId])
                              ? $prevData['map']['programs'][$wpId]
                              : null;
                      $prevPeriod = $prevData['period'] ?? '-';
                    @endphp
                    @if ($prevProgramData)
                      <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya
                        ({{ $prevPeriod }})</div>
                      <div class="row text-center">
                        <div class="col-4 border-end">
                          <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                          <div class="fw-bold text-white" style="font-size: 0.75rem;">Rp
                            {{ number_format($prevProgramData['budget'], 0, ',', '.') }}</div>
                        </div>
                        <div class="col-4 border-end">
                          <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                          <div class="fw-bold text-white text-success" style="font-size: 0.75rem;">Rp
                            {{ number_format($prevProgramData['realization'], 0, ',', '.') }}</div>
                        </div>
                        <div class="col-4">
                          <div class="text-white-50 small" style="font-size: 0.65rem;">Proyeksi</div>
                          <div class="fw-bold text-white text-warning" style="font-size: 0.75rem;">Rp
                            {{ number_format($prevProgramData['projection'] ?? 0, 0, ',', '.') }}</div>
                        </div>
                      </div>
                    @else
                      <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
                    @endif
                  </span>
                </strong>
              </div>
            </div>
          </div>

          <div class="card-body bg-light-gray p-3" style="background-color: #f8fafc;">
            @foreach ($wpGroup as $actIdx => $wp)
              @php
                $activity = $wp->activity;
                $activityCode = $activity ? $activity->code : ($wp->program_code ?: '-');
                $activityTitle = $activity ? $activity->title : ($wp->program_name ?: '-');
                $wpSubtotal = $wp->budgetItems->sum('total_price');
              @endphp
              <div class="activity-card p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-label-primary rounded-circle p-2"><i class="bx bx-task"></i></span>
                    <div>
                      <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                        Kegiatan {{ $actIdx + 1 }}
                        @if (($wp->approval_status ?? 'pending') === 'approved')
                          <span class="badge bg-label-success ms-2"
                            style="font-size: 0.7rem; padding: 0.2rem 0.4rem;"><i
                              class="bx bx-check-circle me-1"></i>Disetujui</span>
                        @elseif(($wp->approval_status ?? 'pending') === 'rejected')
                          <span class="badge bg-label-danger ms-2" style="font-size: 0.7rem; padding: 0.2rem 0.4rem;"><i
                              class="bx bx-x-circle me-1"></i>Revisi</span>
                        @else
                          <span class="badge bg-label-secondary ms-2"
                            style="font-size: 0.7rem; padding: 0.2rem 0.4rem;"><i
                              class="bx bx-time-five me-1"></i>Pending</span>
                        @endif
                      </h6>
                      <span class="text-muted small">{{ $activityCode }} — {{ $activityTitle }}</span>
                    </div>
                  </div>
                  <div class="text-end">
                    <span class="text-muted small d-block">Subtotal Kegiatan</span>
                    <strong class="text-dark has-tooltip">
                      Rp {{ number_format($wpSubtotal, 0, ',', '.') }}
                      <span class="custom-tooltip-content tooltip-align-right">
                        @php
                          $prevWpId = $wp->work_plan_id ?? null;
                          $prevActId = $wp->activity_id ?? null;
                          $actKey = $prevWpId && $prevActId ? "{$prevWpId}-{$prevActId}" : null;
                          $prevActivityData =
                              $actKey && isset($prevData['map']['activities'][$actKey])
                                  ? $prevData['map']['activities'][$actKey]
                                  : null;
                          $prevPeriod = $prevData['period'] ?? '-';
                        @endphp
                        @if ($prevActivityData)
                          <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode
                            Sebelumnya ({{ $prevPeriod }})</div>
                          <div class="row text-center">
                            <div class="col-4 border-end">
                              <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                              <div class="fw-bold text-white" style="font-size: 0.75rem;">Rp
                                {{ number_format($prevActivityData['budget'], 0, ',', '.') }}</div>
                            </div>
                            <div class="col-4 border-end">
                              <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                              <div class="fw-bold text-white text-success" style="font-size: 0.75rem;">Rp
                                {{ number_format($prevActivityData['realization'], 0, ',', '.') }}</div>
                            </div>
                            <div class="col-4">
                              <div class="text-white-50 small" style="font-size: 0.65rem;">Proyeksi</div>
                              <div class="fw-bold text-white text-warning" style="font-size: 0.75rem;">Rp
                                {{ number_format($prevActivityData['projection'] ?? 0, 0, ',', '.') }}</div>
                            </div>
                          </div>
                        @else
                          <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
                        @endif
                      </span>
                    </strong>
                  </div>
                </div>

                @if (($wp->approval_status ?? 'pending') === 'rejected' && !empty($wp->revision_notes))
                  <div class="alert alert-danger d-flex align-items-start mb-3 p-3 animate__animated animate__fadeIn"
                    role="alert">
                    <span class="badge bg-danger text-white me-3 p-1 mt-0.5"><i
                        class="bx bx-error-circle fs-5"></i></span>
                    <div>
                      <h6 class="alert-heading mb-1 fw-bold text-danger">Catatan Revisi dari Reviewer:</h6>
                      <span class="text-dark">{{ $wp->revision_notes }}</span>
                    </div>
                  </div>
                @endif

                @if ($wp->description)
                  <div class="mb-3">
                    <label class="text-muted small d-block">Deskripsi / Tujuan</label>
                    <p class="mb-0 text-dark" style="white-space: pre-line;">{{ $wp->description }}</p>
                  </div>
                @endif

                <div class="row g-2 mb-3 small">
                  <div class="col-auto">
                    <span class="text-muted">Target Output:</span> <span
                      class="fw-medium">{{ $wp->output_target ?? '-' }}</span>
                  </div>
                  <div class="col-auto ms-3">
                    <span class="text-muted">Volume:</span> <span class="fw-medium">{{ $wp->quantity }}
                      {{ $wp->unit }}</span>
                  </div>
                </div>

                <div class="table-responsive">
                  <table class="table table-sm table-striped table-hover mb-0">
                    <thead>
                      <tr>
                        <th>Uraian & Detail Belanja</th>
                        <th class="text-center" style="width: 10%;">Vol</th>
                        <th style="width: 12%;">Sat</th>
                        <th class="text-end" style="width: 14%;">Harga Satuan</th>
                        <th class="text-end" style="width: 14%;">Total</th>
                        <th class="text-center" style="width: 8%;">Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($wp->budgetItems->groupBy('account_code') as $accountCode => $items)
                        @php
                          $firstItem = $items->first();
                          $coaGroupSubtotal = $items->sum('total_price');
                          $prevWpId = $wp->work_plan_id ?? null;
                          $prevCode = $accountCode;
                          $prevAmount =
                              $prevWpId && $prevCode && !empty($prevData['map'][$prevWpId][$prevCode])
                                  ? $prevData['map'][$prevWpId][$prevCode]
                                  : null;
                          $prevPeriod = $prevData['period'] ?? null;
                        @endphp
                        <tr class="table-light fw-semibold">
                          <td colspan="6" class="text-dark bg-lighter py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                              <div class="d-flex align-items-center gap-1 min-w-0">
                                <i class="bx bx-subdirectory-right text-primary flex-shrink-0"></i>
                                <span class="text-truncate"><strong>{{ $accountCode ?? '-' }}</strong> —
                                  {{ $firstItem->description }}</span>
                              </div>
                              <div class="d-flex align-items-center gap-3 flex-shrink-0 text-end">
                                <div class="has-tooltip" style="font-size:0.78rem; line-height:1.2;">
                                  <div class="text-muted" style="white-space:nowrap; font-size:0.68rem;">Sub-total
                                  </div>
                                  <div class="fw-bold text-primary" style="white-space:nowrap;">
                                    Rp {{ number_format($coaGroupSubtotal, 0, ',', '.') }}
                                  </div>
                                  <span class="custom-tooltip-content tooltip-align-right">
                                    @php
                                      $prevActId = $wp->activity_id ?? null;
                                      $coaKey =
                                          $prevWpId && $prevActId && $prevCode
                                              ? "{$prevWpId}-{$prevActId}-{$prevCode}"
                                              : null;
                                      $prevCoaData =
                                          $coaKey && isset($prevData['map']['coas'][$coaKey])
                                              ? $prevData['map']['coas'][$coaKey]
                                              : null;
                                      $prevPeriod = $prevData['period'] ?? '-';
                                    @endphp
                                    @if ($prevCoaData)
                                      <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP
                                        Periode Sebelumnya ({{ $prevPeriod }})</div>
                                      <div class="row text-center">
                                        <div class="col-4 border-end">
                                          <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran</div>
                                          <div class="fw-bold text-white" style="font-size: 0.75rem;">Rp
                                            {{ number_format($prevCoaData['budget'], 0, ',', '.') }}</div>
                                        </div>
                                        <div class="col-4 border-end">
                                          <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi</div>
                                          <div class="fw-bold text-white text-success" style="font-size: 0.75rem;">Rp
                                            {{ number_format($prevCoaData['realization'], 0, ',', '.') }}</div>
                                        </div>
                                        <div class="col-4">
                                          <div class="text-white-50 small" style="font-size: 0.65rem;">Proyeksi</div>
                                          <div class="fw-bold text-white text-warning" style="font-size: 0.75rem;">Rp
                                            {{ number_format($prevCoaData['projection'] ?? 0, 0, ',', '.') }}</div>
                                        </div>
                                      </div>
                                    @else
                                      <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya
                                      </div>
                                    @endif
                                  </span>
                                </div>
                              </div>
                            </div>
                          </td>
                        </tr>
                        @foreach ($items as $bi)
                          @php
                            $allocationModalId = 'allocationDetailModal-' . $bi->id;
                            $monthNames = [
                                1 => 'Jan',
                                2 => 'Feb',
                                3 => 'Mar',
                                4 => 'Apr',
                                5 => 'Mei',
                                6 => 'Jun',
                                7 => 'Jul',
                                8 => 'Agu',
                                9 => 'Sep',
                                10 => 'Okt',
                                11 => 'Nov',
                                12 => 'Des',
                            ];
                          @endphp
                          <tr>
                            <td>
                              {{ $bi->remarks ?: $bi->description }}
                            </td>
                            <td class="text-center">
                              @if ($bi->unit_2)
                                <div>{{ $bi->quantity }}</div>
                                <div class="text-muted small border-top mt-1 pt-1">{{ $bi->quantity_2 }}</div>
                              @else
                                {{ $bi->quantity }}
                              @endif
                            </td>
                            <td>
                              @if ($bi->unit_2)
                                <div>{{ $bi->unit }}</div>
                                <div class="text-muted small border-top mt-1 pt-1">{{ $bi->unit_2 }}</div>
                              @else
                                {{ $bi->unit }}
                              @endif
                            </td>
                            <td class="text-end">Rp {{ number_format($bi->unit_price, 0, ',', '.') }}</td>
                            <td class="text-end">
                              <div class="fw-semibold text-primary">Rp
                                {{ number_format($bi->total_price, 0, ',', '.') }}</div>
                            </td>
                            <td class="text-center">
                              @if ($bi->monthlies->isNotEmpty() || $bi->cashOuts->isNotEmpty())
                                <div class="d-flex justify-content-center">
                                  <button type="button" class="btn btn-xs btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#{{ $allocationModalId }}"
                                    title="Detail Alokasi">
                                    <i class="bx bx-detail"></i>
                                  </button>

                                  <!-- Modal Detail Alokasi (Merged) -->
                                  <div class="modal fade" id="{{ $allocationModalId }}" tabindex="-1"
                                    aria-hidden="true" wire:key="allocation-modal-{{ $bi->id }}">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                      <div class="modal-content text-start">
                                        <div class="modal-header">
                                          <h5 class="modal-title d-flex align-items-center">
                                            <i class="bx bx-info-circle me-2 text-primary fs-4"></i>Detail Alokasi
                                            Anggaran
                                          </h5>
                                          <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                          <!-- Banner Informasi Rincian Belanja -->
                                          <div class="card bg-lighter shadow-none border mb-4">
                                            <div class="card-body py-3 px-4">
                                              <div class="row g-3 small">
                                                <div class="col-md-3 border-end">
                                                  <span class="text-muted d-block mb-1">Kode Akun</span>
                                                  <span
                                                    class="fw-semibold text-dark fs-6">{{ $bi->account_code ?? '-' }}</span>
                                                </div>
                                                <div class="col-md-5 border-end">
                                                  <span class="text-muted d-block mb-1">Deskripsi / Detail
                                                    Belanja</span>
                                                  <span
                                                    class="fw-semibold text-dark fs-6 text-wrap">{{ $bi->description }}</span>
                                                  @if ($bi->remarks)
                                                    <div class="text-muted mt-1 small">Ket: {{ $bi->remarks }}</div>
                                                  @endif
                                                </div>
                                                <div class="col-md-2 border-end">
                                                  <span class="text-muted d-block mb-1">Volume</span>
                                                  <span class="fw-semibold text-dark fs-6">
                                                    @if ($bi->unit_2)
                                                      {{ $bi->quantity }} {{ $bi->unit }} x {{ $bi->quantity_2 }}
                                                      {{ $bi->unit_2 }}
                                                    @else
                                                      {{ $bi->quantity }} {{ $bi->unit }}
                                                    @endif
                                                  </span>
                                                </div>
                                                <div class="col-md-2">
                                                  <span class="text-muted d-block mb-1">Total Anggaran</span>
                                                  <span class="fw-bold text-primary fs-6">Rp
                                                    {{ number_format($bi->total_price, 0, ',', '.') }}</span>
                                                </div>
                                              </div>
                                            </div>
                                          </div>

                                          <!-- Side-by-side Tables (Only months with values) -->
                                          <div class="row g-4">
                                            <!-- Left Column: Distribusi Bulanan -->
                                            <div class="col-md-6">
                                              <div class="border rounded p-3 h-100">
                                                <h6 class="fw-semibold mb-3 text-primary d-flex align-items-center">
                                                  <i class="bx bx-calendar me-2"></i>Distribusi Bulanan
                                                </h6>
                                                @php
                                                  $activeMonthlies = $bi->monthlies->filter(
                                                      fn($m) => (float) $m->amount > 0,
                                                  );
                                                @endphp
                                                @if ($activeMonthlies->isEmpty())
                                                  <div class="text-center text-muted py-4">
                                                    <i class="bx bx-info-circle fs-3 mb-2 d-block"></i>
                                                    <span class="small">Tidak ada data distribusi bulanan</span>
                                                  </div>
                                                @else
                                                  <div class="table-responsive">
                                                    <table class="table table-sm table-hover align-middle mb-0">
                                                      <thead>
                                                        <tr>
                                                          <th>Bulan</th>
                                                          <th class="text-end">Jumlah</th>
                                                          <th class="text-center" style="width: 25%">Porsi</th>
                                                        </tr>
                                                      </thead>
                                                      <tbody>
                                                        @foreach ($activeMonthlies as $monthlyRecord)
                                                          @php
                                                            $percentage =
                                                                $bi->total_price > 0
                                                                    ? ($monthlyRecord->amount / $bi->total_price) * 100
                                                                    : 0;
                                                          @endphp
                                                          <tr class="fw-medium text-primary">
                                                            <td>{{ $monthNames[$monthlyRecord->month] }}</td>
                                                            <td class="text-end">Rp
                                                              {{ number_format($monthlyRecord->amount, 0, ',', '.') }}
                                                            </td>
                                                            <td class="text-center">
                                                              <span
                                                                class="badge bg-label-primary">{{ number_format($percentage, 0) }}%</span>
                                                            </td>
                                                          </tr>
                                                        @endforeach
                                                      </tbody>
                                                    </table>
                                                  </div>
                                                @endif
                                              </div>
                                            </div>

                                            <!-- Right Column: Rencana Kas Keluar -->
                                            <div class="col-md-6">
                                              <div class="border rounded p-3 h-100">
                                                <h6 class="fw-semibold mb-3 text-success d-flex align-items-center">
                                                  <i class="bx bx-wallet me-2"></i>Rencana Kas Keluar
                                                </h6>
                                                @php
                                                  $activeCashOuts = $bi->cashOuts->filter(
                                                      fn($c) => (float) $c->amount > 0,
                                                  );
                                                @endphp
                                                @if ($activeCashOuts->isEmpty())
                                                  <div class="text-center text-muted py-4">
                                                    <i class="bx bx-info-circle fs-3 mb-2 d-block"></i>
                                                    <span class="small">Tidak ada data rencana kas keluar</span>
                                                  </div>
                                                @else
                                                  <div class="table-responsive">
                                                    <table class="table table-sm table-hover align-middle mb-0">
                                                      <thead>
                                                        <tr>
                                                          <th>Bulan</th>
                                                          <th class="text-end">Jumlah</th>
                                                          <th class="text-center" style="width: 25%">Porsi</th>
                                                        </tr>
                                                      </thead>
                                                      <tbody>
                                                        @foreach ($activeCashOuts as $cashOutRecord)
                                                          @php
                                                            $percentage =
                                                                $bi->total_price > 0
                                                                    ? ($cashOutRecord->amount / $bi->total_price) * 100
                                                                    : 0;
                                                          @endphp
                                                          <tr class="fw-medium text-success">
                                                            <td>{{ $monthNames[$cashOutRecord->month] }}</td>
                                                            <td class="text-end">Rp
                                                              {{ number_format($cashOutRecord->amount, 0, ',', '.') }}
                                                            </td>
                                                            <td class="text-center">
                                                              <span
                                                                class="badge bg-label-success">{{ number_format($percentage, 0) }}%</span>
                                                            </td>
                                                          </tr>
                                                        @endforeach
                                                      </tbody>
                                                    </table>
                                                  </div>
                                                @endif
                                              </div>
                                            </div>
                                          </div>
                                        </div>
                                        <div class="modal-footer">
                                          <button type="button" class="btn btn-outline-secondary"
                                            data-bs-dismiss="modal">Tutup</button>
                                        </div>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                              @endif
                            </td>
                          </tr>
                        @endforeach
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      @endforeach
    </div>

    <!-- Sidebar: Actions & History -->
    <div class="col-xl-3 col-lg-4">



      <!-- Comments / Discussion -->
      <div class="card mb-4">
        <div class="card-header border-bottom">
          <h5 class="mb-0"><i class="bx bx-message-rounded-dots me-2"></i>Pembahasan</h5>
        </div>
        <div class="card-body mt-3" style="max-height: 400px; overflow-y: auto;">
          @forelse($submission->comments as $comment)
            <div class="d-flex mb-3">
              <div class="avatar avatar-sm me-3 flex-shrink-0">
                <span
                  class="avatar-initial rounded-circle bg-label-primary">{{ substr($comment->user->name, 0, 2) }}</span>
              </div>
              <div class="w-100">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <h6 class="mb-0">{{ $comment->user->name }}</h6>
                  <small class="text-muted">{{ $comment->created_at->diffForHumans() }}</small>
                </div>
                <div class="p-2 bg-lighter rounded small">
                  {{ $comment->content }}
                </div>
              </div>
            </div>
          @empty
            <div class="text-center text-muted my-3">
              <small>Belum ada pembahasan.</small>
            </div>
          @endforelse
        </div>
        <div class="card-footer border-top">
          <div class="input-group">
            <input type="text" class="form-control @error('newComment') is-invalid @enderror"
              wire:model.defer="newComment" placeholder="Ketik pesan..." wire:keydown.enter="addComment">
            <button class="btn btn-primary" type="button" wire:click="addComment"><i
                class="bx bx-send"></i></button>
          </div>
          @error('newComment')
            <div class="text-danger small mt-1">{{ $message }}</div>
          @enderror
        </div>
      </div>

      <!-- Approval History -->
      <div class="card">
        <div class="card-header border-bottom">
          <h5 class="mb-0"><i class="bx bx-history me-2"></i>Riwayat Persetujuan</h5>
        </div>
        <div class="card-body mt-3">
          <ul class="timeline mb-0">
            @foreach ($submission->approvals as $approval)
              <li class="timeline-item timeline-item-transparent ps-4">
                <span class="timeline-point timeline-point-{{ $approval->action_color }}"></span>
                <div class="timeline-event">
                  <div class="timeline-header mb-1">
                    <h6 class="mb-0">{{ $approval->action_label }}</h6>
                    <small class="text-muted">{{ $approval->created_at->format('d M Y, H:i') }}</small>
                  </div>
                  <p class="mb-0 small">Oleh: <strong>{{ $approval->user->name }}</strong>
                    ({{ \Illuminate\Support\Str::headline($approval->role) }})</p>
                  @if ($approval->comments)
                    <div
                      class="mt-2 p-2 bg-lighter rounded small border-start border-{{ $approval->action_color }} border-3">
                      <em>"{{ $approval->comments }}"</em>
                    </div>
                  @endif
                </div>
              </li>
            @endforeach
            <li class="timeline-item timeline-item-transparent ps-4">
              <span class="timeline-point timeline-point-secondary"></span>
              <div class="timeline-event pb-0">
                <div class="timeline-header mb-1">
                  <h6 class="mb-0">Diajukan</h6>
                  <small class="text-muted">{{ $submission->created_at->format('d M Y, H:i') }}</small>
                </div>
                <p class="mb-0 small">Oleh: <strong>{{ $submission->creator->name ?? '-' }}</strong></p>
              </div>
            </li>
          </ul>
        </div>
      </div>

    </div>
  </div>
</div>
