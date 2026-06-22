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

    /* Scoped Horizontal Timeline Styles */
    .timeline-steps-container {
      overflow-x: auto;
      padding: 15px 5px;
      width: 100%;
    }

    .timeline-steps-wrapper {
      position: relative;
      padding: 10px 0;
      width: max-content;
      min-width: 100%;
    }

    .timeline-steps-line {
      position: absolute;
      top: 25px;
      height: 4px;
      background: #e2e8f0;
      z-index: 1;
      border-radius: 2px;
    }

    .timeline-step-item {
      width: 200px;
      text-align: center;
      position: relative;
      z-index: 2;
      transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      flex-shrink: 0;
    }

    .timeline-step-item:hover {
      transform: translateY(-4px);
    }

    .timeline-step-dot {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background-color: #fff;
      border: 4px solid #fff;
      box-shadow: 0 0 0 2px #e2e8f0, 0 4px 10px rgba(0, 0, 0, 0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto;
      color: #fff;
      transition: all 0.2s ease;
    }

    .timeline-step-dot.bg-success {
      background-color: #28c76f !important;
      box-shadow: 0 0 0 2px rgba(40, 199, 111, 0.15), 0 4px 10px rgba(0, 0, 0, 0.08);
    }
    .timeline-step-dot.bg-danger {
      background-color: #ea5455 !important;
      box-shadow: 0 0 0 2px rgba(234, 84, 85, 0.15), 0 4px 10px rgba(0, 0, 0, 0.08);
    }
    .timeline-step-dot.bg-warning {
      background-color: #ff9f43 !important;
      box-shadow: 0 0 0 2px rgba(255, 159, 67, 0.15), 0 4px 10px rgba(0, 0, 0, 0.08);
    }
    .timeline-step-dot.bg-secondary {
      background-color: #8592a3 !important;
      box-shadow: 0 0 0 2px rgba(133, 146, 163, 0.15), 0 4px 10px rgba(0, 0, 0, 0.08);
    }

    .timeline-step-item:hover .timeline-step-dot.bg-success {
      box-shadow: 0 0 0 4px rgba(40, 199, 111, 0.3), 0 6px 15px rgba(40, 199, 111, 0.2);
    }
    .timeline-step-item:hover .timeline-step-dot.bg-danger {
      box-shadow: 0 0 0 4px rgba(234, 84, 85, 0.3), 0 6px 15px rgba(234, 84, 85, 0.2);
    }
    .timeline-step-item:hover .timeline-step-dot.bg-warning {
      box-shadow: 0 0 0 4px rgba(255, 159, 67, 0.3), 0 6px 15px rgba(255, 159, 67, 0.2);
    }
    .timeline-step-item:hover .timeline-step-dot.bg-secondary {
      box-shadow: 0 0 0 4px rgba(133, 146, 163, 0.3), 0 6px 15px rgba(133, 146, 163, 0.2);
    }

    .timeline-step-content {
      margin-top: 12px;
    }

    .timeline-step-badge {
      font-size: 0.72rem;
      font-weight: 600;
      padding: 0.35em 0.8em;
      border-radius: 4px;
      display: inline-block;
      margin-bottom: 6px;
    }

    .timeline-step-title {
      font-size: 0.82rem;
      font-weight: 700;
      color: #4b4b4b;
      margin-bottom: 2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .timeline-step-subtitle {
      font-size: 0.72rem;
      color: #a1a1a1;
      font-weight: 500;
    }

    .timeline-step-time {
      font-size: 0.68rem;
      color: #b3b3b3;
      margin-top: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 3px;
    }

    .timeline-step-comment {
      margin-top: 8px;
      padding: 8px 10px;
      background-color: #fdfdfd;
      border: 1px solid #eef2f6;
      border-left: 3px solid;
      border-radius: 0 4px 4px 0;
      font-size: 0.72rem;
      text-align: left;
      max-width: 180px;
      margin-left: auto;
      margin-right: auto;
      box-shadow: 0 1px 3px rgba(0,0,0,0.03);
      font-style: italic;
      color: #5c5c5c;
      word-break: break-word;
    }
    .timeline-step-comment.border-success { border-left-color: #28c76f !important; }
    .timeline-step-comment.border-danger { border-left-color: #ea5455 !important; }
    .timeline-step-comment.border-warning { border-left-color: #ff9f43 !important; }
    .timeline-step-comment.border-secondary { border-left-color: #8592a3 !important; }
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
    <div class="col-12">
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

      <!-- Riwayat Persetujuan -->
      <div class="card mb-4">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="bx bx-history me-2"></i>Riwayat Persetujuan</h5>
          <small class="text-muted">Kronologi persetujuan dari kiri ke kanan</small>
        </div>
        <div class="card-body py-4 timeline-steps-container">
          <div class="timeline-steps-wrapper">
            @if ($submission->approvals->count() > 0)
              <div class="timeline-steps-line" style="width: {{ 260 * $submission->approvals->count() }}px; left: 100px;"></div>
            @endif

            <div class="d-flex align-items-start justify-content-start" style="gap: 60px;">
              <!-- Start Node: Diajukan -->
              <div class="timeline-step-item">
                <div class="timeline-step-dot bg-secondary">
                  <i class="bx bx-send" style="font-size: 1.25rem;"></i>
                </div>
                <div class="timeline-step-content">
                  <span class="timeline-step-badge bg-label-secondary">Diajukan</span>
                  <div class="timeline-step-title" title="{{ $submission->creator->name ?? '-' }}">{{ $submission->creator->name ?? '-' }}</div>
                  <div class="timeline-step-subtitle">Pembuat Pengajuan</div>
                  <div class="timeline-step-time">
                    <i class="bx bx-calendar"></i>
                    <span>{{ $submission->created_at->format('d M Y, H:i') }}</span>
                  </div>
                </div>
              </div>

              <!-- Approval Steps (Chronological) -->
              @foreach ($submission->approvals->reverse() as $approval)
                <div class="timeline-step-item">
                  <div class="timeline-step-dot bg-{{ $approval->action_color }}">
                    @if($approval->action === 'approved')
                      <i class="bx bx-check" style="font-size: 1.25rem;"></i>
                    @elseif($approval->action === 'revision_requested')
                      <i class="bx bx-refresh" style="font-size: 1.25rem;"></i>
                    @elseif($approval->action === 'rejected')
                      <i class="bx bx-x" style="font-size: 1.25rem;"></i>
                    @else
                      <i class="bx bx-time-five" style="font-size: 1.25rem;"></i>
                    @endif
                  </div>
                  <div class="timeline-step-content">
                    <span class="timeline-step-badge bg-label-{{ $approval->action_color }}">{{ $approval->action_label }}</span>
                    <div class="timeline-step-title" title="{{ $approval->user->name }}">{{ $approval->user->name }}</div>
                    <div class="timeline-step-subtitle">{{ \Illuminate\Support\Str::headline($approval->role) }}</div>
                    <div class="timeline-step-time">
                      <i class="bx bx-calendar"></i>
                      <span>{{ $approval->created_at->format('d M Y, H:i') }}</span>
                    </div>
                    @if ($approval->comments)
                      <div class="timeline-step-comment border-{{ $approval->action_color }}">
                        {{ $approval->comments }}
                      </div>
                    @endif
                  </div>
                </div>
              @endforeach
            </div>
          </div>
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
  </div>
</div>

