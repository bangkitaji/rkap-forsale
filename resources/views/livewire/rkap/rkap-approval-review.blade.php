<div
  @focus-activity-revision-note.window="
  $nextTick(() => {
    const el = document.getElementById('activity-revision-note-' + $event.detail.id);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      el.focus();
    }
  });
">
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
      padding: 10px 10px;
      width: 100%;
      background: #fafbfc;
      border-radius: 8px;
    }

    /* 
     * Each column is split into 3 zones:
     *   [card-zone-top]   -- card extends upward (for odd/above steps)
     *   [axis-zone]       -- node dot + numbered circle, right on the axis
     *   [card-zone-bottom]-- card extends downward (for even/below steps)
     */
    .timeline-steps-wrapper {
      position: relative;
      padding: 10px 0;
      width: max-content;
      min-width: 100%;
      display: flex;
      align-items: stretch;    /* stretch so columns share full height */
      height: 160px;
    }

    /* Horizontal axis line sits at vertical center */
    .timeline-steps-line {
      position: absolute;
      top: calc(50% - 2px);
      height: 4px;
      background: #2b354e;
      z-index: 1;
      border-radius: 2px;
    }

    .timeline-step-column {
      width: 150px;
      display: flex;
      flex-direction: column;
      align-items: center;
      position: relative;
      z-index: 2;
      flex-shrink: 0;
    }

    /* Top half: card area for "above" steps */
    .timeline-zone-top {
      height: 68px;          /* (160px wrapper - 24px axis) / 2 */
      width: 100%;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;  /* card sits at bottom of top zone */
      align-items: center;
      padding-bottom: 4px;
      position: relative;
    }

    /* Axis zone: holds node-dot and numbered circle, sits right on the axis */
    .timeline-zone-axis {
      height: 24px;           /* node-dot area, vertically centred */
      width: 100%;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      position: relative;
      gap: 2px;
      z-index: 4;
    }

    /* Bottom half: card area for "below" steps */
    .timeline-zone-bottom {
      height: 68px;
      width: 100%;
      display: flex;
      flex-direction: column;
      justify-content: flex-start; /* card sits at top of bottom zone */
      align-items: center;
      padding-top: 4px;
      position: relative;
    }

    .timeline-step-circle {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background-color: #fff;
      border: 2.5px solid #8592a3;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08), inset 0 2px 4px rgba(0,0,0,0.05);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.72rem;
      color: #2b354e;
      transition: all 0.25s ease;
      z-index: 3;
      flex-shrink: 0;
    }

    .timeline-step-circle.border-success { border-color: #28c76f !important; color: #28c76f !important; }
    .timeline-step-circle.border-danger { border-color: #ea5455 !important; color: #ea5455 !important; }
    .timeline-step-circle.border-warning { border-color: #ff9f43 !important; color: #ff9f43 !important; }
    .timeline-step-circle.border-secondary { border-color: #8592a3 !important; color: #8592a3 !important; }
    .timeline-step-circle.border-primary { border-color: #7367f0 !important; color: #7367f0 !important; }

    .timeline-node-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background-color: #8592a3;
      border: 2px solid #fff;
      box-shadow: 0 0 0 1.5px #2b354e;
      z-index: 4;
      transition: all 0.25s ease;
      flex-shrink: 0;
    }

    .timeline-node-dot.bg-success { background-color: #28c76f !important; }
    .timeline-node-dot.bg-danger { background-color: #ea5455 !important; }
    .timeline-node-dot.bg-warning { background-color: #ff9f43 !important; }
    .timeline-node-dot.bg-secondary { background-color: #8592a3 !important; }
    .timeline-node-dot.bg-primary { background-color: #7367f0 !important; }

    .timeline-card {
      width: 135px;
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 4px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      position: relative;
      transition: all 0.25s ease;
    }
    
    .timeline-card:hover {
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
    }

    .timeline-card-header {
      padding: 3px 6px;
      font-size: 0.58rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #fff;
      text-align: center;
      border-top-left-radius: 3px;
      border-top-right-radius: 3px;
    }

    .timeline-card-body {
      padding: 6px;
    }

    .timeline-card-body h6 {
      font-size: 0.65rem !important;
      margin-bottom: 2px !important;
    }

    .timeline-card-body .text-muted {
      font-size: 0.58rem !important;
      margin-bottom: 2px !important;
    }

    .timeline-card-body .small {
      font-size: 0.52rem !important;
    }

    /* Arrow pointing DOWN from the card (card is above axis) */
    .timeline-card.arrow-down::after {
      content: "";
      position: absolute;
      bottom: -7px;
      left: 50%;
      transform: translateX(-50%);
      border-width: 7px 6px 0 6px;
      border-style: solid;
      border-color: var(--theme-color) transparent transparent transparent;
      z-index: 5;
    }

    /* Arrow pointing UP from the card (card is below axis) */
    .timeline-card.arrow-up::after {
      content: "";
      position: absolute;
      top: -7px;
      left: 50%;
      transform: translateX(-50%);
      border-width: 0 6px 7px 6px;
      border-style: solid;
      border-color: transparent transparent var(--theme-color) transparent;
      z-index: 5;
    }

  </style>
  <div class="d-flex justify-content-between align-items-center py-3 mb-4">
    <h4 class="mb-0">
      <span class="text-muted fw-light">RKAP / <a href="{{ route('rkap-submissions') }}"
          class="text-muted text-decoration-none">Pengajuan</a> /</span>
      Review & Persetujuan RKAP
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

  @if (session()->has('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if (
      (auth()->user()->isPresidentDirector() || auth()->user()->isDirekturFinance()) &&
          $submission->status === 'pdir_review' &&
          !$this->presidentApprovalStatus['is_ready']
  )
    <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
      <span class="badge bg-warning text-white me-3 p-1"><i class="bx bx-error fs-4"></i></span>
      <div>
        <h6 class="alert-heading mb-1 fw-bold text-warning" style="color: #ffab00 !important;">Persetujuan Ditangguhkan
          (Persetujuan Belum Dapat Dilakukan)</h6>
        <span>
          Persetujuan akhir oleh Direktur Utama dan Direktur Finance hanya dapat dilakukan setelah <strong>seluruh
            departemen</strong> menyelesaikan pengajuan RKAP dan telah diverifikasi oleh verifikator.
          Saat ini baru <strong>{{ $this->presidentApprovalStatus['verified_count'] }} dari
            {{ $this->presidentApprovalStatus['total_count'] }}</strong> departemen yang terverifikasi.
        </span>
        @if (!empty($this->presidentApprovalStatus['pending_departments']))
          <div class="mt-2 small text-muted">
            <strong>Departemen yang belum terverifikasi:</strong>
            <span
              class="text-danger fw-bold">{{ implode(', ', $this->presidentApprovalStatus['pending_departments']) }}</span>
          </div>
        @endif
      </div>
    </div>
  @endif

  <div class="row">
    <!-- Main Content: RKAP Details -->
    <div class="col-12">
      <!-- Header Info with Comparison -->
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
              <label class="text-muted small">Total Anggaran Ajuan</label>
              <div class="fw-bold text-primary fs-5 has-tooltip">Rp
                {{ number_format($submission->total_budget, 0, ',', '.') }}
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

              @if (isset($prevData['total_budget']))
                @php
                  $prevTotal = $prevData['total_budget'];
                  $totalDiff = $submission->total_budget - $prevTotal;
                  $totalPct = $prevTotal > 0 ? ($totalDiff / $prevTotal) * 100 : 0;
                @endphp
                <div class="small mt-1 p-1 bg-lighter rounded">
                  <span class="text-muted d-block" style="font-size: 0.65rem;">Sebelumnya
                    ({{ $prevData['period'] }}):</span>
                  <span class="fw-semibold text-secondary" style="font-size: 0.72rem;">Rp
                    {{ number_format($prevTotal, 0, ',', '.') }}</span>
                  <span
                    class="fw-bold d-block mt-0.5 @if ($totalDiff > 0) text-danger @elseif($totalDiff < 0) text-success @else text-muted @endif"
                    style="font-size: 0.72rem;">
                    @if ($totalDiff > 0)
                      <i class="bx bx-trending-up" style="font-size: 0.8rem;"></i> +{{ number_format($totalPct, 1) }}%
                    @elseif($totalDiff < 0)
                      <i class="bx bx-trending-down" style="font-size: 0.8rem;"></i>
                      -{{ number_format(abs($totalPct), 1) }}%
                    @else
                      = 0%
                    @endif
                  </span>
                </div>
              @endif
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
                  'time' => $submission->created_at->format('d M Y, H:i'),
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
                      'time' => $approval->created_at->format('d M Y, H:i'),
                      'comment' => $approval->comments,
                      'icon' => $icon
                  ]);
              }
            @endphp

            @if ($allTimelineSteps->count() > 1)
              <div class="timeline-steps-line" style="width: {{ 165 * ($allTimelineSteps->count() - 1) }}px; left: 75px;"></div>
            @endif

            {{--
              Layout per column (top-to-bottom, 260px total height):
              [timeline-zone-top    118px] — card floats down to bottom edge for ODD steps
              [timeline-zone-axis    24px] — node-dot on top, numbered-circle below (or reversed for even)
              [timeline-zone-bottom 118px] — card floats up to top edge for EVEN steps

              ODD  steps (1, 3, 5…): card is ABOVE the axis → top zone has card, axis has dot-above-circle
              EVEN steps (2, 4, 6…): card is BELOW the axis → bottom zone has card, axis has circle-above-dot
            --}}
            <div class="d-flex align-items-stretch justify-content-start" style="gap: 15px; z-index: 2; position: relative;">
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
                      <div class="timeline-card arrow-down" style="--theme-color: {{ $themeColor }}; border-color: {{ $themeColor }};">
                        <div class="timeline-card-header" style="background-color: {{ $themeColor }};">
                          <i class="{{ $step['icon'] }} me-1" style="font-size: 0.85rem;"></i> {{ $step['status'] }}
                        </div>
                        <div class="timeline-card-body text-center py-1 px-2">
                          <div class="text-muted" style="font-size: 0.58rem !important; white-space: nowrap;">
                            <i class="bx bx-calendar me-0.5" style="font-size: 0.68rem;"></i>{{ $step['time'] }}
                          </div>
                        </div>
                      </div>
                    </div>

                    {{-- Axis zone: numbered circle (top), then node-dot (bottom) --}}
                    <div class="timeline-zone-axis" style="flex-direction: column; justify-content: center; gap: 2px;">
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
                    <div class="timeline-zone-axis" style="flex-direction: column; justify-content: center; gap: 2px;">
                      <div class="timeline-node-dot bg-{{ $step['color'] }}"></div>
                      <div class="timeline-step-circle border-{{ $step['color'] }}" title="{{ $step['role'] }}">
                        {{ $loop->iteration }}
                      </div>
                    </div>

                    {{-- Bottom zone: card floats to top of this zone --}}
                    <div class="timeline-zone-bottom">
                      <div class="timeline-card arrow-up" style="--theme-color: {{ $themeColor }}; border-color: {{ $themeColor }};">
                        <div class="timeline-card-header" style="background-color: {{ $themeColor }};">
                          <i class="{{ $step['icon'] }} me-1" style="font-size: 0.85rem;"></i> {{ $step['status'] }}
                        </div>
                        <div class="timeline-card-body text-center py-1 px-2">
                          <div class="text-muted" style="font-size: 0.58rem !important; white-space: nowrap;">
                            <i class="bx bx-calendar me-0.5" style="font-size: 0.68rem;"></i>{{ $step['time'] }}
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

      <!-- Review Actions (Only if user can review) -->
      @if ($this->canApprove())
        <div class="card mb-4 border-primary shadow-sm">
          <div class="card-header bg-label-primary py-3">
            <h5 class="mb-0 text-primary fw-bold"><i class="bx bx-check-shield me-2"></i>Aksi Review</h5>
          </div>
          <div class="card-body mt-3">
            @if (auth()->user()->isVerifikator())
              <button class="btn btn-primary w-100 mb-3" wire:click="openAddActivityModal" wire:key="btn-open-add-activity">
                <i class="bx bx-plus me-1"></i> Tambah Program Kegiatan
              </button>
            @endif

            @if ($showRevisionForm)
              <div class="mb-3" wire:key="revision-reason-wrapper">
                <label class="form-label text-danger fw-semibold">Alasan Permintaan Revisi <span
                    class="text-danger">*</span></label>
                <textarea class="form-control @error('revisionReason') is-invalid @enderror" wire:model="revisionReason"
                  rows="3" placeholder="Sebutkan bagian mana yang perlu diperbaiki..." wire:key="revision-reason-textarea"
                  readonly></textarea>
                @error('revisionReason')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div class="d-flex gap-2" wire:key="revision-action-buttons">
                <button class="btn btn-label-secondary w-50" wire:key="btn-cancel-revision"
                  wire:click="$set('showRevisionForm', false)">Batal</button>
                <button class="btn btn-danger w-50" wire:key="btn-submit-revision" wire:click="requestRevision"
                  wire:confirm="Yakin ingin menolak dan meminta revisi RKAP ini?" wire:loading.attr="disabled">Kirim
                  Permintaan</button>
              </div>
            @else
              <div class="mb-3">
                <label class="form-label fw-semibold">Catatan Review (Opsional)</label>
                <textarea class="form-control" wire:model="reviewComments" rows="2"
                  placeholder="Tinggalkan catatan untuk persetujuan..."></textarea>
              </div>
              @php
                $allApproved = collect($submission->workPlans)->every(
                    fn($wp) => ($this->activityStatuses[$wp->id] ?? 'pending') === 'approved',
                );
                $hasRejected = collect($submission->workPlans)->contains(
                    fn($wp) => ($this->activityStatuses[$wp->id] ?? 'pending') === 'rejected',
                );
              @endphp
              <div class="d-flex flex-column gap-2">
                @php
                  $totalActivities = $submission->workPlans->count();
                  $approvedCount = collect($submission->workPlans)
                      ->filter(fn($wp) => ($this->activityStatuses[$wp->id] ?? 'pending') === 'approved')
                      ->count();
                  $rejectedCount = collect($submission->workPlans)
                      ->filter(fn($wp) => ($this->activityStatuses[$wp->id] ?? 'pending') === 'rejected')
                      ->count();
                  $pendingCount = $totalActivities - $approvedCount - $rejectedCount;
                @endphp

                {{-- Activity progress summary --}}
                <div class="bg-lighter rounded p-3 mb-2 border">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="text-muted fw-semibold">Status Kegiatan</small>
                    <small
                      class="fw-bold text-{{ $allApproved ? 'success' : 'secondary' }}">{{ $approvedCount }}/{{ $totalActivities }}
                      Disetujui</small>
                  </div>
                  <div class="progress mb-2" style="height: 6px;">
                    @if ($totalActivities > 0)
                      <div class="progress-bar bg-success"
                        style="width: {{ ($approvedCount / $totalActivities) * 100 }}%;"></div>
                      <div class="progress-bar bg-danger"
                        style="width: {{ ($rejectedCount / $totalActivities) * 100 }}%;"></div>
                    @endif
                  </div>
                  @if ($rejectedCount > 0 || $pendingCount > 0)
                    <div class="d-flex gap-3" style="font-size: 0.75rem;">
                      @if ($approvedCount > 0)
                        <span class="text-success fw-medium"><i class="bx bx-check-circle me-1"></i>{{ $approvedCount }}
                          Disetujui</span>
                      @endif
                      @if ($rejectedCount > 0)
                        <span class="text-danger fw-medium"><i class="bx bx-x-circle me-1"></i>{{ $rejectedCount }}
                          Ditolak</span>
                      @endif
                      @if ($pendingCount > 0)
                        <span class="text-secondary fw-medium"><i class="bx bx-time-five me-1"></i>{{ $pendingCount }}
                          Pending</span>
                      @endif
                    </div>
                  @endif
                </div>

                <div class="d-flex gap-3 mt-2">
                  <button class="btn btn-success flex-grow-1 py-2" wire:key="btn-approve-rkap" wire:click="approve"
                    wire:loading.attr="disabled" wire:confirm="Yakin menyetujui RKAP ini?" @disabled(!$allApproved)>
                    <i class="bx bx-check-circle me-1"></i> Setujui RKAP
                  </button>

                  <button class="btn btn-outline-danger flex-grow-1 py-2" wire:key="btn-open-revision-form"
                    wire:click="openRevisionForm" @disabled(!$hasRejected)>
                    <i class="bx bx-x-circle me-1"></i> Minta Revisi
                  </button>
                </div>
                @if (!$hasRejected)
                  <small class="text-center text-muted mt-1"><i class="bx bx-info-circle me-1"></i>Tolak minimal satu
                    kegiatan untuk meminta revisi.</small>
                @endif
              </div>
            @endif
          </div>
        </div>
      @endif

      @if (
          (auth()->user()->isPresidentDirector() || auth()->user()->isDirekturFinance()) &&
              in_array($submission->status, ['pdir_review', 'approved']))
        <!-- Helicopter View -->
        <div class="card mb-4">
          <div class="card-header border-bottom d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-1"><i class="bx bx-spreadsheet me-2 text-primary"></i>Helikopter-View Pengajuan RKAP</h5>
              <p class="text-muted small mb-0">Menampilkan akumulasi anggaran yang dikompilasi berdasarkan kategori Profit
                & Loss.</p>
            </div>
            <span
              class="badge bg-label-primary">{{ auth()->user()->isPresidentDirector() ? 'Direktur Utama Approval' : 'Direktur Finance Approval' }}</span>
          </div>
          <div class="card-body">

        @php
          $groupedCategories = collect($helicopterViewData['categories'])->groupBy('group');
        @endphp

        @foreach ($groupedCategories as $groupName => $cats)
          <h6 class="text-uppercase text-muted small fw-bold mb-3 mt-4" style="letter-spacing: 1px;">
            <i class="bx bx-folder-open me-1 text-secondary"></i> {{ $groupName }}
          </h6>
          <div class="row g-3">
            @foreach ($cats as $cat)
              <div class="col-12">
                <div class="card mb-2 border-start border-{{ $cat['color'] }} border-3 shadow-sm">
                  <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center cursor-pointer collapsed"
                      data-bs-toggle="collapse" data-bs-target="#cat-collapse-{{ $cat['key'] }}"
                      aria-expanded="false" style="user-select: none;">
                      <div>
                        <h6 class="mb-1 fw-bold text-dark">{{ $cat['label'] }}</h6>
                        <small class="text-muted"><i class="bx bx-chevron-down me-1"></i> Klik untuk melihat rincian
                          {{ count($cat['coas']) }} COA</small>
                      </div>
                      <div class="text-end">
                        <span class="text-muted small d-block" style="font-size: 0.7rem;">Anggaran Diajukan</span>
                        <span class="fw-bold text-{{ $cat['color'] }} fs-5">Rp
                          {{ number_format($cat['current_total'], 0, ',', '.') }}</span>

                        @if ($helicopterViewData['prevPeriod'])
                          @php
                            $prevTotal = $cat['prev_total'];
                            $diff = $cat['current_total'] - $prevTotal;
                            $pct = $prevTotal > 0 ? ($diff / $prevTotal) * 100 : 0;
                          @endphp
                          <div style="font-size: 0.75rem; line-height: 1.2;">
                            <span class="text-muted">Sebelumnya ({{ $helicopterViewData['prevPeriod'] }}): Rp
                              {{ number_format($prevTotal, 0, ',', '.') }}</span>
                            <span
                              class="fw-bold ms-1 @if ($diff > 0) text-danger @elseif($diff < 0) text-success @else text-muted @endif">
                              @if ($diff > 0)
                                ↑ +{{ number_format($pct, 1) }}%
                              @elseif($diff < 0)
                                ↓ -{{ number_format(abs($pct), 1) }}%
                              @else
                                = 0%
                              @endif
                            </span>
                          </div>
                        @endif
                      </div>
                    </div>

                    <!-- Collapsible Details -->
                    <div class="collapse mt-3" id="cat-collapse-{{ $cat['key'] }}">
                      <hr class="my-2">
                      @if (empty($cat['coas']))
                        <div class="text-center py-2 text-muted small">
                          Tidak ada COA yang dianggarkan dalam kategori ini.
                        </div>
                      @else
                        <div class="table-responsive">
                          <table class="table table-sm table-hover table-striped mb-0" style="font-size: 0.8rem;">
                            <thead>
                              <tr>
                                <th style="width: 20%;">Kode Akun</th>
                                <th style="width: 50%;">Judul Akun (COA)</th>
                                <th class="text-end" style="width: 15%;">Anggaran Ajuan</th>
                                <th class="text-end" style="width: 15%;">Selisih (Δ)</th>
                              </tr>
                            </thead>
                            <tbody>
                              @foreach ($cat['coas'] as $coaItem)
                                @php
                                  $coaDiff = $coaItem['current_total'] - $coaItem['prev_total'];
                                  $coaPct = $coaItem['prev_total'] > 0 ? ($coaDiff / $coaItem['prev_total']) * 100 : 0;
                                @endphp
                                <tr>
                                  <td><strong>{{ $coaItem['code'] }}</strong></td>
                                  <td class="text-wrap">{{ $coaItem['title'] }}</td>
                                  <td class="text-end fw-semibold text-primary">Rp
                                    {{ number_format($coaItem['current_total'], 0, ',', '.') }}</td>
                                  <td
                                    class="text-end fw-semibold @if ($coaDiff > 0) text-danger @elseif($coaDiff < 0) text-success @else text-muted @endif">
                                    @if ($coaItem['prev_total'] > 0)
                                      @if ($coaDiff > 0)
                                        ↑ +{{ number_format($coaPct, 1) }}%
                                      @elseif($coaDiff < 0)
                                        ↓ -{{ number_format(abs($coaPct), 1) }}%
                                      @else
                                        = 0%
                                      @endif
                                    @else
                                      <span class="text-muted small">Baru</span>
                                    @endif
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
            @endforeach
          </div>
        @endforeach

        @php
          $hasUnmapped = collect($helicopterViewData['unmappedGroups'])->contains(
              fn($g) => $g['current_total'] > 0 || $g['prev_total'] > 0,
          );
        @endphp

        @if ($hasUnmapped)
          <h5 class="mt-5 mb-3 text-secondary border-bottom pb-2">
            <i class="bx bx-bracket me-2 text-secondary"></i>Akun COA Neraca & Lainnya (Belum Dipetakan)
          </h5>
          <div class="row g-3">
            @foreach ($helicopterViewData['unmappedGroups'] as $group)
              @if ($group['current_total'] > 0 || $group['prev_total'] > 0)
                <div class="col-12">
                  <div class="card mb-2 border-start border-secondary border-3 shadow-sm">
                    <div class="card-body py-3">
                      <div class="d-flex justify-content-between align-items-center cursor-pointer collapsed"
                        data-bs-toggle="collapse" data-bs-target="#group-collapse-{{ $group['group_id'] }}"
                        aria-expanded="false" style="user-select: none;">
                        <div>
                          <h6 class="mb-1 fw-bold text-secondary">{{ $group['group_name'] }} (Grup:
                            {{ $group['group_code'] }})</h6>
                          <small class="text-muted"><i class="bx bx-chevron-down me-1"></i> Klik untuk melihat rincian
                            {{ count($group['coas']) }} COA</small>
                        </div>
                        <div class="text-end">
                          <span class="text-muted small d-block" style="font-size: 0.7rem;">Anggaran Diajukan</span>
                          <span class="fw-bold text-secondary fs-5">Rp
                            {{ number_format($group['current_total'], 0, ',', '.') }}</span>

                          @if ($helicopterViewData['prevPeriod'])
                            @php
                              $prevTotal = $group['prev_total'];
                              $diff = $group['current_total'] - $prevTotal;
                              $pct = $prevTotal > 0 ? ($diff / $prevTotal) * 100 : 0;
                            @endphp
                            <div style="font-size: 0.75rem; line-height: 1.2;">
                              <span class="text-muted">Sebelumnya ({{ $helicopterViewData['prevPeriod'] }}): Rp
                                {{ number_format($prevTotal, 0, ',', '.') }}</span>
                              <span
                                class="fw-bold ms-1 @if ($diff > 0) text-danger @elseif($diff < 0) text-success @else text-muted @endif">
                                @if ($diff > 0)
                                  ↑ +{{ number_format($pct, 1) }}%
                                @elseif($diff < 0)
                                  ↓ -{{ number_format(abs($pct), 1) }}%
                                @else
                                  = 0%
                                @endif
                              </span>
                            </div>
                          @endif
                        </div>
                      </div>

                      <!-- Collapsible Details -->
                      <div class="collapse mt-3" id="group-collapse-{{ $group['group_id'] }}">
                        <hr class="my-2">
                        <div class="table-responsive">
                          <table class="table table-sm table-hover table-striped mb-0" style="font-size: 0.8rem;">
                            <thead>
                              <tr>
                                <th style="width: 20%;">Kode Akun</th>
                                <th style="width: 50%;">Judul Akun (COA)</th>
                                <th class="text-end" style="width: 15%;">Anggaran Ajuan</th>
                                <th class="text-end" style="width: 15%;">Selisih (Δ)</th>
                              </tr>
                            </thead>
                            <tbody>
                              @foreach ($group['coas'] as $coaItem)
                                @php
                                  $coaDiff = $coaItem['current_total'] - $coaItem['prev_total'];
                                  $coaPct = $coaItem['prev_total'] > 0 ? ($coaDiff / $coaItem['prev_total']) * 100 : 0;
                                @endphp
                                <tr>
                                  <td><strong>{{ $coaItem['code'] }}</strong></td>
                                  <td class="text-wrap">{{ $coaItem['title'] }}</td>
                                  <td class="text-end fw-semibold text-primary">Rp
                                    {{ number_format($coaItem['current_total'], 0, ',', '.') }}</td>
                                  <td
                                    class="text-end fw-semibold @if ($coaDiff > 0) text-danger @elseif($coaDiff < 0) text-success @else text-muted @endif">
                                    @if ($coaItem['prev_total'] > 0)
                                      @if ($coaDiff > 0)
                                        ↑ +{{ number_format($coaPct, 1) }}%
                                      @elseif($coaDiff < 0)
                                        ↓ -{{ number_format(abs($coaPct), 1) }}%
                                      @else
                                        = 0%
                                      @endif
                                    @else
                                      <span class="text-muted small">Baru</span>
                                    @endif
                                  </td>
                                </tr>
                              @endforeach
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              @endif
            @endforeach
          </div>
        @endif
          </div>
        </div>
      @else
        <!-- Work Plans -->
        <hr class="my-4">
        <h5 class="mb-4"><i class="bx bx-list-check me-2 text-primary"></i>Rincian Program Kerja</h5>
        @php
          $groupedCombinedWorkPlans = collect($combinedWorkPlans)->groupBy('work_plan_id');
        @endphp
        @foreach ($groupedCombinedWorkPlans as $wpId => $wpGroup)
          @php
            $firstWp = $wpGroup->first();
            $programCode = $firstWp['program_code'];
            $programName = $firstWp['program_name'];
            $wpGroupSubtotal = collect($wpGroup)->where('is_virtual', false)->sum('total_budget');
            $isProgramVirtual = collect($wpGroup)->every(fn($item) => $item['is_virtual'] ?? false);
          @endphp
          <div
            class="card mb-4 border-start border-primary border-3 @if ($isProgramVirtual) border-danger @endif"
            style="@if ($isProgramVirtual) border-left-color: #ff3e1d !important; background-color: #fff5f5; @endif">
            <div class="card-header border-bottom py-3"
              style="@if ($isProgramVirtual) background-color: #fff0f0; @endif">
              <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex flex-column">
                  <div class="d-flex align-items-center gap-2">
                    <i
                      class="bx bx-list-ul @if ($isProgramVirtual) text-danger @else text-primary @endif flex-shrink-0"></i>
                    <strong class="text-nowrap">Program Kerja {{ $loop->iteration }}</strong>
                    @if ($isProgramVirtual)
                      <span class="badge bg-danger ms-2">Tidak Diajukan Kembali</span>
                    @endif
                  </div>
                  <div
                    class="fw-semibold @if ($isProgramVirtual) text-danger @else text-dark @endif fs-5 mt-1">
                    {{ $programCode }} — {{ $programName }}</div>
                </div>
                <div class="text-end">
                  <span class="text-muted small d-block">Subtotal Program</span>
                  <strong class="text-primary fs-5 has-tooltip @if ($isProgramVirtual) text-danger @endif">
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
                          ({{ $prevPeriod }})
                        </div>
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
                  $isWpVirtual = $wp['is_virtual'] ?? false;
                  $activity = $wp['model'] ? $wp['model']->activity : null;
                  $activityCode = $activity ? $activity->code : ($wp['program_code'] ?: '-');
                  $activityTitle = $activity ? $activity->title : ($wp['program_name'] ?: '-');
                  $actSubtotal = (float) $wp['total_budget'];
                @endphp
                <div class="activity-card p-3 mb-3 @if ($isWpVirtual) border-danger @endif"
                  style="@if ($isWpVirtual) border-color: #ff3e1d !important; background-color: #fff9f9; @else background-color: #ffffff; @endif">
                  <div class="d-flex justify-content-between align-items-start gap-3 border-bottom pb-2 mb-3">
                    {{-- Left: icon + activity info --}}
                    <div class="d-flex align-items-center gap-2 flex-grow-1 overflow-hidden">
                      <span
                        class="badge @if ($isWpVirtual) bg-label-danger @else bg-label-primary @endif rounded-circle p-2 flex-shrink-0"><i
                          class="bx bx-task"></i></span>
                      <div class="overflow-hidden">
                        <div class="d-flex align-items-center flex-wrap gap-1 mb-1">
                          <h6 class="mb-0 fw-bold @if ($isWpVirtual) text-danger @endif">Kegiatan
                            {{ $actIdx + 1 }}</h6>
                          @if ($isWpVirtual)
                            <span class="badge bg-danger" style="font-size: 0.6rem;">Tidak Diajukan Kembali</span>
                          @endif
                        </div>
                        <span
                          class="@if ($isWpVirtual) text-danger @else text-muted @endif small d-block text-truncate">{{ $activityCode }}
                          — {{ $activityTitle }}</span>
                      </div>
                    </div>
                    {{-- Right: approval controls stacked above subtotal --}}
                    <div class="d-flex flex-column align-items-end gap-2 flex-shrink-0">
                      @if (!$isWpVirtual)
                        @php
                          $wpModelId = $wp['model']?->id;
                          $currStatus = $this->activityStatuses[$wpModelId] ?? 'pending';
                        @endphp
                        @if ($this->canApprove())
                          <div class="btn-group btn-group-sm" role="group">
                            <button type="button"
                              class="btn {{ $currStatus === 'approved' ? 'btn-success' : 'btn-outline-success' }}"
                              wire:click="setActivityStatus({{ $wpModelId }}, 'approved')">
                              <i class="bx bx-check me-1"></i>Setujui
                            </button>
                            <button type="button"
                              class="btn {{ $currStatus === 'rejected' ? 'btn-danger' : 'btn-outline-danger' }}"
                              wire:click="setActivityStatus({{ $wpModelId }}, 'rejected')">
                              <i class="bx bx-x me-1"></i>Tolak
                            </button>
                          </div>
                        @else
                          @if ($currStatus === 'approved')
                            <span class="badge bg-label-success fs-6"><i
                                class="bx bx-check-circle me-1"></i>Disetujui</span>
                          @elseif($currStatus === 'rejected')
                            <span class="badge bg-label-danger fs-6"><i class="bx bx-x-circle me-1"></i>Revisi</span>
                          @else
                            <span class="badge bg-label-secondary fs-6"><i
                                class="bx bx-time-five me-1"></i>Pending</span>
                          @endif
                        @endif
                      @endif
                      <div class="text-end">
                        <span class="text-muted small d-block">Subtotal Kegiatan</span>
                        <strong
                          class="has-tooltip @if ($isWpVirtual) text-danger @else text-dark @endif">
                          Rp {{ number_format($actSubtotal, 0, ',', '.') }}
                          <span class="custom-tooltip-content tooltip-align-right">
                            @php
                              $prevWpId = $wp['work_plan_id'] ?? null;
                              $prevActId = $wp['activity_id'] ?? null;
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
                  </div>

                  @if ($wp['description'])
                    <div class="mb-3">
                      <label class="text-muted small d-block">Deskripsi / Tujuan</label>
                      <p class="mb-0 text-dark @if ($isWpVirtual) text-danger @endif"
                        style="white-space: pre-line;">{{ $wp['description'] }}</p>
                    </div>
                  @endif

                  {{-- Revision Changes Summary --}}
                  @if (!$isWpVirtual && isset($revisionChanges[$wp['model']?->id]))
                    @php
                      $actChanges = $revisionChanges[$wp['model']->id];
                    @endphp
                    @if ($actChanges['has_changes'])
                      <div class="alert alert-warning border-warning p-3 mb-3" style="background-color: #fffdf5;">
                        <h6 class="alert-heading fw-bold text-warning mb-2 d-flex align-items-center">
                          <i class="bx bx-edit-alt me-1"></i> Perubahan pada Revisi Ini (Versi
                          {{ $submission->current_version - 1 }} → {{ $submission->current_version }})
                        </h6>
                        <ul class="mb-0 ps-3 small text-dark" style="list-style-type: disc;">
                          @foreach ($actChanges['activity_level_changes'] as $c)
                            <li><strong>{{ $c['field'] }}</strong> diubah dari <code>{{ $c['old'] }}</code>
                              menjadi <code>{{ $c['new'] }}</code></li>
                          @endforeach
                          @foreach ($actChanges['added_items'] as $item)
                            <li class="text-success"><i class="bx bx-plus-circle me-1"></i> Menambahkan anggaran:
                              <strong>{{ $item['account_code'] }}</strong> - {{ $item['description'] }}
                              ({{ $item['quantity'] }} {{ $item['unit'] }} @ Rp
                              {{ number_format($item['unit_price'], 0, ',', '.') }} = Rp
                              {{ number_format($item['total_price'], 0, ',', '.') }})
                            </li>
                          @endforeach
                          @foreach ($actChanges['removed_items'] as $item)
                            <li class="text-danger"><i class="bx bx-minus-circle me-1"></i> Menghapus anggaran:
                              <strong>{{ $item['account_code'] }}</strong> - {{ $item['description'] }} (Sebelumnya:
                              Rp {{ number_format($item['total_price'], 0, ',', '.') }})
                            </li>
                          @endforeach
                          @foreach ($actChanges['modified_items'] as $item)
                            <li>
                              <i class="bx bx-pencil text-warning me-1"></i> Mengubah anggaran
                              <strong>{{ $item['account_code'] }}</strong> - {{ $item['description'] }}:
                              <ul class="mb-0 ps-3" style="list-style-type: circle;">
                                @if (($item['old']['description'] ?? '') !== ($item['new']['description'] ?? ''))
                                  <li>Deskripsi: <code>{{ $item['old']['description'] }}</code> →
                                    <code>{{ $item['new']['description'] }}</code>
                                  </li>
                                @endif
                                @if (
                                    ($item['old']['quantity'] ?? 0) != ($item['new']['quantity'] ?? 0) ||
                                        ($item['old']['unit'] ?? '') !== ($item['new']['unit'] ?? ''))
                                  <li>Volume: <code>{{ $item['old']['quantity'] }} {{ $item['old']['unit'] }}</code> →
                                    <code>{{ $item['new']['quantity'] }} {{ $item['new']['unit'] }}</code>
                                  </li>
                                @endif
                                @if (($item['old']['unit_price'] ?? 0) != ($item['new']['unit_price'] ?? 0))
                                  <li>Harga Satuan: <code>Rp
                                      {{ number_format($item['old']['unit_price'], 0, ',', '.') }}</code> → <code>Rp
                                      {{ number_format($item['new']['unit_price'], 0, ',', '.') }}</code></li>
                                @endif
                                @if (($item['old']['total_price'] ?? 0) != ($item['new']['total_price'] ?? 0))
                                  <li>Total: <code>Rp
                                      {{ number_format($item['old']['total_price'], 0, ',', '.') }}</code> → <code>Rp
                                      {{ number_format($item['new']['total_price'], 0, ',', '.') }}</code></li>
                                @endif
                                @if (($item['old']['remarks'] ?? '') !== ($item['new']['remarks'] ?? ''))
                                  <li>Catatan: <code>{{ $item['old']['remarks'] ?: '-' }}</code> →
                                    <code>{{ $item['new']['remarks'] ?: '-' }}</code>
                                  </li>
                                @endif
                              </ul>
                            </li>
                          @endforeach
                        </ul>
                      </div>
                    @endif
                  @endif

                  <div class="row g-2 mb-3 small">
                    <div class="col-auto">
                      <span class="text-muted">Target Output:</span> <span
                        class="fw-medium @if ($isWpVirtual) text-danger @else text-dark @endif">{{ $wp['output_target'] ?? '-' }}</span>
                    </div>
                    <div class="col-auto ms-3">
                      <span class="text-muted">Volume:</span> <span
                        class="fw-medium @if ($isWpVirtual) text-danger @else text-dark @endif">{{ $wp['quantity'] }}
                        {{ $wp['unit'] }}</span>
                    </div>
                  </div>

                  @if (!$isWpVirtual && ($this->activityStatuses[$wp['model']?->id] ?? 'pending') === 'rejected')
                    <div class="card bg-label-danger border-0 p-3 mb-3 animate__animated animate__fadeIn"
                      wire:key="rev-notes-edit-{{ $wp['model']?->id }}">
                      <label class="form-label text-danger fw-semibold small">Catatan Revisi Kegiatan <span
                          class="text-danger">*</span></label>
                      @if ($this->canApprove())
                        <textarea id="activity-revision-note-{{ $wp['model']?->id }}" class="form-control bg-white"
                          wire:model.blur="activityRevisionNotes.{{ $wp['model']?->id }}" rows="2"
                          placeholder="Tuliskan catatan perbaikan untuk kegiatan ini..."></textarea>
                      @else
                        <p class="mb-0 text-dark small">
                          {{ $wp['model']?->revision_notes ?: 'Tidak ada catatan revisi.' }}</p>
                      @endif
                    </div>
                  @elseif(!$isWpVirtual && !empty($wp['model']?->revision_notes))
                    <div class="card bg-label-danger border-0 p-3 mb-3 animate__animated animate__fadeIn"
                      wire:key="rev-notes-view-{{ $wp['model']?->id }}">
                      <label class="form-label text-danger fw-semibold small">Catatan Revisi Kegiatan</label>
                      <p class="mb-0 text-dark small">{{ $wp['model']?->revision_notes }}</p>
                    </div>
                  @endif

                  <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0">
                      <thead>
                        <tr>
                          <th>Uraian & Detail Belanja</th>
                          <th class="text-center" style="width: 10%;">Vol</th>
                          <th style="width: 12%;">Satuan</th>
                          <th class="text-end" style="width: 14%;">Harga Satuan</th>
                          <th class="text-end" style="width: 16%;">Total</th>
                          <th class="text-center" style="width: 8%;">Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach ($wp['grouped_items'] as $accountCode => $items)
                          @php
                            $firstItem = $items[0];
                            $coaGroupSubtotal = collect($items)->sum('total_price');
                            $prevWpId = $wp['work_plan_id'] ?? null;
                            $prevCode = $accountCode;
                            $itemCounters = [];
                          @endphp
                          <tr class="table-light fw-semibold">
                            <td colspan="6" class="text-dark bg-lighter py-2 px-3">
                              <div class="d-flex justify-content-between align-items-center gap-2">
                                <div class="d-flex align-items-center gap-1 min-w-0">
                                  <i class="bx bx-subdirectory-right text-primary flex-shrink-0"></i>
                                  <span class="text-truncate"><strong>{{ $accountCode ?? '-' }}</strong> —
                                    {{ $firstItem['description'] }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-3 flex-shrink-0 text-end">
                                  @php
                                    $prevActId = $wp['activity_id'] ?? null;
                                    $coaKey =
                                        $prevWpId && $prevActId && $prevCode
                                            ? "{$prevWpId}-{$prevActId}-{$prevCode}"
                                            : null;
                                    $prevCoaData =
                                        $coaKey && isset($prevData['map']['coas'][$coaKey])
                                            ? $prevData['map']['coas'][$coaKey]
                                            : null;
                                    $prevPeriod = $prevData['period'] ?? '-';

                                    $prevCoaBudget = $prevCoaData ? (float) $prevCoaData['budget'] : 0.0;
                                    $coaDiff = $coaGroupSubtotal - $prevCoaBudget;
                                    $coaPct = $prevCoaBudget > 0 ? ($coaDiff / $prevCoaBudget) * 100 : 0;
                                  @endphp


                                  <div class="has-tooltip" style="font-size:0.78rem; line-height:1.2;">
                                    <span class="text-muted" style="font-size:0.68rem;">Sub-total:</span>
                                    <span class="fw-bold text-primary">
                                      Rp {{ number_format($coaGroupSubtotal, 0, ',', '.') }}
                                    </span>
                                    <span class="custom-tooltip-content tooltip-align-right">
                                      @if ($prevCoaData)
                                        <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP
                                          Periode Sebelumnya ({{ $prevPeriod }})</div>
                                        <div class="row text-center">
                                          <div class="col-4 border-end">
                                            <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran
                                            </div>
                                            <div class="fw-bold text-white" style="font-size: 0.75rem;">Rp
                                              {{ number_format($prevCoaData['budget'], 0, ',', '.') }}</div>
                                          </div>
                                          <div class="col-4 border-end">
                                            <div class="text-white-50 small" style="font-size: 0.65rem;">Realisasi
                                            </div>
                                            <div class="fw-bold text-white text-success" style="font-size: 0.75rem;">
                                              Rp {{ number_format($prevCoaData['realization'], 0, ',', '.') }}</div>
                                          </div>
                                          <div class="col-4">
                                            <div class="text-white-50 small" style="font-size: 0.65rem;">Proyeksi
                                            </div>
                                            <div class="fw-bold text-white text-warning" style="font-size: 0.75rem;">
                                              Rp {{ number_format($prevCoaData['projection'] ?? 0, 0, ',', '.') }}
                                            </div>
                                          </div>
                                        </div>
                                      @else
                                        <div class="text-center text-white-50 py-1">Tidak ada data di periode
                                          sebelumnya</div>
                                      @endif
                                    </span>
                                  </div>
                                </div>
                              </div>
                            </td>
                          </tr>
                          @foreach ($items as $bi)
                            @php
                              $descKey = trim(strtolower($bi['description']));
                              $itemCounters[$descKey] = ($itemCounters[$descKey] ?? 0) + 1;
                              $seq = $itemCounters[$descKey];

                              $isBiVirtual = $bi['is_virtual'] ?? false;
                              $allocationModalId =
                                  'allocationDetailModal-' .
                                  ($isBiVirtual
                                      ? md5($bi['account_code'] . $bi['description'] . $seq)
                                      : $bi['model']->id);
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

                              $prevWpId = $wp['work_plan_id'] ?? null;
                              $prevActId = $wp['activity_id'] ?? null;
                              $itemKey =
                                  $prevWpId && $prevActId && $accountCode
                                      ? "{$prevWpId}-{$prevActId}-{$accountCode}-" .
                                          trim(strtolower($bi['description'])) .
                                          '-' .
                                          $seq
                                      : null;
                              $prevItemData =
                                  $itemKey && isset($prevData['map']['items'][$itemKey])
                                      ? $prevData['map']['items'][$itemKey]
                                      : null;
                              $prevItemBudget = $prevItemData ? (float) $prevItemData['budget'] : null;
                              $itemDiff = $prevItemBudget !== null ? $bi['total_price'] - $prevItemBudget : null;
                              $itemPct =
                                  $prevItemBudget !== null && $prevItemBudget > 0
                                      ? ($itemDiff / $prevItemBudget) * 100
                                      : 0;

                              // Dynamic revision tracking comparisons
                              $actChanges = isset($revisionChanges[$wp['model']?->id])
                                  ? $revisionChanges[$wp['model']->id]
                                  : null;
                              $biModelId = !$isBiVirtual && $bi['model'] ? $bi['model']->id : null;
                              $isBiRevisionAdded =
                                  $biModelId && $actChanges && in_array($biModelId, $actChanges['added_bi_ids']);
                              $biRevisionModifiedData =
                                  $biModelId && $actChanges && isset($actChanges['modified_bi_map'][$biModelId])
                                      ? $actChanges['modified_bi_map'][$biModelId]
                                      : null;
                            @endphp
                            <tr
                              @if ($isBiVirtual) style="background-color: #fff9f9;" @elseif($isBiRevisionAdded) style="background-color: #e8f5e9;" @elseif($biRevisionModifiedData) style="background-color: #fffde7;" @endif>
                              <td>
                                <span
                                  class="@if ($isBiVirtual) text-danger text-decoration-line-through @endif">
                                  {{ $bi['remarks'] ?: $bi['description'] }}
                                </span>
                                @if ($isBiVirtual)
                                  <span class="badge bg-label-danger ms-1" style="font-size: 0.6rem;">Dihapus</span>
                                @endif
                                @if ($isBiRevisionAdded)
                                  <span class="badge bg-success ms-1" style="font-size: 0.6rem;">Baru</span>
                                @endif
                                @if ($biRevisionModifiedData)
                                  <span class="badge bg-warning text-dark ms-1"
                                    style="font-size: 0.6rem;">Diubah</span>
                                @endif
                              </td>
                              <td class="text-center @if ($isBiVirtual) text-danger @endif">
                                @if (!$isBiVirtual && $bi['model'] && $bi['model']->unit_2)
                                  <div>{{ $bi['quantity'] }}</div>
                                  <div class="text-muted small border-top mt-1 pt-1">{{ $bi['model']->quantity_2 }}
                                  </div>
                                @else
                                  {{ $bi['quantity'] }}
                                @endif
                                @if ($biRevisionModifiedData && $biRevisionModifiedData['quantity'] != $bi['quantity'])
                                  <div class="text-muted small text-decoration-line-through">Sblm:
                                    {{ $biRevisionModifiedData['quantity'] }}</div>
                                @endif
                              </td>
                              <td class="@if ($isBiVirtual) text-danger @endif">
                                @if (!$isBiVirtual && $bi['model'] && $bi['model']->unit_2)
                                  <div>{{ $bi['unit'] }}</div>
                                  <div class="text-muted small border-top mt-1 pt-1">{{ $bi['model']->unit_2 }}</div>
                                @else
                                  {{ $bi['unit'] }}
                                @endif
                                @if ($biRevisionModifiedData && ($biRevisionModifiedData['unit'] ?? '') !== ($bi['unit'] ?? ''))
                                  <div class="text-muted small text-decoration-line-through">Sblm:
                                    {{ $biRevisionModifiedData['unit'] }}</div>
                                @endif
                              </td>
                              <td class="text-end @if ($isBiVirtual) text-danger @endif">
                                Rp {{ number_format($bi['unit_price'], 0, ',', '.') }}
                                @if ($biRevisionModifiedData && $biRevisionModifiedData['unit_price'] != $bi['unit_price'])
                                  <div class="text-muted small text-decoration-line-through text-end">Sblm: Rp
                                    {{ number_format($biRevisionModifiedData['unit_price'], 0, ',', '.') }}</div>
                                @endif
                              </td>
                              <td class="text-end text-nowrap">
                                <div class="fw-semibold @if ($isBiVirtual) text-danger @else text-primary @endif has-tooltip">
                                  Rp {{ number_format($bi['total_price'], 0, ',', '.') }}
                                  <span class="custom-tooltip-content tooltip-align-right" style="width: 280px; font-weight: normal;">
                                    @if ($prevItemBudget !== null)
                                      <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP Periode Sebelumnya ({{ $prevPeriod }})</div>
                                      <div class="row text-center" style="min-width: 250px;">
                                        <div class="col-6 border-end">
                                          <div class="text-white-50 small" style="font-size: 0.65rem;">Anggaran Sblm</div>
                                          <div class="fw-bold text-white" style="font-size: 0.75rem;">Rp {{ number_format($prevItemBudget, 0, ',', '.') }}</div>
                                        </div>
                                        <div class="col-6">
                                          <div class="text-white-50 small" style="font-size: 0.65rem;">Selisih (Δ)</div>
                                          <div class="fw-bold @if($itemDiff > 0) text-danger @elseif($itemDiff < 0) text-success @else text-white @endif" style="font-size: 0.75rem;">
                                            @if ($itemDiff > 0)
                                              ↑ +{{ number_format($itemPct, 1) }}%<br><span style="font-size: 0.68rem;">(+Rp {{ number_format($itemDiff, 0, ',', '.') }})</span>
                                            @elseif ($itemDiff < 0)
                                              ↓ -{{ number_format(abs($itemPct), 1) }}%<br><span style="font-size: 0.68rem;">(-Rp {{ number_format(abs($itemDiff), 0, ',', '.') }})</span>
                                            @else
                                              = 0%<br><span style="font-size: 0.68rem;">(Rp 0)</span>
                                            @endif
                                          </div>
                                        </div>
                                      </div>
                                    @else
                                      <div class="text-center text-white-50 py-1">Tidak ada data di periode sebelumnya</div>
                                    @endif
                                  </span>
                                </div>
                                @if ($biRevisionModifiedData && $biRevisionModifiedData['total_price'] != $bi['total_price'])
                                  <div class="text-muted small text-decoration-line-through text-end" style="font-size: 0.75rem;">Sblm: Rp
                                    {{ number_format($biRevisionModifiedData['total_price'], 0, ',', '.') }}</div>
                                @endif
                              </td>
                              <td class="text-center">
                                @if (!$isBiVirtual && ($bi['monthlies']->isNotEmpty() || $bi['cashOuts']->isNotEmpty()))
                                  <div class="d-flex justify-content-center">
                                    <button type="button" class="btn btn-xs btn-outline-primary"
                                      data-bs-toggle="modal" data-bs-target="#{{ $allocationModalId }}"
                                      title="Detail Alokasi">
                                      <i class="bx bx-detail"></i>
                                    </button>
                                    <!-- Modal Detail Alokasi (Merged) -->
                                    <div class="modal fade" id="{{ $allocationModalId }}" tabindex="-1"
                                      aria-hidden="true" wire:key="allocation-modal-{{ $bi['model']->id }}">
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
                                                    <span class="text-muted d-block mb-1">Kode Akun</span>
                                                    <span
                                                      class="fw-semibold text-dark fs-6">{{ $bi['account_code'] ?? '-' }}</span>
                                                  </div>
                                                  <div class="col border-end">
                                                    <span class="text-muted d-block mb-1">Deskripsi / Detail
                                                      Belanja</span>
                                                    <span
                                                      class="fw-semibold text-dark fs-6 text-wrap">{{ $bi['description'] }}</span>
                                                    @if ($bi['remarks'])
                                                      <div class="text-muted mt-1 small">Ket: {{ $bi['remarks'] }}
                                                      </div>
                                                    @endif
                                                  </div>
                                                  <div class="col-md-auto border-end text-nowrap">
                                                    <span class="text-muted d-block mb-1">Volume</span>
                                                    <span class="fw-semibold text-dark fs-6">
                                                      @if (!$isBiVirtual && $bi['model'] && $bi['model']->unit_2)
                                                        {{ $bi['quantity'] }} {{ $bi['unit'] }} x
                                                        {{ $bi['model']->quantity_2 }} {{ $bi['model']->unit_2 }}
                                                      @else
                                                        {{ $bi['quantity'] }} {{ $bi['unit'] }}
                                                      @endif
                                                    </span>
                                                  </div>
                                                  <div class="col-md-auto text-nowrap">
                                                    <span class="text-muted d-block mb-1">Total Anggaran</span>
                                                    <span class="fw-bold text-primary fs-6">Rp
                                                      {{ number_format($bi['total_price'], 0, ',', '.') }}</span>
                                                  </div>
                                                </div>
                                              </div>
                                            </div>
                                            @php
                                              $monthliesByMonth = $bi['monthlies']->keyBy('month');
                                              $cashOutsByMonth = $bi['cashOuts']->keyBy('month');
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
                                              $coa = $bi['model']?->coa ?? \App\Models\Coa::with(['coaGroup', 'cashflowGroup', 'differenceGroup'])->where('code', $bi['account_code'])->first();
                                            @endphp

                                            @if (!$hasAnyValue)
                                              <div class="text-center text-muted py-4">
                                                <i class="bx bx-info-circle fs-3 mb-2 d-block"></i>
                                                <span class="small">Tidak ada data alokasi anggaran</span>
                                              </div>
                                            @else
                                              <div class="border rounded-2 table-responsive mb-2">
                                                <table class="table table-sm table-bordered align-middle mb-0" style="min-width: 750px;">
                                                  <thead class="table-primary">
                                                    <tr>
                                                      <th class="text-center" style="width: 90px;">Bulan</th>
                                                      <th class="text-end">
                                                        Distribusi Beban (Rp)
                                                        <div class="small fw-normal text-muted" style="font-size: 0.65rem; opacity: 0.85;">({{ $coa?->coaGroup?->name ?: '-' }})</div>
                                                      </th>
                                                      <th class="text-end">
                                                        Rencana Kas Keluar (Rp)
                                                        <div class="small fw-normal text-muted" style="font-size: 0.65rem; opacity: 0.85;">({{ $coa?->cashflowGroup?->name ?: '-' }})</div>
                                                      </th>
                                                      <th class="text-end">
                                                        Selisih (Rp)
                                                        <div class="small fw-normal text-muted" style="font-size: 0.65rem; opacity: 0.85;">({{ $coa?->differenceGroup?->name ?: '-' }})</div>
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
                                              data-bs-dismiss="modal">Tutup</button>
                                          </div>
                                        </div>
                                      </div>
                                    </div>
                                @endif
                              </td>
                            </tr>
                          @endforeach

                          {{-- Removed Budget Items in Revision --}}
                          @if ($actChanges && !empty($actChanges['removed_items']))
                            @foreach ($actChanges['removed_items'] as $removedBi)
                              <tr style="background-color: #fff5f5;">
                                <td class="text-danger">
                                  <span class="text-decoration-line-through">
                                    <strong>{{ $removedBi['account_code'] }}</strong> -
                                    {{ $removedBi['remarks'] ?: $removedBi['description'] }}
                                  </span>
                                  <span class="badge bg-label-danger ms-1" style="font-size: 0.6rem;">Dihapus pada
                                    Revisi</span>
                                </td>
                                <td class="text-center text-danger">
                                  {{ $removedBi['quantity'] }}
                                </td>
                                <td class="text-danger">
                                  {{ $removedBi['unit'] }}
                                </td>
                                <td class="text-end text-danger">
                                  Rp {{ number_format($removedBi['unit_price'], 0, ',', '.') }}
                                </td>
                                <td class="text-end text-danger text-decoration-line-through fw-semibold">
                                  Rp {{ number_format($removedBi['total_price'], 0, ',', '.') }}
                                </td>
                                <td></td>
                              </tr>
                            @endforeach
                          @endif
                        @endforeach
                      </tbody>
                    </table>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        @endforeach
      @endif
    </div>

    </div>
  </div>

  @if ($showAddActivityModal)
    <div class="modal fade show" tabindex="-1" style="display: block; background: rgba(0, 0, 0, 0.5);" role="dialog" wire:key="add-activity-modal-wrapper">
      <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header border-bottom py-3">
            <h5 class="modal-title fw-bold text-primary"><i class="bx bx-plus-circle me-2"></i>Tambah Program Kegiatan</h5>
            <button type="button" class="btn-close" wire:click="$set('showAddActivityModal', false)" aria-label="Close"></button>
          </div>
          <div class="modal-body pb-3" style="max-height: 70vh; overflow-y: auto;">
            <div class="mb-3">
              <label class="form-label fw-semibold">Program Kerja (Work Plan) <span class="text-danger">*</span></label>
              <select class="form-select @error('selectedWorkPlanId') is-invalid @enderror" wire:model.live="selectedWorkPlanId">
                <option value="">-- Pilih Program Kerja --</option>
                @foreach ($this->workPlansList as $wp)
                  <option value="{{ $wp->id }}">{{ $wp->code }} — {{ $wp->title }}</option>
                @endforeach
              </select>
              @error('selectedWorkPlanId')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Kegiatan (Activity) <span class="text-danger">*</span></label>
              <select class="form-select @error('selectedActivityId') is-invalid @enderror" wire:model.live="selectedActivityId" @disabled(empty($selectedWorkPlanId))>
                <option value="">-- Pilih Kegiatan --</option>
                @foreach ($this->activitiesList as $act)
                  <option value="{{ $act->id }}">{{ $act->code }} — {{ $act->title }}</option>
                @endforeach
              </select>
              @error('selectedActivityId')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            @if ($selectedActivityId)
              <div class="mb-3">
                <label class="form-label fw-semibold">Deskripsi / Tujuan Kegiatan</label>
                <textarea class="form-control @error('activityDescription') is-invalid @enderror" wire:model="activityDescription" rows="2" placeholder="Deskripsi/tujuan kegiatan"></textarea>
                @error('activityDescription')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-semibold">Volume Kegiatan <span class="text-danger">*</span></label>
                  <input type="number" class="form-control @error('activityQuantity') is-invalid @enderror" wire:model="activityQuantity" min="1">
                  @error('activityQuantity')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-semibold">Satuan Volume Kegiatan <span class="text-danger">*</span></label>
                  <input type="text" class="form-control @error('activityUnit') is-invalid @enderror" wire:model="activityUnit" placeholder="Contoh: Paket, Kali, Bulan">
                  @error('activityUnit')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Target Output</label>
                <input type="text" class="form-control @error('activityOutputTarget') is-invalid @enderror" wire:model="activityOutputTarget" placeholder="Contoh: Laporan Keuangan, Dokumen">
                @error('activityOutputTarget')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <hr class="my-4">
              <h6 class="fw-bold mb-3 text-secondary"><i class="bx bx-link me-1"></i>Rincian Anggaran (COA Terkait)</h6>
              @if (empty($budgetItemsInput))
                <div class="alert alert-warning small d-flex align-items-center mb-0">
                  <i class="bx bx-info-circle me-2 fs-5"></i>
                  <span>Kegiatan ini belum memiliki COA yang dipetakan. Silakan petakan COA terlebih dahulu di menu Master Data.</span>
                </div>
              @else
                <div class="table-responsive border rounded">
                  <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>COA</th>
                        <th style="width: 100px;">Volume</th>
                        <th style="width: 120px;">Satuan</th>
                        <th style="width: 180px;">Harga Satuan</th>
                        <th>Keterangan (Remarks)</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($budgetItemsInput as $coaId => $bi)
                        <tr wire:key="coa-input-{{ $coaId }}">
                          <td class="align-middle">
                            <strong class="text-primary">{{ $bi['code'] }}</strong><br>
                            <small class="text-muted text-wrap">{{ $bi['title'] }}</small>
                          </td>
                          <td class="align-middle">
                            <input type="number" class="form-control form-control-sm @error('budgetItemsInput.' . $coaId . '.quantity') is-invalid @enderror" wire:model="budgetItemsInput.{{ $coaId }}.quantity" min="1">
                          </td>
                          <td class="align-middle">
                            <input type="text" class="form-control form-control-sm @error('budgetItemsInput.' . $coaId . '.unit') is-invalid @enderror" wire:model="budgetItemsInput.{{ $coaId }}.unit" placeholder="Pcs">
                          </td>
                          <td class="align-middle">
                            <div class="input-group input-group-merge input-group-sm">
                              <span class="input-group-text">Rp</span>
                              <input type="number" class="form-control @error('budgetItemsInput.' . $coaId . '.unit_price') is-invalid @enderror" wire:model="budgetItemsInput.{{ $coaId }}.unit_price" placeholder="Harga">
                            </div>
                          </td>
                          <td class="align-middle">
                            <input type="text" class="form-control form-control-sm @error('budgetItemsInput.' . $coaId . '.remarks') is-invalid @enderror" wire:model="budgetItemsInput.{{ $coaId }}.remarks" placeholder="Catatan">
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @endif
            @endif
          </div>
          <div class="modal-footer border-top py-3">
            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showAddActivityModal', false)">Batal</button>
            <button type="button" class="btn btn-primary" wire:click="saveActivity" @disabled(!$selectedActivityId || empty($budgetItemsInput))>
              <i class="bx bx-save me-1"></i> Simpan Kegiatan
            </button>
          </div>
        </div>
      </div>
    </div>
  @endif
</div>
