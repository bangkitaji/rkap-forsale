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
      <h6 class="alert-heading mb-1 fw-bold text-warning text-warning-bold">Persetujuan Ditangguhkan
        (Persetujuan Belum Dapat Dilakukan)</h6>
      <span>
        Persetujuan akhir oleh Direktur Utama dan Direktur Finance hanya dapat dilakukan setelah <strong>seluruh
          departemen</strong> menyelesaikan pengajuan RKAP dan telah diverifikasi oleh verifikator.
        Saat ini baru <strong>{{ $this->presidentApprovalStatus['verified_count'] }} dari
          {{ $this->presidentApprovalStatus['total_count'] }}</strong> departemen yang terverifikasi.
      </span>
      @if (!empty($this->presidentApprovalStatus['pending_departments']))
      <div class="mt-2 small text-muted">
        <strong>{{ __('Departemen yang belum terverifikasi:') }}</strong>
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
              <label class="text-muted small">{{ __('Biro Pengaju') }}</label>
              <div class="fw-semibold">{{ $submission->bureau->name ?? '-' }}</div>
              <div class="text-muted small">{{ $submission->bureau->department->directorate->name ?? '-' }}</div>
            </div>
            <div class="col-sm-3">
              <label class="text-muted small">{{ __('Periode') }}</label>
              <div class="fw-semibold">{{ $submission->period->title ?? '-' }}</div>
            </div>
            <div class="col-sm-3">
              <label class="text-muted small">{{ __('Total Anggaran Ajuan') }}</label>
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

              @if (isset($prevData['total_budget']))
              @php
              $prevTotal = $prevData['total_budget'];
              $totalDiff = $submission->total_budget - $prevTotal;
              $totalPct = $prevTotal > 0 ? ($totalDiff / $prevTotal) * 100 : 0;
              @endphp
              <div class="small mt-1 p-1 bg-lighter rounded">
                <span class="text-muted d-block rkap-font-065">Sebelumnya
                  ({{ $prevData['period'] }}):</span>
                <span class="fw-semibold text-secondary rkap-font-072">Rp
                  {{ number_format($prevTotal, 0, ',', '.') }}</span>
                <span
                  class="fw-bold d-block mt-0.5 @if ($totalDiff > 0) text-danger @elseif($totalDiff < 0) text-success @else text-muted @endif rkap-font-072">
                  @if ($totalDiff > 0)
                  <i class="bx bx-trending-up rkap-font-08"></i> +{{ number_format($totalPct, 1) }}%
                  @elseif($totalDiff < 0)
                    <i class="bx bx-trending-down rkap-font-08"></i>
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
            <div class="timeline-steps-line" :style="{ width: (165 * ({{ $allTimelineSteps->count() }} - 1)) + 'px', left: '75px' }"></div>
            @endif

            {{--
              Layout per column (top-to-bottom, 260px total height):
              [timeline-zone-top    118px] — card floats down to bottom edge for ODD steps
              [timeline-zone-axis    24px] — node-dot on top, numbered-circle below (or reversed for even)
              [timeline-zone-bottom 118px] — card floats up to top edge for EVEN steps

              ODD  steps (1, 3, 5…): card is ABOVE the axis → top zone has card, axis has dot-above-circle
              EVEN steps (2, 4, 6…): card is BELOW the axis → bottom zone has card, axis has circle-above-dot
            --}}
            <div class="d-flex align-items-stretch justify-content-start rkap-position-relative rkap-gap-15 rkap-z-2">
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
                  <div class="timeline-card arrow-down timeline-card-{{ $step['color'] }}">
                    <div class="timeline-card-header">
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
                <div class="timeline-zone-axis">
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
                <div class="timeline-zone-axis">
                  <div class="timeline-node-dot bg-{{ $step['color'] }}"></div>
                  <div class="timeline-step-circle border-{{ $step['color'] }}" title="{{ $step['role'] }}">
                    {{ $loop->iteration }}
                  </div>
                </div>

                {{-- Bottom zone: card floats to top of this zone --}}
                <div class="timeline-zone-bottom">
                  <div class="timeline-card arrow-up timeline-card-{{ $step['color'] }}">
                    <div class="timeline-card-header">
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

      <!-- Review Actions (Only if user can review) -->
      @if ($this->canApprove())
      <div class="card mb-4 border-primary shadow-sm">
        <div class="card-header bg-label-primary py-3">
          <h5 class="mb-0 text-primary fw-bold"><i class="bx bx-check-shield me-2"></i>Aksi Review</h5>
        </div>
        <div class="card-body mt-3">
          @if ($isEditMode)
          <div class="mb-3 alert alert-primary d-flex align-items-center">
            <span class="badge bg-primary text-white me-2 p-1"><i class="bx bx-info-circle fs-5"></i></span>
            <span class="small"><strong>Mode Edit Aktif</strong>: Anda dapat mengubah COA pada setiap rincian anggaran dan menambahkan program kegiatan baru melalui form di bawah.</span>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <button class="btn btn-secondary w-100 py-2" type="button" wire:key="btn-cancel-edit" wire:click="cancelEditMode">
                Batal
              </button>
            </div>
            <div class="col-6">
              <button class="btn btn-primary w-100 py-2" type="button" wire:key="btn-submit-edit" wire:click="saveEditMode" wire:loading.attr="disabled">
                {{ __('Simpan Perubahan') }}
              </button>
            </div>
          </div>
          @elseif ($showRevisionForm)
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
              wire:click="$set('showRevisionForm', false)">{{ __('Batal') }}</button>
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
              <div class="progress mb-2 rkap-h-6">
                @if ($totalActivities > 0)
                <div class="progress-bar bg-success"
                  :style="{ width: ({{ $approvedCount }} / {{ $totalActivities }} * 100) + '%' }"></div>
                <div class="progress-bar bg-danger"
                  :style="{ width: ({{ $rejectedCount }} / {{ $totalActivities }} * 100) + '%' }"></div>
                @endif
              </div>
              @if ($rejectedCount > 0 || $pendingCount > 0)
              <div class="d-flex gap-3 rkap-font-075">
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

            <div class="row g-2 mt-2">
              <div class="col">
                <button class="btn btn-success w-100 py-2 text-nowrap" wire:key="btn-approve-rkap" wire:click="approve"
                  wire:loading.attr="disabled" wire:confirm="Yakin menyetujui RKAP ini?" @disabled(!$allApproved)>
                  <i class="bx bx-check-circle me-1"></i> Setujui RKAP
                </button>
              </div>
              <div class="col">
                <button class="btn btn-outline-danger w-100 py-2 text-nowrap" wire:key="btn-open-revision-form"
                  wire:click="openRevisionForm" @disabled(!$hasRejected)>
                  <i class="bx bx-x-circle me-1"></i> Minta Revisi
                </button>
              </div>
              @if (auth()->user()->isVerifikator())
              <div class="col">
                <button class="btn btn-primary w-100 py-2 text-nowrap" wire:click="enterEditMode" wire:key="btn-open-add-activity">
                  <i class="bx bx-edit me-1"></i> Program Kegiatan
                </button>
              </div>
              @endif
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
          <h6 class="text-uppercase text-muted small fw-bold mb-3 mt-4 rkap-ls-1">
            <i class="bx bx-folder-open me-1 text-secondary"></i> {{ $groupName }}
          </h6>
          <div class="row g-3">
            @foreach ($cats as $cat)
            <div class="col-12">
              <div class="card mb-2 border-start border-{{ $cat['color'] }} border-3 shadow-sm">
                <div class="card-body py-3">
                  <div class="d-flex justify-content-between align-items-center cursor-pointer collapsed rkap-user-select-none"
                    data-bs-toggle="collapse" data-bs-target="#cat-collapse-{{ $cat['key'] }}"
                    aria-expanded="false">
                    <div>
                      <h6 class="mb-1 fw-bold text-dark">{{ $cat['label'] }}</h6>
                      <small class="text-muted"><i class="bx bx-chevron-down me-1"></i> Klik untuk melihat rincian
                        {{ count($cat['coas']) }} COA</small>
                    </div>
                    <div class="text-end">
                      <span class="text-muted small d-block rkap-font-07">Anggaran Diajukan</span>
                      <span class="fw-bold text-{{ $cat['color'] }} fs-5">Rp
                        {{ number_format($cat['current_total'], 0, ',', '.') }}</span>

                      @if ($helicopterViewData['prevPeriod'])
                      @php
                      $prevTotal = $cat['prev_total'];
                      $diff = $cat['current_total'] - $prevTotal;
                      $pct = $prevTotal > 0 ? ($diff / $prevTotal) * 100 : 0;
                      @endphp
                      <div class="rkap-font-075 rkap-lh-12">
                        <span class="text-muted">Sebelumnya ({{ $helicopterViewData['prevPeriod'] }}): Rp
                          {{ number_format($prevTotal, 0, ',', '.') }}</span>
                        <span
                          class="fw-bold ms-1 @if ($diff > 0) text-danger @elseif($diff < 0) text-success @else text-muted @endif">
                          @if ($diff > 0)
                          ↑ +{{ number_format($pct, 1) }}%
                          @elseif($diff < 0)
                            ↓ -{{ number_format(abs($pct), 1) }}%
                            @else=0%
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
                      <table class="table table-sm table-hover table-striped mb-0 rkap-font-08">
                        <thead>
                          <tr>
                            <th class="w-20p">{{ __('Kode Akun') }}</th>
                            <th class="w-50p">Judul Akun (COA)</th>
                            <th class="text-end w-15p">Anggaran Ajuan</th>
                            <th class="text-end w-15p">Selisih (Δ)</th>
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
                              {{ number_format($coaItem['current_total'], 0, ',', '.') }}
                            </td>
                            <td
                              class="text-end fw-semibold @if ($coaDiff > 0) text-danger @elseif($coaDiff < 0) text-success @else text-muted @endif">
                              @if ($coaItem['prev_total'] > 0)
                              @if ($coaDiff > 0)
                              ↑ +{{ number_format($coaPct, 1) }}%
                              @elseif($coaDiff < 0)
                                ↓ -{{ number_format(abs($coaPct), 1) }}%
                                @else=0%
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
                  <div class="d-flex justify-content-between align-items-center cursor-pointer collapsed rkap-user-select-none"
                    data-bs-toggle="collapse" data-bs-target="#group-collapse-{{ $group['group_id'] }}"
                    aria-expanded="false">
                    <div>
                      <h6 class="mb-1 fw-bold text-secondary">{{ $group['group_name'] }} (Grup:
                        {{ $group['group_code'] }})
                      </h6>
                      <small class="text-muted"><i class="bx bx-chevron-down me-1"></i> Klik untuk melihat rincian
                        {{ count($group['coas']) }} COA</small>
                    </div>
                    <div class="text-end">
                      <span class="text-muted small d-block rkap-font-07">Anggaran Diajukan</span>
                      <span class="fw-bold text-secondary fs-5">Rp
                        {{ number_format($group['current_total'], 0, ',', '.') }}</span>

                      @if ($helicopterViewData['prevPeriod'])
                      @php
                      $prevTotal = $group['prev_total'];
                      $diff = $group['current_total'] - $prevTotal;
                      $pct = $prevTotal > 0 ? ($diff / $prevTotal) * 100 : 0;
                      @endphp
                      <div class="rkap-font-075 rkap-lh-12">
                        <span class="text-muted">Sebelumnya ({{ $helicopterViewData['prevPeriod'] }}): Rp
                          {{ number_format($prevTotal, 0, ',', '.') }}</span>
                        <span
                          class="fw-bold ms-1 @if ($diff > 0) text-danger @elseif($diff < 0) text-success @else text-muted @endif">
                          @if ($diff > 0)
                          ↑ +{{ number_format($pct, 1) }}%
                          @elseif($diff < 0)
                            ↓ -{{ number_format(abs($pct), 1) }}%
                            @else=0%
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
                      <table class="table table-sm table-hover table-striped mb-0 rkap-font-08">
                        <thead>
                          <tr>
                            <th class="w-20p">{{ __('Kode Akun') }}</th>
                            <th class="w-50p">Judul Akun (COA)</th>
                            <th class="text-end w-15p">Anggaran Ajuan</th>
                            <th class="text-end w-15p">Selisih (Δ)</th>
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
                              {{ number_format($coaItem['current_total'], 0, ',', '.') }}
                            </td>
                            <td
                              class="text-end fw-semibold @if ($coaDiff > 0) text-danger @elseif($coaDiff < 0) text-success @else text-muted @endif">
                              @if ($coaItem['prev_total'] > 0)
                              @if ($coaDiff > 0)
                              ↑ +{{ number_format($coaPct, 1) }}%
                              @elseif($coaDiff < 0)
                                ↓ -{{ number_format(abs($coaPct), 1) }}%
                                @else=0%
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
      <h5 class="mb-4"><i class="bx bx-list-check me-2 text-primary"></i>{{ __('Rincian Program Kerja') }}</h5>

      @if ($isEditMode)
      <div class="card mb-4 border border-primary shadow-sm animate__animated animate__fadeIn" wire:key="add-activity-inline-card">
        <div class="card-header bg-label-primary py-3 d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0 fw-bold text-primary"><i class="bx bx-plus-circle me-2"></i>{{ __('Tambah Program Kegiatan') }}</h5>
          <span class="badge bg-primary">Inline Edit Mode</span>
        </div>
        <div class="card-body mt-3">
          <div class="mb-3">
            <label class="form-label fw-semibold">Program Kerja (Work Plan) <span class="text-danger">*</span></label>
            <div x-data="{
                    open: false,
                    search: '{{ $selectedWorkPlanId ? addslashes($this->workPlansList->firstWhere('id', $selectedWorkPlanId)?->code . ' — ' . $this->workPlansList->firstWhere('id', $selectedWorkPlanId)?->title) : '' }}',
                }" class="position-relative"
              wire:key="wp-select-container-{{ $selectedWorkPlanId ?? 'null' }}">

              <div class="input-group">
                <input type="text"
                  class="form-control @error('selectedWorkPlanId') is-invalid @enderror"
                  placeholder="{{ __('Cari program kerja...') }}" x-model="search" @focus="open = true" @click.outside="open = false"
                  @input="open = true" autocomplete="off" id="wp-search-verifier">
                @if ($selectedWorkPlanId)
                <button type="button" class="btn btn-outline-secondary"
                  wire:click="$set('selectedWorkPlanId', null)" @click="search = ''"
                  title="{{ __('Hapus pilihan') }}">
                  <i class="bx bx-x"></i>
                </button>
                @endif
              </div>
              @error('selectedWorkPlanId')
              <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror

              {{-- Hidden select --}}
              <select wire:model.live="selectedWorkPlanId" class="d-none" id="wp-select-verifier">
                <option value=""></option>
                @foreach ($this->workPlansList as $wp)
                <option value="{{ $wp->id }}">{{ $wp->code }} — {{ $wp->title }}</option>
                @endforeach
              </select>

              {{-- Dropdown options --}}
              <div x-show="open" x-cloak class="position-absolute bg-white border rounded shadow-sm w-100 mt-1 rkap-dropdown-menu">
                @forelse($this->workPlansList as $wp)
                <div
                  class="px-3 py-2 cursor-pointer dropdown-item small {{ $selectedWorkPlanId == $wp->id ? 'bg-primary text-white' : '' }}"
                  x-show="'{{ strtolower(addslashes($wp->code . ' ' . $wp->title)) }}'.includes(search.toLowerCase())"
                  @click="
                            $wire.set('selectedWorkPlanId', {{ $wp->id }});
                            search = '{{ addslashes($wp->code . ' — ' . $wp->title) }}';
                            open = false;
                        ">
                  <span class="fw-semibold text-primary">{{ $wp->code }}</span>
                  <span class="ms-1">{{ $wp->title }}</span>
                </div>
                @empty
                <div class="px-3 py-2 text-muted small">{{ __('Tidak ada data program kerja.') }}</div>
                @endforelse
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Kegiatan (Activity) <span class="text-danger">*</span></label>
            <div x-data="{
                    open: false,
                    search: '{{ $selectedActivityId ? addslashes($this->activitiesList->firstWhere('id', $selectedActivityId)?->code . ' — ' . $this->activitiesList->firstWhere('id', $selectedActivityId)?->title) : '' }}',
                }" class="position-relative"
              wire:key="act-select-container-{{ $selectedWorkPlanId ?? 'null' }}-{{ $selectedActivityId ?? 'null' }}">

              <div class="input-group">
                <input type="text"
                  class="form-control @error('selectedActivityId') is-invalid @enderror"
                  placeholder="{{ __('Cari kegiatan...') }}" x-model="search" @focus="open = true" @click.outside="open = false"
                  @input="open = true" autocomplete="off" id="act-search-verifier"
                  @disabled(empty($selectedWorkPlanId))>
                @if ($selectedActivityId)
                <button type="button" class="btn btn-outline-secondary"
                  wire:click="$set('selectedActivityId', null)" @click="search = ''"
                  title="{{ __('Hapus pilihan') }}">
                  <i class="bx bx-x"></i>
                </button>
                @endif
              </div>
              @error('selectedActivityId')
              <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror

              {{-- Hidden select --}}
              <select wire:model.live="selectedActivityId" class="d-none" id="act-select-verifier" @disabled(empty($selectedWorkPlanId))>
                <option value=""></option>
                @foreach ($this->activitiesList as $act)
                <option value="{{ $act->id }}">{{ $act->code }} — {{ $act->title }}</option>
                @endforeach
              </select>

              {{-- Dropdown options --}}
              <div x-show="open" x-cloak class="position-absolute bg-white border rounded shadow-sm w-100 mt-1 rkap-dropdown-menu">
                @forelse($this->activitiesList as $act)
                <div
                  class="px-3 py-2 cursor-pointer dropdown-item small {{ $selectedActivityId == $act->id ? 'bg-primary text-white' : '' }}"
                  x-show="'{{ strtolower(addslashes($act->code . ' ' . $act->title)) }}'.includes(search.toLowerCase())"
                  @click="
                            $wire.set('selectedActivityId', {{ $act->id }});
                            search = '{{ addslashes($act->code . ' — ' . $act->title) }}';
                            open = false;
                        ">
                  <div class="d-flex flex-column gap-1">
                    <span class="fw-semibold text-primary">{{ $act->code }}</span>
                    <span class="text-secondary rkap-font-085">{{ $act->title }}</span>
                  </div>
                </div>
                @empty
                <div class="px-3 py-2 text-muted small">{{ __('Tidak ada data kegiatan.') }}</div>
                @endforelse
              </div>
            </div>
          </div>

          @if ($selectedActivityId)
          {{-- Activity Details Grid (same layout as submission form) --}}
          <div class="row g-3 mb-3">
            <div class="col-md-12">
              <label class="form-label small fw-semibold">{{ __('Deskripsi / Tujuan') }}</label>
              <textarea class="form-control form-control-sm @error('activityDescription') is-invalid @enderror"
                wire:model="activityDescription" rows="2"
                placeholder="{{ __('Deskripsi kegiatan...') }}"></textarea>
              @error('activityDescription')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">{{ __('Target Output') }}</label>
              <input type="text" class="form-control form-control-sm @error('activityOutputTarget') is-invalid @enderror"
                wire:model="activityOutputTarget"
                placeholder="{{ __('Misal: 1 sistem, 100 user') }}">
              @error('activityOutputTarget')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">{{ __('Volume') }}</label>
              <input type="number" class="form-control form-control-sm @error('activityQuantity') is-invalid @enderror"
                wire:model="activityQuantity" min="1">
              @error('activityQuantity')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">{{ __('Satuan') }}</label>
              <input type="text" class="form-control form-control-sm @error('activityUnit') is-invalid @enderror"
                wire:model="activityUnit" list="satuan-options-verifier"
                placeholder="{{ __('Paket, Unit, ...') }}" autocomplete="off">
              @error('activityUnit')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          {{-- Budget Items section --}}
          <div x-data="{
                    dropdownOpen: false,
                }" @coa-dropdown-open-new.window="dropdownOpen = true"
            @coa-dropdown-close-new.window="dropdownOpen = false">

            <div class="table-responsive" :class="dropdownOpen ? 'rkap-table-responsive-visible' : ''">
              <table class="table table-sm table-bordered align-middle mb-2">
                <thead class="table-primary text-white fw-semibold">
                  <tr>
                    <th class="text-center align-middle w-30p">{{ __('Uraian & Detail Belanja') }} <span class="text-warning">*</span></th>
                    <th class="text-center align-middle w-10p">{{ __('Vol') }} <span class="text-warning">*</span></th>
                    <th class="text-center align-middle w-8p">Satuan</th>
                    <th class="text-center align-middle rkap-col-180">Harga Satuan (Rp) <span class="text-warning">*</span></th>
                    <th class="text-center align-middle rkap-col-160">{{ __('Total (Rp)') }}</th>
                    <th class="text-center align-middle rkap-w-80">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  @php
                  $newGroups = [];
                  $currentGroup = null;
                  foreach ($newActivityBudgetItems as $biIdx => $bi) {
                  $coaId = $bi['coa_id'] ?? null;
                  if ($coaId !== null && $currentGroup !== null && $currentGroup['coa_id'] === $coaId) {
                  $currentGroup['items'][] = ['index' => $biIdx, 'item' => $bi];
                  } else {
                  if ($currentGroup !== null) {
                  $newGroups[] = $currentGroup;
                  }
                  $currentGroup = [
                  'coa_id' => $coaId,
                  'items' => [['index' => $biIdx, 'item' => $bi]],
                  ];
                  }
                  }
                  if ($currentGroup !== null) {
                  $newGroups[] = $currentGroup;
                  }
                  @endphp

                  @foreach ($newGroups as $gIdx => $group)
                  @php
                  $itemCount = count($group['items']);
                  $firstIdx = $group['items'][0]['index'];
                  $firstBi = $group['items'][0]['item'];
                  $selectedCoa = $this->coaOptionsList->firstWhere('id', $firstBi['coa_id']);
                  $filteredCoas = $this->coaOptionsList;
                  $filteredCoasOrdered = $filteredCoas;
                  if ($firstBi['coa_id'] ?? null) {
                  $filteredCoasOrdered = $filteredCoas
                  ->sortBy(fn($c) => $c->id === $firstBi['coa_id'] ? 0 : 1)
                  ->values();
                  }
                  $searchLabel = '';
                  if ($selectedCoa) {
                  $searchLabel = $selectedCoa->code . ' — ' . $selectedCoa->title;
                  } elseif (!empty($firstBi['account_code']) || !empty($firstBi['description'])) {
                  $searchLabel = trim(
                  ($firstBi['account_code'] ?? '') .
                  (!empty($firstBi['description']) ? ' — ' . ($firstBi['description'] ?? '') : ''),
                  );
                  }
                  $groupSubtotal = collect($group['items'])->sum(function ($info) {
                  $bi = $info['item'];
                  $qty2 = !empty($bi['unit_2']) ? (float) ($bi['quantity_2'] ?? 1) : 1;
                  return ((float) ($bi['quantity'] ?? 0)) * $qty2 * ((float) ($bi['unit_price'] ?? 0));
                  });
                  $totalItemsCount = count($newActivityBudgetItems);
                  $indices = array_column($group['items'], 'index');
                  $indicesJson = json_encode($indices);
                  @endphp

                  <tr wire:key="new-bi-group-{{ $gIdx }}-coa"
                    class="{{ $gIdx % 2 == 1 ? 'bg-group-alt' : '' }}">
                    <td colspan="6"
                      wire:key="new-coa-cell-g{{ $gIdx }}-{{ $firstBi['coa_id'] ?? 'none' }}-{{ md5($searchLabel) }}"
                      x-data="{
                                  open: false,
                                  search: @js($searchLabel),
                                  currentLabel: @js($searchLabel),
                              }"
                      x-effect="if (!open && search !== currentLabel) search = currentLabel"
                      :class="open ? 'rkap-position-relative rkap-z-1060' : ''"
                      @click.outside="open = false; $dispatch('coa-dropdown-close-new')" class="border-bottom-0">
                      <div class="position-relative">
                        <div class="input-group input-group-sm">
                          <input type="text"
                            class="form-control form-control-sm @error('newActivityBudgetItems.' . $firstIdx . '.coa_id') is-invalid @enderror"
                            placeholder="{{ __('Cari akun/belanja...') }}" x-model="search"
                            @focus="open = true; $dispatch('coa-dropdown-open-new')"
                            @input="open = true; $dispatch('coa-dropdown-open-new')" autocomplete="off">
                          @if ($firstBi['coa_id'])
                          <button type="button" class="btn btn-sm btn-outline-secondary"
                            wire:click="updateNewGroupCoa({{ $firstIdx }}, null)"
                            @click="search = ''; currentLabel = ''; open = false; $dispatch('coa-dropdown-close-new');"
                            title="{{ __('Hapus pilihan') }}">
                            <i class="bx bx-x"></i>
                          </button>
                          @endif
                          @if ($totalItemsCount > $itemCount)
                          <button type="button"
                            wire:click="removeNewGroup({{ $indicesJson }})"
                            class="btn btn-sm btn-outline-danger"
                            title="{{ __('Hapus grup akun belanja') }}">
                            <i class="bx bx-trash"></i>
                          </button>
                          @endif
                          <span class="input-group-text px-2 fw-semibold text-nowrap rkap-coa-sum-chip">
                            <i class="bx bx-sum me-1 rkap-coa-sum-icon"></i>
                            Rp {{ number_format($groupSubtotal, 0, ',', '.') }}
                          </span>
                        </div>
                        @error('newActivityBudgetItems.' . $firstIdx . '.coa_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <select wire:model.live="newActivityBudgetItems.{{ $firstIdx }}.coa_id" class="d-none">
                          <option value=""></option>
                          @foreach ($filteredCoasOrdered as $coa)
                          <option value="{{ $coa->id }}">{{ $coa->code }} — {{ $coa->title }}</option>
                          @endforeach
                        </select>
                        <div x-show="open" x-cloak
                          class="position-absolute bg-white border rounded shadow-sm w-100 mt-1 rkap-dropdown-menu">
                          @foreach ($filteredCoasOrdered as $coa)
                          <div
                            class="px-3 py-2 cursor-pointer dropdown-item small {{ ($firstBi['coa_id'] ?? null) == $coa->id ? 'bg-primary text-white' : '' }}"
                            x-show="'{{ strtolower(addslashes($coa->code . ' ' . $coa->title)) }}'.includes(search.toLowerCase())"
                            @click="
                                          $wire.call('updateNewGroupCoa', {{ $firstIdx }}, {{ $coa->id }});
                                          currentLabel = '{{ addslashes($coa->code . ' — ' . $coa->title) }}';
                                          search = currentLabel;
                                          open = false;
                                          $dispatch('coa-dropdown-close-new');
                                      ">
                            <span class="fw-semibold text-primary">{{ $coa->code }}</span>
                            <span class="ms-1">{{ $coa->title }}</span>
                          </div>
                          @endforeach
                        </div>
                      </div>
                    </td>
                  </tr>

                  @foreach ($group['items'] as $itemIdx => $itemInfo)
                  @php
                  $biIdx = $itemInfo['index'];
                  $bi = $itemInfo['item'];
                  $qty2 = !empty($bi['unit_2']) ? (float) ($bi['quantity_2'] ?? 1) : 1;
                  $biTotal = ((float) ($bi['quantity'] ?? 0)) * $qty2 * ((float) ($bi['unit_price'] ?? 0));
                  @endphp
                  <tr wire:key="new-bi-{{ $biIdx }}-detail"
                    class="{{ $gIdx % 2 == 1 ? 'bg-group-alt' : '' }}">
                    <td class="border-top-0">
                      <input type="text" class="form-control form-control-sm"
                        wire:model="newActivityBudgetItems.{{ $biIdx }}.remarks"
                        placeholder="{{ __('Detail Belanja / Ket...') }}">
                    </td>
                    <td class="border-top-0 rkap-rp-input">
                      {{-- Vol 1 --}}
                      <input type="number" step="any"
                        class="form-control form-control-sm mb-2 @error('newActivityBudgetItems.' . $biIdx . '.quantity') is-invalid @enderror"
                        wire:model.live.debounce.500ms="newActivityBudgetItems.{{ $biIdx }}.quantity"
                        min="0.0001" placeholder="Vol 1">

                      {{-- Vol 2 --}}
                      <input type="number" step="any"
                        class="form-control form-control-sm @error('newActivityBudgetItems.' . $biIdx . '.quantity_2') is-invalid @enderror"
                        wire:model.live.debounce.500ms="newActivityBudgetItems.{{ $biIdx }}.quantity_2"
                        min="0.0001" placeholder="Vol 2">
                    </td>
                    <td class="border-top-0 rkap-position-relative rkap-rp-input">
                      {{-- Satuan 1 --}}
                      <input type="text"
                        class="form-control form-control-sm mb-2 @error('newActivityBudgetItems.' . $biIdx . '.unit') is-invalid @enderror"
                        wire:model="newActivityBudgetItems.{{ $biIdx }}.unit"
                        list="satuan-options-verifier" placeholder="Satuan 1" autocomplete="off">

                      {{-- Satuan 2 --}}
                      <input type="text"
                        class="form-control form-control-sm @error('newActivityBudgetItems.' . $biIdx . '.unit_2') is-invalid @enderror"
                        wire:model.live.debounce.500ms="newActivityBudgetItems.{{ $biIdx }}.unit_2"
                        list="satuan-options-verifier" placeholder="Satuan 2 (opsional)" autocomplete="off">
                    </td>
                    <td class="border-top-0 rkap-min-w-180">
                      <div x-data="{
                                    raw: {{ (int) ($bi['unit_price'] ?? 0) }},
                                    display: '',
                                    timer: null,
                                    fmt(n) { return n > 0 ? new Intl.NumberFormat('id-ID').format(n) : '' },
                                    sync() {
                                        clearTimeout(this.timer);
                                        this.timer = setTimeout(() => {
                                            $wire.set('newActivityBudgetItems.{{ $biIdx }}.unit_price', this.raw);
                                        }, 500);
                                    },
                                    onInput(e) {
                                        let d = e.target.value.replace(/\D/g, '');
                                        this.raw = parseInt(d) || 0;
                                        this.display = this.fmt(this.raw);
                                        e.target.value = this.display;
                                        this.sync();
                                    },
                                    onBlur(e) {
                                        if (!this.raw) this.raw = 0;
                                        e.target.value = this.fmt(this.raw);
                                        clearTimeout(this.timer);
                                        $wire.set('newActivityBudgetItems.{{ $biIdx }}.unit_price', this.raw);
                                    }
                                }" x-init="display = fmt(raw)">
                        <div class="input-group input-group-sm">
                          <span class="input-group-text rkap-font-075">Rp</span>
                          <input type="text"
                            class="form-control form-control-sm text-end @error('newActivityBudgetItems.' . $biIdx . '.unit_price') is-invalid @enderror"
                            :value="display" @input="onInput($event)" @blur="onBlur($event)"
                            @focus="$event.target.select()" placeholder="0" inputmode="numeric">
                        </div>
                      </div>
                    </td>
                    <td class="text-end text-nowrap border-top-0">
                      <div class="fw-semibold text-primary">Rp {{ number_format($biTotal, 0, ',', '.') }}</div>
                    </td>
                    <td class="text-center text-nowrap border-top-0">
                      <div class="d-flex align-items-center justify-content-center gap-1">
                        @if ($itemCount > 1)
                        <button type="button"
                          wire:click="removeNewBudgetItem({{ $biIdx }})"
                          class="btn btn-sm btn-icon btn-outline-danger"
                          title="{{ __('Hapus detail rincian ini') }}">
                          <i class="bx bx-trash"></i>
                        </button>
                        @endif

                        @if ($itemIdx === $itemCount - 1)
                        <button type="button"
                          wire:click="duplicateNewBudgetItem({{ $biIdx }})"
                          class="btn btn-sm btn-icon btn-outline-success"
                          title="{{ __('Tambah detail rincian untuk akun ini') }}">
                          <i class="bx bx-plus"></i>
                        </button>
                        @endif
                      </div>
                    </td>
                  </tr>
                  @endforeach
                  @endforeach
                </tbody>
              </table>
            </div>

            <button type="button" wire:click="addNewBudgetItem"
              class="btn btn-sm btn-label-secondary mt-2">
              <i class="bx bx-plus me-1"></i> {{ __('Tambah Item Belanja') }}
            </button>

          </div>
          @else
          <div class="alert alert-info d-flex align-items-center mb-0 mt-3">
            <i class="bx bx-info-circle me-2 fs-4"></i>
            <div>
              {{ __('Silakan pilih Program Kerja dan Nama Kegiatan terlebih dahulu untuk') }}
              {{ __('mengisi detail anggaran belanja.') }}
            </div>
          </div>
          @endif
        </div>
      </div>

      {{-- Satuan datalist --}}
      <datalist id="satuan-options-verifier">
        @foreach ($this->satuanOptions as $satuanOpt)
        <option value="{{ $satuanOpt->name }}"></option>
        @endforeach
      </datalist>
      @endif
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
        class="card mb-4 border-start border-primary border-3 @if ($isProgramVirtual) rkap-virtual-program-card @endif">
        <div class="card-header border-bottom py-3 @if ($isProgramVirtual) rkap-virtual-program-header @endif">
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
                {{ $programCode }} — {{ $programName }}
              </div>
            </div>
            <div class="text-end">
              <span class="text-muted small d-block">{{ __('Subtotal Program') }}</span>
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
                  <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">{{ __('RKAP Periode Sebelumnya') }}
                    ({{ $prevPeriod }})
                  </div>
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

        <div class="card-body bg-group-alt p-3">
          @foreach ($wpGroup as $actIdx => $wp)
          @php
          $isWpVirtual = $wp['is_virtual'] ?? false;
          $activity = $wp['model'] ? $wp['model']->activity : null;
          $activityCode = $activity ? $activity->code : ($wp['program_code'] ?: '-');
          $activityTitle = $activity ? $activity->title : ($wp['program_name'] ?: '-');
          $actSubtotal = (float) $wp['total_budget'];
          @endphp
          <div class="activity-card p-3 mb-3 @if ($isWpVirtual) rkap-activity-card-virtual @endif">
            <div class="d-flex justify-content-between align-items-start gap-3 border-bottom pb-2 mb-3">
              {{-- Left: icon + activity info --}}
              <div class="d-flex align-items-center gap-2 flex-grow-1 overflow-hidden">
                <span
                  class="badge @if ($isWpVirtual) bg-label-danger @else bg-label-primary @endif rounded-circle p-2 flex-shrink-0"><i
                    class="bx bx-task"></i></span>
                <div class="overflow-hidden">
                  <div class="d-flex align-items-center flex-wrap gap-1 mb-1">
                    <h6 class="mb-0 fw-bold @if ($isWpVirtual) text-danger @endif">Kegiatan
                      {{ $actIdx + 1 }}
                    </h6>
                    @if ($isWpVirtual)
                    <span class="badge bg-danger rkap-font-06">Tidak Diajukan Kembali</span>
                    @endif
                    @if ($wp['model'] && $wp['model']->is_past_period_payment)
                    <span class="badge bg-warning text-dark ms-1 rkap-font-06">
                      <i class="bx bx-calendar-exclamation me-1"></i>Pembayaran Periode Lalu
                      @if ($wp['model']->pastPeriod) — {{ $wp['model']->pastPeriod->year }}@endif
                    </span>
                    @endif
                    @if ($wp['model'] && $wp['model']->transferred_from)
                    <span class="badge bg-info text-white ms-1 rkap-font-06">
                      <i class="bx bx-transfer me-1"></i>{{ __('Ditransfer dari') }} {{ $wp['model']->transferred_from->name }}
                    </span>
                    @endif
                  </div>
                  <span
                    class="@if ($isWpVirtual) text-danger @else text-muted @endif small d-block text-truncate">{{ $activityCode }}
                    — {{ $activityTitle }}</span>
                  @if ($wp['model'] && $wp['model']->is_past_period_payment && $wp['model']->pastPeriod)
                  <div class="d-flex align-items-center gap-1 mt-1 rkap-font-078">
                    <i class="bx bx-info-circle text-warning flex-shrink-0"></i>
                    <span class="text-warning fw-semibold">Anggaran rencana pembayaran kewajiban untuk
                      RKAP {{ $wp['model']->pastPeriod->year }} — {{ $wp['model']->pastPeriod->title }}</span>
                  </div>
                  @endif
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
                <div class="d-flex gap-2">
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
                  @if (auth()->user()->isVerifikator() && $wp['model']?->added_by_verifier)
                  <button type="button" class="btn btn-sm btn-danger"
                    wire:click="deleteWorkPlan({{ $wpModelId }})"
                    wire:confirm="Apakah Anda yakin ingin menghapus kegiatan yang ditambahkan ini beserta semua item anggarannya?">
                    <i class="bx bx-trash me-1"></i>Hapus
                  </button>
                  @endif
                </div>
                @else
                @if ($currStatus === 'approved')
                <span class="badge bg-label-success fs-6"><i
                    class="bx bx-check-circle me-1"></i>{{ __('Disetujui') }}</span>
                @elseif($currStatus === 'rejected')
                <span class="badge bg-label-danger fs-6"><i class="bx bx-x-circle me-1"></i>{{ __('Revisi') }}</span>
                @else
                <span class="badge bg-label-secondary fs-6"><i
                    class="bx bx-time-five me-1"></i>{{ __('Pending') }}</span>
                @endif
                @endif
                @endif
                <div class="text-end">
                  <span class="text-muted small d-block">{{ __('Subtotal Kegiatan') }}</span>
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
            </div>

            @if ($wp['description'])
            <div class="mb-3">
              <label class="text-muted small d-block">{{ __('Deskripsi / Tujuan') }}</label>
              <p class="mb-0 text-dark @if ($isWpVirtual) text-danger @endif rkap-preline">{{ $wp['description'] }}</p>
            </div>
            @endif

            {{-- Revision Changes Summary --}}
            @if (!$isWpVirtual && isset($revisionChanges[$wp['model']?->id]))
            @php
            $actChanges = $revisionChanges[$wp['model']->id];
            @endphp
            @if ($actChanges['has_changes'])
            <div class="alert alert-warning border-warning p-3 mb-3 rkap-bg-revision-alert">
              <h6 class="alert-heading fw-bold text-warning mb-2 d-flex align-items-center">
                <i class="bx bx-edit-alt me-1"></i> Perubahan pada Revisi Ini (Versi
                {{ $submission->current_version - 1 }} → {{ $submission->current_version }})
              </h6>
              <ul class="mb-0 ps-3 small text-dark rkap-list-disc">
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
                  <ul class="mb-0 ps-3 rkap-list-circle">
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
                <span class="text-muted">{{ __('Target Output:') }}</span> <span
                  class="fw-medium @if ($isWpVirtual) text-danger @else text-dark @endif">{{ $wp['output_target'] ?? '-' }}</span>
              </div>
              <div class="col-auto ms-3">
                <span class="text-muted">{{ __('Volume:') }}</span> <span
                  class="fw-medium @if ($isWpVirtual) text-danger @else text-dark @endif">{{ $wp['quantity'] }}
                  {{ $wp['unit'] }}</span>
              </div>
            </div>

            @if ($wp['model'] && $wp['model']->activityFiles && $wp['model']->activityFiles->isNotEmpty())
            <div class="mb-3" wire:key="act-view-files-approval-{{ $wp['model']->id }}">
              <label class="text-muted small d-block mb-1 fw-semibold"><i class="bx bx-paperclip me-1"></i>{{ __('File Referensi:') }}</label>
              <div class="d-flex flex-wrap gap-2">
                @foreach ($wp['model']->activityFiles as $file)
                @php
                $isViewable = in_array(strtolower($file->file_type), ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'svg']);
                @endphp
                @if ($isViewable)
                <button type="button" class="btn btn-xs btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewFileModal-{{ $file->id }}">
                  <i class="bx bx-show me-1"></i> {{ $file->original_name }}
                </button>
                <!-- Modal for displaying inline -->
                <div class="modal fade" id="viewFileModal-{{ $file->id }}" tabindex="-1" aria-hidden="true" wire:key="view-file-modal-approval-{{ $file->id }}">
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
                        <img src="{{ route('rkap-files.view', $file->id) }}" class="img-fluid p-3 rkap-max-h-75vh rkap-obj-contain" />
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
                {{ $wp['model']?->revision_notes ?: 'Tidak ada catatan revisi.' }}
              </p>
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
                    <th>{{ __('Uraian & Detail Belanja') }}</th>
                    <th class="text-center rkap-w-10p">Vol</th>
                    <th class="rkap-w-12p">Satuan</th>
                    <th class="text-end rkap-w-14p">{{ __('Harga Satuan') }}</th>
                    <th class="text-end rkap-w-16p">{{ __('Total') }}</th>
                    <th class="text-center rkap-w-8p">Aksi</th>
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


                          <div class="has-tooltip rkap-font-078 rkap-lh-12">
                            <span class="text-muted rkap-font-068">Sub-total:</span>
                            <span class="fw-bold text-primary">
                              Rp {{ number_format($coaGroupSubtotal, 0, ',', '.') }}
                            </span>
                            <span class="custom-tooltip-content tooltip-align-right">
                              @if ($prevCoaData)
                              <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">RKAP
                                Periode Sebelumnya ({{ $prevPeriod }})</div>
                              <div class="row text-center">
                                <div class="col-4 border-end">
                                  <div class="text-white-50 small rkap-font-065">Anggaran
                                  </div>
                                  <div class="fw-bold text-white rkap-font-075">Rp
                                    {{ number_format($prevCoaData['budget'], 0, ',', '.') }}
                                  </div>
                                </div>
                                <div class="col-4 border-end">
                                  <div class="text-white-50 small rkap-font-065">Realisasi
                                  </div>
                                  <div class="fw-bold text-white text-success rkap-font-075">
                                    Rp {{ number_format($prevCoaData['realization'], 0, ',', '.') }}</div>
                                </div>
                                <div class="col-4">
                                  <div class="text-white-50 small rkap-font-065">Proyeksi
                                  </div>
                                  <div class="fw-bold text-white text-warning rkap-font-075">
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
                    class="@if ($isBiVirtual) rkap-bg-revision-alert @elseif($isBiRevisionAdded) rkap-bg-revision-added @elseif($biRevisionModifiedData) rkap-bg-revision-modified @endif">
                    <td>
                      <span
                        class="@if ($isBiVirtual) text-danger text-decoration-line-through @endif">
                        {{ $bi['remarks'] ?: $bi['description'] }}
                      </span>
                      @if ($isBiVirtual)
                      <span class="badge bg-label-danger ms-1 rkap-font-06">Dihapus</span>
                      @endif
                      @if ($isBiRevisionAdded)
                      <span class="badge bg-success ms-1 rkap-font-06">Baru</span>
                      @endif
                      @if ($biRevisionModifiedData)
                      <span class="badge bg-warning text-dark ms-1 rkap-font-06">Diubah</span>
                      @endif

                      @if ($isEditMode && !$isBiVirtual)
                      @php
                      $currentCoaCode = $editCoas[$biModelId] ?? '';
                      $selectedCoaOption = $this->coaOptionsList->firstWhere('code', $currentCoaCode);
                      $coaSearchLabel = $selectedCoaOption ? $selectedCoaOption->code . ' — ' . $selectedCoaOption->title : '';
                      @endphp
                      <div class="mt-2 position-relative" wire:key="edit-coa-container-{{ $biModelId }}"
                        x-data="{
                                        open: false,
                                        search: @js($coaSearchLabel),
                                        currentLabel: @js($coaSearchLabel),
                                    }"
                        x-effect="if (!open && search !== currentLabel) search = currentLabel"
                        @click.outside="open = false; $dispatch('coa-dropdown-close')">
                        <label class="form-label small fw-semibold text-primary mb-1">Ubah COA:</label>
                        <div class="input-group input-group-sm">
                          <input type="text"
                            class="form-control form-control-sm"
                            placeholder="Cari COA..." x-model="search"
                            @focus="open = true; $dispatch('coa-dropdown-open')"
                            @input="open = true; $dispatch('coa-dropdown-open')" autocomplete="off"
                            id="coa-search-{{ $biModelId }}">
                          @if ($currentCoaCode)
                          <button type="button" class="btn btn-sm btn-outline-secondary"
                            wire:click="$set('editCoas.{{ $biModelId }}', null)"
                            @click="search = ''; currentLabel = ''; open = false; $dispatch('coa-dropdown-close')">
                            <i class="bx bx-x"></i>
                          </button>
                          @endif
                        </div>
                        <select wire:model.live="editCoas.{{ $biModelId }}" class="d-none" id="coa-select-{{ $biModelId }}">
                          <option value=""></option>
                          @foreach ($this->coaOptionsList as $coaOption)
                          <option value="{{ $coaOption->code }}">{{ $coaOption->code }} — {{ $coaOption->title }}</option>
                          @endforeach
                        </select>
                        <div x-show="open" x-cloak
                          class="position-absolute bg-white border rounded shadow-sm w-100 mt-1 rkap-z-1060 rkap-overflow-y-auto rkap-h-200-scroll">
                          @foreach ($this->coaOptionsList as $coaOption)
                          <div
                            class="px-3 py-2 cursor-pointer dropdown-item small {{ $currentCoaCode == $coaOption->code ? 'bg-primary text-white' : '' }}"
                            x-show="'{{ strtolower(addslashes($coaOption->code . ' ' . $coaOption->title)) }}'.includes(search.toLowerCase())"
                            @click="
                                              $wire.set('editCoas.{{ $biModelId }}', '{{ $coaOption->code }}');
                                              currentLabel = '{{ addslashes($coaOption->code . ' — ' . $coaOption->title) }}';
                                              search = currentLabel;
                                              open = false;
                                              $dispatch('coa-dropdown-close');
                                          ">
                            <span class="fw-semibold text-primary">{{ $coaOption->code }}</span>
                            <span class="ms-1">{{ $coaOption->title }}</span>
                          </div>
                          @endforeach
                        </div>
                      </div>
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
                        {{ $biRevisionModifiedData['quantity'] }}
                      </div>
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
                        {{ $biRevisionModifiedData['unit'] }}
                      </div>
                      @endif
                    </td>
                    <td class="text-end @if ($isBiVirtual) text-danger @endif">
                      Rp {{ number_format($bi['unit_price'], 0, ',', '.') }}
                      @if ($biRevisionModifiedData && $biRevisionModifiedData['unit_price'] != $bi['unit_price'])
                      <div class="text-muted small text-decoration-line-through text-end">Sblm: Rp
                        {{ number_format($biRevisionModifiedData['unit_price'], 0, ',', '.') }}
                      </div>
                      @endif
                    </td>
                    <td class="text-end text-nowrap">
                      <div class="fw-semibold @if ($isBiVirtual) text-danger @else text-primary @endif has-tooltip">
                        Rp {{ number_format($bi['total_price'], 0, ',', '.') }}
                        <span class="custom-tooltip-content tooltip-align-right rkap-w-280 fw-normal">
                          @if ($prevItemBudget !== null)
                          <div class="fw-semibold text-center border-bottom pb-1 mb-2 text-white">{{ __('RKAP Periode Sebelumnya') }} ({{ $prevPeriod }})</div>
                          <div class="row text-center rkap-mw-250">
                            <div class="col-6 border-end">
                              <div class="text-white-50 small rkap-font-065">Anggaran Sblm</div>
                              <div class="fw-bold text-white rkap-font-075">Rp {{ number_format($prevItemBudget, 0, ',', '.') }}</div>
                            </div>
                            <div class="col-6">
                              <div class="text-white-50 small rkap-font-065">Selisih (Δ)</div>
                              <div class="fw-bold @if($itemDiff > 0) text-danger @elseif($itemDiff < 0) text-success @else text-white @endif rkap-font-075">
                                @if ($itemDiff > 0)
                                ↑ +{{ number_format($itemPct, 1) }}%<br><span class="rkap-font-068">(+Rp {{ number_format($itemDiff, 0, ',', '.') }})</span>
                                @elseif ($itemDiff < 0)
                                  ↓ -{{ number_format(abs($itemPct), 1) }}%<br><span class="rkap-font-068">(-Rp {{ number_format(abs($itemDiff), 0, ',', '.') }})</span>
                                  @else
                                  = 0%<br><span class="rkap-font-068">(Rp 0)</span>
                                  @endif
                              </div>
                            </div>
                          </div>
                          @else
                          <div class="text-center text-white-50 py-1">{{ __('Tidak ada data di periode sebelumnya') }}</div>
                          @endif
                        </span>
                      </div>
                      @if ($biRevisionModifiedData && $biRevisionModifiedData['total_price'] != $bi['total_price'])
                      <div class="text-muted small text-decoration-line-through text-end rkap-font-075">Sblm: Rp
                        {{ number_format($biRevisionModifiedData['total_price'], 0, ',', '.') }}
                      </div>
                      @endif
                    </td>
                    <td class="text-center">
                      @if (!$isBiVirtual && ($bi['monthlies']->isNotEmpty() || $bi['cashOuts']->isNotEmpty()))
                      <div class="d-flex justify-content-center gap-1">
                        <button type="button" class="btn btn-xs btn-outline-primary"
                          data-bs-toggle="modal" data-bs-target="#{{ $allocationModalId }}"
                          title="Detail Alokasi">
                          <i class="bx bx-detail"></i>
                        </button>
                        @if ($this->canApprove() && auth()->user()->isVerifikator() && $wp['model']?->added_by_verifier)
                        <button type="button" class="btn btn-xs btn-outline-danger"
                          wire:click="deleteBudgetItem({{ $bi['model']->id }})"
                          wire:confirm="Apakah Anda yakin ingin menghapus item anggaran ini?"
                          title="Hapus Item">
                          <i class="bx bx-trash"></i>
                        </button>
                        @endif
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
                                        <span class="text-muted d-block mb-1">{{ __('Kode Akun') }}</span>
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
                                        <span class="text-muted d-block mb-1">{{ __('Volume') }}</span>
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
                                        <span class="text-muted d-block mb-1">{{ __('Total Anggaran') }}</span>
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
                                $coa = $bi['model']?->coa ?? \App\Models\Coa::with(['coaGroup', 'cashflowGroup', 'differenceGroups'])->where('code', $bi['account_code'])->first();
                                @endphp

                                @if (!$hasAnyValue)
                                <div class="text-center text-muted py-4">
                                  <i class="bx bx-info-circle fs-3 mb-2 d-block"></i>
                                  <span class="small">{{ __('Tidak ada data alokasi anggaran') }}</span>
                                </div>
                                @else
                                <div class="border rounded-2 table-responsive mb-2">
                                  <table class="table table-sm table-bordered align-middle mb-0 rkap-min-w-750">
                                    <thead class="table-primary">
                                      <tr>
                                        <th class="text-center rkap-w-90">{{ __('Bulan') }}</th>
                                        <th class="text-end">
                                          Distribusi Penganggaran (Rp)
                                          <div class="small fw-normal text-muted rkap-font-065 rkap-opacity-85">({{ $coa?->coaGroup?->name ?: '-' }})</div>
                                        </th>
                                        <th class="text-end">
                                          Rencana Pendanaan (Rp)
                                          <div class="small fw-normal text-muted rkap-font-065 rkap-opacity-85">({{ $coa?->cashflowGroup?->name ?: '-' }})</div>
                                        </th>
                                        <th class="text-end">
                                          Selisih (Rp)
                                          <div class="small fw-normal text-muted rkap-font-065 rkap-opacity-85">({{ ($coa && $coa->differenceGroups->isNotEmpty()) ? $coa->differenceGroups->pluck('name')->implode(', ') : '-' }})</div>
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
                        @endif
                    </td>
                  </tr>
                  @endforeach

                  {{-- Removed Budget Items in Revision --}}
                  @if ($actChanges && !empty($actChanges['removed_items']))
                  @foreach ($actChanges['removed_items'] as $removedBi)
                  <tr class="rkap-bg-revision-removed">
                    <td class="text-danger">
                      <span class="text-decoration-line-through">
                        <strong>{{ $removedBi['account_code'] }}</strong> -
                        {{ $removedBi['remarks'] ?: $removedBi['description'] }}
                      </span>
                      <span class="badge bg-label-danger ms-1 rkap-font-06">Dihapus pada
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
</div>