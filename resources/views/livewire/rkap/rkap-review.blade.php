<div>
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
              <label class="text-muted small">{{ __('Biro Pengaju') }}</label>
              <div class="fw-semibold">{{ $submission->bureau->name ?? '-' }}</div>
              <div class="text-muted small">{{ $submission->bureau->department->directorate->name ?? '-' }}</div>
            </div>
            <div class="col-sm-3">
              <label class="text-muted small">{{ __('Periode') }}</label>
              <div class="fw-semibold">{{ $submission->period->title ?? '-' }}</div>
            </div>
            <div class="col-sm-3">
              <label class="text-muted small">{{ __('Total Anggaran') }}</label>
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
                  <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">{{ __('RKAP Periode Sebelumnya') }}
                    ({{ $prevPeriod }})</div>
                  <div class="row text-center">
                    <div class="col-4 border-end">
                      <div class="text-white-50 small rkap-font-065">{{ __('Anggaran') }}</div>
                      <div class="fw-bold text-white rkap-font-075">Rp
                        {{ number_format($prevTotal, 0, ',', '.') }}
                      </div>
                    </div>
                    <div class="col-4 border-end">
                      <div class="text-white-50 small rkap-font-065">{{ __('Realisasi') }}</div>
                      <div class="fw-bold text-white text-success rkap-font-075">Rp
                        {{ number_format($prevRealization, 0, ',', '.') }}
                      </div>
                    </div>
                    <div class="col-4">
                      <div class="text-white-50 small rkap-font-065">{{ __('Proyeksi') }}</div>
                      <div class="fw-bold text-white text-warning rkap-font-075">Rp
                        {{ number_format($prevProjection, 0, ',', '.') }}
                      </div>
                    </div>
                  </div>
                  @else
                  <div class="text-center text-white-50 py-1">{{ __('Tidak ada data di periode sebelumnya') }}</div>
                  @endif
                </span>
              </div>
            </div>
          </div>
          @if ($submission->notes)
          <hr class="my-3">
          <label class="text-muted small">{{ __('Catatan Pengajuan') }}</label>
          <p class="mb-0">{{ $submission->notes }}</p>
          @endif
        </div>
      </div>

      <!-- Riwayat Persetujuan -->
      <div class="card mb-4">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="bx bx-history me-2"></i>{{ __('Riwayat Persetujuan') }}</h5>
          <small class="text-muted">{{ __('Kronologi persetujuan dari kiri ke kanan') }}</small>
        </div>
        <div class="card-body py-2 timeline-steps-container">
          <div class="timeline-steps-wrapper">
            @php
            $allTimelineSteps = collect();

            $allTimelineSteps->push([
            'type' => 'creator',
            'role' => 'Pembuat Pengajuan',
            'name' => $submission->creator->name ?? '-',
            'status' => 'Diajukan',
            'color' => 'secondary',
            'time' => $submission->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i'),
            'comment' => null,
            'icon' => 'bx bx-send'
            ]);

            foreach ($submission->approvals->reverse() as $approval) {
            $icon = 'bx bx-time-five';
            if ($approval->action === 'approved') {
            $icon = 'bx bx-check';
            } elseif ($approval->action === 'revision_requested') {
            $icon = 'bx bx-refresh';
            } elseif ($approval->action === 'rejected') {
            $icon = 'bx bx-x';
            }

            $allTimelineSteps->push([
            'type' => 'approval',
            'role' => \Illuminate\Support\Str::headline($approval->role),
            'name' => $approval->user->name,
            'status' => $approval->action_label,
            'color' => $approval->action_color,
            'time' => $approval->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i'),
            'comment' => $approval->comments,
            'icon' => $icon
            ]);
            }
            @endphp

            @if ($allTimelineSteps->count() > 1)
            <div class="timeline-steps-line" :style="{ width: '{{ 165 * ($allTimelineSteps->count() - 1) }}px', left: '75px' }"></div>
            @endif

            {{--
              Layout per column (top-to-bottom, 260px total height):
              [timeline-zone-top    118px] — card floats down to bottom edge for ODD steps
              [timeline-zone-axis    24px] — node-dot on top, numbered-circle below (or reversed for even)
              [timeline-zone-bottom 118px] — card floats up to top edge for EVEN steps

              ODD  steps (1, 3, 5…): card is ABOVE the axis → top zone has card, axis has dot-above-circle
              EVEN steps (2, 4, 6…): card is BELOW the axis → bottom zone has card, axis has circle-above-dot
            --}}
            <div class="d-flex align-items-stretch justify-content-start rkap-z-2 gap-15px position-relative">
              @foreach ($allTimelineSteps as $step)
              @php
              $isOdd = $loop->iteration % 2 !== 0;
              $themeColor = match($step['color']) {
              'success' => '#28c76f',
              'danger' => '#ea5455',
              'warning' => '#ff9f43',
              'secondary' => '#8592a3',
              default => '#7367f0',
              };
              @endphp

              <div class="timeline-step-column">

                @if ($isOdd)
                {{-- ODD: card above axis, circle just above node-dot --}}

                {{-- Top zone: card floats to bottom of this zone --}}
                <div class="timeline-zone-top">
                  <div class="timeline-card arrow-down" :style="{ '--theme-color': '{{ $themeColor }}', 'border-color': '{{ $themeColor }}' }">
                    <div class="timeline-card-header" :style="{ 'background-color': '{{ $themeColor }}' }">
                      <i class="{{ $step['icon'] }} me-1 rkap-font-085"></i> {{ $step['status'] }}
                    </div>
                    <div class="timeline-card-body text-center py-1 px-2">
                      <div class="fw-semibold text-dark text-truncate mb-0.5 rkap-font-062" title="{{ $step['name'] }}">
                        {{ $step['name'] }}
                      </div>
                      <div class="text-muted rkap-font-052 text-nowrap">
                        <i class="bx bx-calendar me-0.5 rkap-font-06"></i>{{ $step['time'] }}
                      </div>
                    </div>
                  </div>
                </div>

                {{-- Axis zone: numbered circle (top), then node-dot (bottom) --}}
                <div class="timeline-zone-axis d-flex flex-column justify-content-center gap-2px">
                  <div class="timeline-step-circle border-{{ $step['color'] }}" title="{{ $step['role'] }}">
                    {{ $loop->iteration }}
                  </div>
                  <div class="timeline-node-dot bg-{{ $step['color'] }}"></div>
                </div>

                {{-- Bottom zone: empty for odd steps --}}
                <div class="timeline-zone-bottom"></div>

                @else
                {{-- EVEN: card below axis, circle just below node-dot --}}

                {{-- Top zone: empty for even steps --}}
                <div class="timeline-zone-top"></div>

                {{-- Axis zone: node-dot (top), then numbered circle (bottom) --}}
                <div class="timeline-zone-axis d-flex flex-column justify-content-center gap-2px">
                  <div class="timeline-node-dot bg-{{ $step['color'] }}"></div>
                  <div class="timeline-step-circle border-{{ $step['color'] }}" title="{{ $step['role'] }}">
                    {{ $loop->iteration }}
                  </div>
                </div>

                {{-- Bottom zone: card floats to top of this zone --}}
                <div class="timeline-zone-bottom">
                  <div class="timeline-card arrow-up" :style="{ '--theme-color': '{{ $themeColor }}', 'border-color': '{{ $themeColor }}' }">
                    <div class="timeline-card-header" :style="{ 'background-color': '{{ $themeColor }}' }">
                      <i class="{{ $step['icon'] }} me-1 rkap-font-085"></i> {{ $step['status'] }}
                    </div>
                    <div class="timeline-card-body text-center py-1 px-2">
                      <div class="fw-semibold text-dark text-truncate mb-0.5 rkap-font-062" title="{{ $step['name'] }}">
                        {{ $step['name'] }}
                      </div>
                      <div class="text-muted rkap-font-052 text-nowrap">
                        <i class="bx bx-calendar me-0.5 rkap-font-06"></i>{{ $step['time'] }}
                      </div>
                    </div>
                  </div>
                </div>
                @endif

              </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      <!-- Work Plans -->
      <h5 class="mb-3">{{ __('Rincian Program Kerja') }}</h5>
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
              <span class="text-muted small d-block">{{ __('Subtotal Program') }}</span>
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
                  <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">{{ __('RKAP Periode Sebelumnya') }}
                    ({{ $prevPeriod }})</div>
                  <div class="row text-center">
                    <div class="col-4 border-end">
                      <div class="text-white-50 small rkap-font-065">{{ __('Anggaran') }}</div>
                      <div class="fw-bold text-white rkap-font-075">Rp
                        {{ number_format($prevProgramData['budget'], 0, ',', '.') }}
                      </div>
                    </div>
                    <div class="col-4 border-end">
                      <div class="text-white-50 small rkap-font-065">{{ __('Realisasi') }}</div>
                      <div class="fw-bold text-white text-success rkap-font-075">Rp
                        {{ number_format($prevProgramData['realization'], 0, ',', '.') }}
                      </div>
                    </div>
                    <div class="col-4">
                      <div class="text-white-50 small rkap-font-065">{{ __('Proyeksi') }}</div>
                      <div class="fw-bold text-white text-warning rkap-font-075">Rp
                        {{ number_format($prevProgramData['projection'] ?? 0, 0, ',', '.') }}
                      </div>
                    </div>
                  </div>
                  @else
                  <div class="text-center text-white-50 py-1">{{ __('Tidak ada data di periode sebelumnya') }}</div>
                  @endif
                </span>
              </strong>
            </div>
          </div>
        </div>

        <div class="card-body bg-light-gray p-3 bg-lighter">
          @foreach ($wpGroup as $actIdx => $wp)
          @php
          $activity = $wp->activity;
          $activityCode = $activity ? $activity->code : ($wp->program_code ?: '-');
          $activityTitle = $activity ? $activity->title : ($wp->program_name ?: '-');
          $wpSubtotal = $wp->budgetItems->sum('total_price');
          @endphp
          <div class="rkap-activity-card p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-label-primary rounded-circle p-2"><i class="bx bx-task"></i></span>
                <div>
                  <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                    Kegiatan {{ $actIdx + 1 }}
                    @if (($wp->approval_status ?? 'pending') === 'approved')
                    <span class="badge bg-label-success ms-2 rkap-font-07 rkap-p-02-04"><i
                        class="bx bx-check-circle me-1"></i>{{ __('Disetujui') }}</span>
                    @elseif(($wp->approval_status ?? 'pending') === 'rejected')
                    <span class="badge bg-label-danger ms-2 rkap-font-07 rkap-p-02-04"><i
                        class="bx bx-x-circle me-1"></i>{{ __('Revisi') }}</span>
                    @else
                    <span class="badge bg-label-secondary ms-2 rkap-font-07 rkap-p-02-04"><i
                        class="bx bx-time-five me-1"></i>{{ __('Pending') }}</span>
                    @endif
                    @if ($wp->is_past_period_payment)
                    <span class="badge bg-warning text-dark ms-1 rkap-font-07 rkap-p-02-04">
                      <i class="bx bx-calendar-exclamation me-1"></i>Pembayaran Periode Lalu
                      @if ($wp->pastPeriod) — {{ $wp->pastPeriod->year }}@endif
                    </span>
                    @endif
                  </h6>
                  <span class="text-muted small">{{ $activityCode }} — {{ $activityTitle }}</span>
                  @if ($wp->is_past_period_payment && $wp->pastPeriod)
                  <div class="d-flex align-items-center gap-1 mt-1 rkap-font-078">
                    <i class="bx bx-info-circle text-warning"></i>
                    <span class="text-warning fw-semibold">Anggaran rencana pembayaran kewajiban untuk
                      RKAP {{ $wp->pastPeriod->year }} — {{ $wp->pastPeriod->title }}</span>
                  </div>
                  @endif
                </div>
              </div>
              <div class="text-end">
                <span class="text-muted small d-block">{{ __('Subtotal Kegiatan') }}</span>
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
                        <div class="text-white-50 small rkap-font-065">{{ __('Anggaran') }}</div>
                        <div class="fw-bold text-white rkap-font-075">Rp
                          {{ number_format($prevActivityData['budget'], 0, ',', '.') }}
                        </div>
                      </div>
                      <div class="col-4 border-end">
                        <div class="text-white-50 small rkap-font-065">{{ __('Realisasi') }}</div>
                        <div class="fw-bold text-white text-success rkap-font-075">Rp
                          {{ number_format($prevActivityData['realization'], 0, ',', '.') }}
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="text-white-50 small rkap-font-065">{{ __('Proyeksi') }}</div>
                        <div class="fw-bold text-white text-warning rkap-font-075">Rp
                          {{ number_format($prevActivityData['projection'] ?? 0, 0, ',', '.') }}
                        </div>
                      </div>
                    </div>
                    @else
                    <div class="text-center text-white-50 py-1">{{ __('Tidak ada data di periode sebelumnya') }}</div>
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
                <h6 class="alert-heading mb-1 fw-bold text-danger">{{ __('Catatan Revisi dari Reviewer:') }}</h6>
                <span class="text-dark">{{ $wp->revision_notes }}</span>
              </div>
            </div>
            @endif

            @if ($wp->description)
            <div class="mb-3">
              <label class="text-muted small d-block">{{ __('Deskripsi / Tujuan') }}</label>
              <p class="mb-0 text-dark rkap-ws-pre-line">{{ $wp->description }}</p>
            </div>
            @endif

            <div class="row g-2 mb-3 small">
              <div class="col-auto">
                <span class="text-muted">{{ __('Target Output:') }}</span> <span
                  class="fw-medium">{{ $wp->output_target ?? '-' }}</span>
              </div>
              <div class="col-auto ms-3">
                <span class="text-muted">{{ __('Volume:') }}</span> <span class="fw-medium">{{ $wp->quantity }}
                  {{ $wp->unit }}</span>
              </div>
            </div>

            @if ($wp->activityFiles && $wp->activityFiles->isNotEmpty())
            <div class="mb-3" wire:key="act-view-files-{{ $wp->id }}">
              <label class="text-muted small d-block mb-1 fw-semibold"><i class="bx bx-paperclip me-1"></i>{{ __('File Referensi:') }}</label>
              <div class="d-flex flex-wrap gap-2">
                @foreach ($wp->activityFiles as $file)
                @php
                $isViewable = in_array(strtolower($file->file_type), ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'svg']);
                @endphp
                @if ($isViewable)
                <button type="button" class="btn btn-xs btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewFileModal-{{ $file->id }}">
                  <i class="bx bx-show me-1"></i> {{ $file->original_name }}
                </button>
                <!-- Modal for displaying inline -->
                <div class="modal fade" id="viewFileModal-{{ $file->id }}" tabindex="-1" aria-hidden="true" wire:key="view-file-modal-{{ $file->id }}">
                  <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title"><i class="bx bx-file me-2 text-primary"></i>{{ $file->original_name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body p-0 text-center bg-light">
                        @if (strtolower($file->file_type) === 'pdf')
                        <iframe src="{{ route('rkap-files.view', $file->id) }}" width="100%" height="700px" class="border-0"></iframe>
                        @else
                        <img src="{{ route('rkap-files.view', $file->id) }}" class="img-fluid p-3 rkap-img-h75-contain" />
                        @endif
                      </div>
                      <div class="modal-footer">
                        <a href="{{ route('rkap-files.download', $file->id) }}" class="btn btn-primary btn-sm"><i class="bx bx-download me-1"></i> Download</a>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
                      </div>
                    </div>
                  </div>
                </div>
                @else
                <a href="{{ route('rkap-files.download', $file->id) }}" class="btn btn-xs btn-outline-secondary">
                  <i class="bx bx-download me-1"></i> {{ $file->original_name }}
                </a>
                @endif
                @endforeach
              </div>
            </div>
            @endif

            <div class="table-responsive">
              <table class="table table-sm table-striped table-hover mb-0">
                <thead>
                  <tr>
                    <th>{{ __('Uraian & Detail Belanja') }}</th>
                    <th class="text-center w-10p">Vol</th>
                    <th class="w-12p">Sat</th>
                    <th class="text-end w-14p">{{ __('Harga Satuan') }}</th>
                    <th class="text-end w-14p">{{ __('Total') }}</th>
                    <th class="text-center w-8p">Aksi</th>
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
                          <div class="has-tooltip rkap-font-078 rkap-lh-12">
                            <div class="text-muted text-nowrap rkap-font-068">Sub-total
                            </div>
                            <div class="fw-bold text-primary text-nowrap">
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
                                  <div class="text-white-50 small rkap-font-065">{{ __('Anggaran') }}</div>
                                  <div class="fw-bold text-white rkap-font-075">Rp
                                    {{ number_format($prevCoaData['budget'], 0, ',', '.') }}
                                  </div>
                                </div>
                                <div class="col-4 border-end">
                                  <div class="text-white-50 small rkap-font-065">{{ __('Realisasi') }}</div>
                                  <div class="fw-bold text-white text-success rkap-font-075">Rp
                                    {{ number_format($prevCoaData['realization'], 0, ',', '.') }}
                                  </div>
                                </div>
                                <div class="col-4">
                                  <div class="text-white-50 small rkap-font-065">{{ __('Proyeksi') }}</div>
                                  <div class="fw-bold text-white text-warning rkap-font-075">Rp
                                    {{ number_format($prevCoaData['projection'] ?? 0, 0, ',', '.') }}
                                  </div>
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
                        {{ number_format($bi->total_price, 0, ',', '.') }}
                      </div>
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
                                      <div class="col-md-auto border-end text-nowrap">
                                        <span class="text-muted d-block mb-1">{{ __('Kode Akun') }}</span>
                                        <span
                                          class="fw-semibold text-dark fs-6">{{ $bi->account_code ?? '-' }}</span>
                                      </div>
                                      <div class="col border-end">
                                        <span class="text-muted d-block mb-1">Deskripsi / Detail
                                          Belanja</span>
                                        <span
                                          class="fw-semibold text-dark fs-6 text-wrap">{{ $bi->description }}</span>
                                        @if ($bi->remarks)
                                        <div class="text-muted mt-1 small">Ket: {{ $bi->remarks }}</div>
                                        @endif
                                      </div>
                                      <div class="col-md-auto border-end text-nowrap">
                                        <span class="text-muted d-block mb-1">{{ __('Volume') }}</span>
                                        <span class="fw-semibold text-dark fs-6">
                                          @if ($bi->unit_2)
                                          {{ $bi->quantity }} {{ $bi->unit }} x {{ $bi->quantity_2 }}
                                          {{ $bi->unit_2 }}
                                          @else
                                          {{ $bi->quantity }} {{ $bi->unit }}
                                          @endif
                                        </span>
                                      </div>
                                      <div class="col-md-auto text-nowrap">
                                        <span class="text-muted d-block mb-1">{{ __('Total Anggaran') }}</span>
                                        <span class="fw-bold text-primary fs-6">Rp
                                          {{ number_format($bi->total_price, 0, ',', '.') }}</span>
                                      </div>
                                    </div>
                                  </div>
                                </div>

                                @php
                                $monthliesByMonth = $bi->monthlies->keyBy('month');
                                $cashOutsByMonth = $bi->cashOuts->keyBy('month');
                                $hasAnyValue = false;
                                foreach (range(1, 12) as $mNum) {
                                if (
                                (isset($monthliesByMonth[$mNum]) && (float) $monthliesByMonth[$mNum]->amount > 0) ||
                                (isset($cashOutsByMonth[$mNum]) && (float) $cashOutsByMonth[$mNum]->amount > 0)
                                ) {
                                $hasAnyValue = true;
                                break;
                                }
                                }
                                @endphp

                                @if (!$hasAnyValue)
                                <div class="text-center text-muted py-4">
                                  <i class="bx bx-info-circle fs-3 mb-2 d-block"></i>
                                  <span class="small">{{ __('Tidak ada data alokasi anggaran') }}</span>
                                </div>
                                @else
                                <div class="border rounded-2 table-responsive mb-2">
                                  <table class="table table-sm table-bordered align-middle mb-0 rkap-mw-750">
                                    <thead class="table-primary">
                                      <tr>
                                        <th class="text-center rkap-w-90">{{ __('Bulan') }}</th>
                                        <th class="text-end">
                                          Distribusi Penganggaran (Rp)
                                          <div class="small fw-normal text-muted rkap-font-065 rkap-opacity-85">({{ $bi->coa?->coaGroup?->name ?: '-' }})</div>
                                        </th>
                                        <th class="text-end">
                                          Rencana Pendanaan (Rp)
                                          <div class="small fw-normal text-muted rkap-font-065 rkap-opacity-85">({{ $bi->coa?->cashflowGroup?->name ?: '-' }})</div>
                                        </th>
                                        <th class="text-end">
                                          Selisih (Rp)
                                          <div class="small fw-normal text-muted rkap-font-065 rkap-opacity-85">({{ $bi->coa?->differenceGroup?->name ?: '-' }})</div>
                                        </th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      @foreach ($monthNames as $monthNum => $monthLabel)
                                      @php
                                      $distValue = isset($monthliesByMonth[$monthNum]) ? (float) $monthliesByMonth[$monthNum]->amount : 0;
                                      $cashOutValue = isset($cashOutsByMonth[$monthNum]) ? (float) $cashOutsByMonth[$monthNum]->amount : 0;
                                      @endphp
                                      @if ($distValue > 0 || $cashOutValue > 0)
                                      @php
                                      $selisih = $distValue - $cashOutValue;
                                      @endphp
                                      <tr>
                                        <td class="text-center fw-semibold small">{{ $monthLabel }}</td>
                                        <td class="text-end font-monospace">
                                          @if ($distValue > 0)
                                          Rp {{ number_format($distValue, 0, ',', '.') }}
                                          @else
                                          <span class="text-muted small">-</span>
                                          @endif
                                        </td>
                                        <td class="text-end font-monospace">
                                          @if ($cashOutValue > 0)
                                          Rp {{ number_format($cashOutValue, 0, ',', '.') }}
                                          @else
                                          <span class="text-muted small">-</span>
                                          @endif
                                        </td>
                                        <td class="text-end font-monospace fw-semibold {{ $selisih == 0 ? 'text-success' : 'text-danger' }}">
                                          Rp {{ number_format($selisih, 0, ',', '.') }}
                                        </td>
                                      </tr>
                                      @endif
                                      @endforeach
                                    </tbody>
                                  </table>
                                </div>
                                @endif
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary"
                                  data-bs-dismiss="modal">{{ __('Tutup') }}</button>
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