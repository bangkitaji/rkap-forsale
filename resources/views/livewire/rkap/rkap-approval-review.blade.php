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
    <div class="col-xl-9 col-lg-8">
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

      @if (
          (auth()->user()->isPresidentDirector() || auth()->user()->isDirekturFinance()) &&
              in_array($submission->status, ['pdir_review', 'approved']))
        <!-- Helicopter View -->
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
          <div>
            <h5 class="mb-1"><i class="bx bx-spreadsheet me-2 text-primary"></i>Helikopter-View Pengajuan RKAP</h5>
            <p class="text-muted small mb-0">Menampilkan akumulasi anggaran yang dikompilasi berdasarkan kategori Profit
              & Loss.</p>
          </div>
          <span
            class="badge bg-label-primary">{{ auth()->user()->isPresidentDirector() ? 'Direktur Utama Approval' : 'Direktur Finance Approval' }}</span>
        </div>

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
      @else
        <!-- Work Plans -->
        <h5 class="mb-3">Rincian Program Kerja</h5>
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
                          <th class="text-end" style="width: 14%;">Total</th>
                          <th class="text-end" style="width: 12%;">RKAP Sblm</th>
                          <th class="text-end" style="width: 12%;">Selisih (Δ)</th>
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
                            <td colspan="8" class="text-dark bg-lighter py-2 px-3">
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
                              <td class="text-end">
                                <div
                                  class="fw-semibold @if ($isBiVirtual) text-danger @else text-primary @endif">
                                  Rp {{ number_format($bi['total_price'], 0, ',', '.') }}</div>
                                @if ($biRevisionModifiedData && $biRevisionModifiedData['total_price'] != $bi['total_price'])
                                  <div class="text-muted small text-decoration-line-through text-end">Sblm: Rp
                                    {{ number_format($biRevisionModifiedData['total_price'], 0, ',', '.') }}</div>
                                @endif
                              </td>
                              <!-- RKAP Sblm -->
                              <td class="text-end text-secondary" style="font-size: 0.8rem;">
                                @if ($prevItemBudget !== null)
                                  Rp {{ number_format($prevItemBudget, 0, ',', '.') }}
                                @else
                                  <span class="text-muted small">-</span>
                                @endif
                              </td>
                              <!-- Selisih (Δ) -->
                              <td
                                class="text-end fw-semibold @if ($itemDiff > 0) text-danger @elseif($itemDiff < 0) text-success @else text-muted @endif"
                                style="font-size: 0.8rem;">
                                @if ($itemDiff !== null)
                                  @if ($itemDiff > 0)
                                    ↑ +{{ number_format($itemPct, 1) }}%
                                  @elseif($itemDiff < 0)
                                    ↓ -{{ number_format(abs($itemPct), 1) }}%
                                  @else
                                    = 0%
                                  @endif
                                @else
                                  <span class="text-muted small">-</span>
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
                                                  <div class="col-md-3 border-end">
                                                    <span class="text-muted d-block mb-1">Kode Akun</span>
                                                    <span
                                                      class="fw-semibold text-dark fs-6">{{ $bi['account_code'] ?? '-' }}</span>
                                                  </div>
                                                  <div class="col-md-5 border-end">
                                                    <span class="text-muted d-block mb-1">Deskripsi / Detail
                                                      Belanja</span>
                                                    <span
                                                      class="fw-semibold text-dark fs-6 text-wrap">{{ $bi['description'] }}</span>
                                                    @if ($bi['remarks'])
                                                      <div class="text-muted mt-1 small">Ket: {{ $bi['remarks'] }}
                                                      </div>
                                                    @endif
                                                  </div>
                                                  <div class="col-md-2 border-end">
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
                                                  <div class="col-md-2">
                                                    <span class="text-muted d-block mb-1">Total Anggaran</span>
                                                    <span class="fw-bold text-primary fs-6">Rp
                                                      {{ number_format($bi['total_price'], 0, ',', '.') }}</span>
                                                  </div>
                                                </div>
                                              </div>
                                            </div>
                                            <!-- Side-by-side Tables -->
                                            <div class="row g-4">
                                              <!-- Left Column: Distribusi Bulanan -->
                                              <div class="col-md-6">
                                                <div class="border rounded p-3 h-100">
                                                  <h6 class="fw-semibold mb-3 text-primary d-flex align-items-center">
                                                    <i class="bx bx-calendar me-2"></i>Distribusi Bulanan
                                                  </h6>
                                                  @php
                                                    $activeMonthlies = $bi['monthlies']->filter(
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
                                                                  $bi['total_price'] > 0
                                                                      ? ($monthlyRecord->amount / $bi['total_price']) *
                                                                          100
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
                                                    $activeCashOuts = $bi['cashOuts']->filter(
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
                                                                  $bi['total_price'] > 0
                                                                      ? ($cashOutRecord->amount / $bi['total_price']) *
                                                                          100
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
                                <td colspan="3"></td>
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

    <!-- Sidebar: Actions & History -->
    <div class="col-xl-3 col-lg-4">

      <!-- Review Actions (Only if user can review) -->
      @if ($this->canApprove())
        <div class="card mb-4 border-primary">
          <div class="card-header bg-label-primary">
            <h5 class="mb-0 text-primary"><i class="bx bx-check-shield me-2"></i>Aksi Review</h5>
          </div>
          <div class="card-body mt-3">
            @if ($showRevisionForm)
              <div class="mb-3" wire:key="revision-reason-wrapper">
                <label class="form-label text-danger">Alasan Permintaan Revisi <span
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
                <label class="form-label">Catatan Review (Opsional)</label>
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
                <div class="bg-lighter rounded p-2 mb-1">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="text-muted fw-semibold">Status Kegiatan</small>
                    <small
                      class="fw-bold text-{{ $allApproved ? 'success' : 'secondary' }}">{{ $approvedCount }}/{{ $totalActivities }}
                      Disetujui</small>
                  </div>
                  <div class="progress" style="height: 6px;">
                    @if ($totalActivities > 0)
                      <div class="progress-bar bg-success"
                        style="width: {{ ($approvedCount / $totalActivities) * 100 }}%;"></div>
                      <div class="progress-bar bg-danger"
                        style="width: {{ ($rejectedCount / $totalActivities) * 100 }}%;"></div>
                    @endif
                  </div>
                  @if ($rejectedCount > 0 || $pendingCount > 0)
                    <div class="d-flex gap-2 mt-1" style="font-size: 0.7rem;">
                      @if ($approvedCount > 0)
                        <span class="text-success"><i class="bx bx-check-circle me-1"></i>{{ $approvedCount }}
                          Disetujui</span>
                      @endif
                      @if ($rejectedCount > 0)
                        <span class="text-danger"><i class="bx bx-x-circle me-1"></i>{{ $rejectedCount }}
                          Ditolak</span>
                      @endif
                      @if ($pendingCount > 0)
                        <span class="text-secondary"><i class="bx bx-time-five me-1"></i>{{ $pendingCount }}
                          Pending</span>
                      @endif
                    </div>
                  @endif
                </div>

                <button class="btn btn-success w-100" wire:key="btn-approve-rkap" wire:click="approve"
                  wire:loading.attr="disabled" wire:confirm="Yakin menyetujui RKAP ini?" @disabled(!$allApproved)>
                  <i class="bx bx-check-circle me-1"></i> Setujui RKAP
                </button>

                <button class="btn btn-outline-danger w-100" wire:key="btn-open-revision-form"
                  wire:click="openRevisionForm" @disabled(!$hasRejected)>
                  <i class="bx bx-x-circle me-1"></i> Minta Revisi
                </button>
                @if (!$hasRejected)
                  <small class="text-center text-muted"><i class="bx bx-info-circle me-1"></i>Tolak minimal satu
                    kegiatan untuk meminta revisi.</small>
                @endif
              </div>
            @endif
          </div>
        </div>
      @endif





    </div>
  </div>
</div>
